<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionCheckCommand extends Command
{
    protected $signature = 'production:check
        {--strict : Exit with code 1 if any required check fails}
        {--skip-payments : Do not require Lemon Squeezy keys (default for pre-billing deploys)}
        {--probe-storage : Write and delete a tiny object on the media disk}';

    protected $description = 'Verify must-have production configuration before go-live';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->info('SnapDraft production check');
        $this->newLine();

        $this->checkApp();
        $this->checkDatabase();
        $this->checkRedisAndQueue();
        $this->checkStorage();
        $this->checkMail();
        $this->checkAi();
        $this->checkAuthOptional();

        if (! $this->option('skip-payments')) {
            $this->checkPayments();
        } else {
            $this->line('  <fg=gray>skip</> Payments (Lemon Squeezy) — --skip-payments');
        }

        $this->newLine();
        $this->line("Failures: {$this->failures}  Warnings: {$this->warnings}");

        if ($this->failures > 0) {
            $this->error('Required checks failed. Fix the items marked FAIL before serving real traffic.');

            return $this->option('strict') || app()->environment('production') ? self::FAILURE : self::SUCCESS;
        }

        if ($this->warnings > 0) {
            $this->warn('Required checks passed with warnings. Review WARN items before open launch.');
        } else {
            $this->info('All required checks passed.');
        }

        $this->newLine();
        $this->comment('Still manual on the server: SSL/domain, Supervisor workers, cron, DNS, backups, GitHub deploy secrets.');

        return self::SUCCESS;
    }

    private function checkApp(): void
    {
        $this->section('Application');

        $this->ok(filled(config('app.key')), 'APP_KEY is set', 'Run: php artisan key:generate');

        if (app()->environment('production')) {
            $this->ok(config('app.debug') === false, 'APP_DEBUG=false', 'Set APP_DEBUG=false in production .env');
        } else {
            $this->warnMsg('APP_ENV is "'.app()->environment().'" (expected production on the live server)');
        }

        $url = (string) config('app.url');
        $this->ok(
            str_starts_with($url, 'https://') && ! str_contains($url, 'yourdomain'),
            'APP_URL is a real https URL',
            'Set APP_URL=https://your-real-domain.com'
        );

        $storage = storage_path('app');
        $this->ok(is_writable($storage), 'storage/app is writable', 'chown/chmod storage and bootstrap/cache for the web user');
        $this->ok(is_writable(storage_path('logs')), 'storage/logs is writable', 'Fix permissions on storage/logs');
        $this->ok(is_writable(base_path('bootstrap/cache')), 'bootstrap/cache is writable', 'Fix permissions on bootstrap/cache');
    }

    private function checkDatabase(): void
    {
        $this->section('Database');

        try {
            DB::connection()->getPdo();
            $this->pass('Database connection works');

            $pending = DB::table('migrations')->count() >= 1;
            $this->ok($pending, 'Migrations table exists', 'Run: php artisan migrate --force');
        } catch (Throwable $e) {
                $this->markFail('Database connection failed: '.$e->getMessage());
        }
    }

    private function checkRedisAndQueue(): void
    {
        $this->section('Queue & cache');

        $queue = (string) config('queue.default');
        $cache = (string) config('cache.default');

        if ($queue === 'redis') {
            $this->pass('QUEUE_CONNECTION=redis');
        } else {
            $this->warnMsg("QUEUE_CONNECTION={$queue} — use redis for hundreds of users");
        }

        if ($cache === 'redis') {
            $this->pass('CACHE_STORE=redis');
        } else {
            $this->warnMsg("CACHE_STORE={$cache} — use redis so rate limits stay fast");
        }

        $retryAfter = (int) config('queue.connections.redis.retry_after', 90);
        $this->ok(
            $retryAfter >= 600,
            "Redis queue retry_after={$retryAfter} (>= 600)",
            'Set REDIS_QUEUE_RETRY_AFTER=700'
        );

        try {
            Redis::connection()->ping();
            $this->pass('Redis ping OK');
        } catch (Throwable $e) {
            if ($queue === 'redis' || $cache === 'redis') {
                $this->markFail('Redis unreachable: '.$e->getMessage());
            } else {
                $this->warnMsg('Redis unreachable (OK if not using redis yet): '.$e->getMessage());
            }
        }
    }

    private function checkStorage(): void
    {
        $this->section('File storage');

        $disk = (string) config('filesystems.default');
        $media = (string) config('filesystems.media_disk');

        if (app()->environment('production')) {
            $this->ok($disk === 's3', 'FILESYSTEM_DISK=s3', 'Set FILESYSTEM_DISK=s3 and AWS_* credentials');
            $this->ok(
                $media === 'media_s3' || $media === 's3',
                "Media disk={$media}",
                'Leave MEDIA_DISK empty so it resolves to media_s3 when FILESYSTEM_DISK=s3'
            );
        } else {
            $this->line("  <fg=gray>info</> FILESYSTEM_DISK={$disk}, media={$media}");
        }

        if ($this->option('probe-storage')) {
            try {
                $path = 'healthchecks/'.uniqid('probe_', true).'.txt';
                $target = Storage::disk(config('filesystems.media_disk'));
                $target->put($path, 'ok');
                $exists = $target->exists($path);
                $target->delete($path);
                $this->ok($exists, 'Media disk write/delete probe succeeded', 'Check AWS_* / bucket permissions');
            } catch (Throwable $e) {
                $this->markFail('Media disk probe failed: '.$e->getMessage());
            }
        } else {
            $this->line('  <fg=gray>skip</> Storage probe (pass --probe-storage to test S3)');
        }
    }

    private function checkMail(): void
    {
        $this->section('Mail');

        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        $this->ok(
            ! in_array($mailer, ['array', 'log', ''], true),
            "MAIL_MAILER={$mailer}",
            'Configure SMTP/Postmark/Resend for production'
        );
        $this->ok(
            filled($from) && ! str_contains($from, 'yourdomain') && filter_var($from, FILTER_VALIDATE_EMAIL),
            'MAIL_FROM_ADDRESS is a real address',
            'Set MAIL_FROM_ADDRESS to a verified sender'
        );
    }

    private function checkAi(): void
    {
        $this->section('AI keys');

        $this->ok(
            filled(config('services.gemini.api_key') ?: env('GEMINI_API_KEY')),
            'GEMINI_API_KEY is set',
            'Add GEMINI_API_KEY from Google AI Studio'
        );
        $this->ok(
            filled(config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY')),
            'OPENROUTER_API_KEY is set',
            'Add OPENROUTER_API_KEY from OpenRouter'
        );
    }

    private function checkAuthOptional(): void
    {
        $this->section('Auth / monitoring (recommended)');

        if (filled(config('services.google.client_id') ?: env('GOOGLE_CLIENT_ID'))) {
            $this->pass('Google OAuth client configured');
        } else {
            $this->warnMsg('Google OAuth not configured (email signup still works)');
        }

        if (filled(env('SENTRY_LARAVEL_DSN'))) {
            $this->pass('SENTRY_LARAVEL_DSN is set');
        } else {
            $this->warnMsg('Sentry DSN missing — errors will only be in logs');
        }
    }

    private function checkPayments(): void
    {
        $this->section('Payments');

        $this->ok(filled(config('services.lemonsqueezy.api_key')), 'LEMON_SQUEEZY_API_KEY', 'Add Lemon API key');
        $this->ok(filled(config('services.lemonsqueezy.store_id')), 'LEMON_SQUEEZY_STORE_ID', 'Add store id');
        $this->ok(filled(config('services.lemonsqueezy.webhook_secret')), 'LEMON_SQUEEZY_WEBHOOK_SECRET', 'Add webhook secret');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->comment($title);
    }

    private function ok(bool $condition, string $pass, string $failHint): void
    {
        if ($condition) {
            $this->pass($pass);
        } else {
            $this->markFail($failHint !== '' ? "{$pass} — {$failHint}" : $pass);
        }
    }

    private function pass(string $message): void
    {
        $this->line("  <fg=green>PASS</> {$message}");
    }

    private function markFail(string $message): void
    {
        $this->failures++;
        $this->line("  <fg=red>FAIL</> {$message}");
    }

    private function warnMsg(string $message): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>WARN</> {$message}");
    }
}
