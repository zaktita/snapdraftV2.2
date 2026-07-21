<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_runs_with_skip_payments(): void
    {
        $exit = Artisan::call('production:check', ['--skip-payments' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('SnapDraft production check', Artisan::output());
    }

    public function test_admin_create_command_creates_admin(): void
    {
        $exit = Artisan::call('admin:create', [
            'email' => 'ops@example.com',
            '--name' => 'Ops',
            '--password' => 'super-secure-pass',
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('users', [
            'email' => 'ops@example.com',
            'is_admin' => 1,
        ]);
    }
}
