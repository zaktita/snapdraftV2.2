# Production Deploy Ops

This complements the GitHub Actions workflow at `.github/workflows/deploy.yml`.

Repo helpers added for go-live:

- `php artisan production:check --skip-payments` — validate must-have env/connectivity
- `php artisan production:check --skip-payments --probe-storage --strict` — also probe S3
- `php artisan admin:create you@domain.com --name="You"` — create prod admin (seeder skips production)
- `deploy/supervisor-snapdraft-worker.conf` — copy to `/etc/supervisor/conf.d/`
- `deploy/nginx-snapdraft.example.conf` — Nginx starting point

---

## What you must do manually (cannot be automated in git)

1. Provision a VPS/Forge server (PHP 8.2+, MySQL, Redis, Nginx, SSL).
2. Point DNS A/AAAA records at the server; issue Let's Encrypt cert.
3. Clone the repo to `DEPLOY_PATH` (e.g. `/var/www/snapdraft`) and create `.env` from `.env.example`.
4. Fill secrets in `.env` (DB, Redis, AWS/S3, mail, Gemini, OpenRouter, `APP_KEY`, `APP_URL`).
5. Install PHP Redis extension (`phpredis`) and Composer deps on the server.
6. Create S3/Spaces bucket + IAM keys with put/get/delete on that bucket.
7. Verify mail domain (SPF/DKIM) at Postmark or Resend.
8. Install Supervisor worker config and enable cron for the scheduler.
9. Add GitHub Actions secrets for deploy (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`, `DEPLOY_PORT`, `DEPLOY_HEALTHCHECK_URL`).
10. Create the first admin: `php artisan admin:create …` then enable 2FA.
11. (Later) Lemon Squeezy + Google OAuth console redirect — not required for first infra boot.

---

## First-boot commands (on the server)

```bash
cd /var/www/snapdraft

cp .env.example .env
php artisan key:generate
# edit .env with real values

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force

php artisan production:check --skip-payments --probe-storage --strict
php artisan admin:create you@yourdomain.com --name="Your Name"

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Generate invite codes until Lemon is live:

```bash
php artisan beta:invites 20
```

---

## Required GitHub Secrets

- DEPLOY_HOST
- DEPLOY_USER
- DEPLOY_SSH_KEY
- DEPLOY_PORT (optional, defaults to 22)
- DEPLOY_PATH (absolute path to app on server, for example /var/www/snapdraft)
- DEPLOY_HEALTHCHECK_URL (recommended, for example https://app.example.com/up)

---

## Supervisor Worker Config

Copy `deploy/supervisor-snapdraft-worker.conf` to:

`/etc/supervisor/conf.d/snapdraft-worker.conf`

Edit the path/user if needed, then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start snapdraft-worker:*
```

For ~100–500 users, start with `numprocs=3` and scale to 5 if failed jobs grow. Set `REDIS_QUEUE_RETRY_AFTER=700`.

If you use database queue, replace `redis` with `database` in the command (not recommended for open launch).

---

## Cron Scheduler

```cron
* * * * * cd /var/www/snapdraft && php artisan schedule:run >> /dev/null 2>&1
```

```bash
sudo crontab -u www-data -e
```

---

## First-Time Server Prep Checklist

1. Ensure `.env` exists with `APP_ENV=production`, `APP_DEBUG=false`.
2. Set `FILESYSTEM_DISK=s3` and `AWS_*` credentials (or Spaces endpoint). User uploads are **private** and served via `/media/*` (auth required).
3. Set `SENTRY_LARAVEL_DSN` and `VITE_SENTRY_DSN` (same DSN) for error monitoring (rebuild assets after Vite DSN).
4. Paste Lemon Squeezy variant IDs when ready, then re-run `php artisan db:seed --class=PlanSeeder`.
5. Ensure PHP extensions are installed (`curl`, `gd`, `mbstring`, `pdo_mysql`, `redis`, `zip`).
6. Ensure write permissions for `storage/` and `bootstrap/cache/`.
7. Ensure Nginx site root points to `/var/www/snapdraft/public` (see `deploy/nginx-snapdraft.example.conf`).
8. Ensure SSL is configured.
9. Ensure Redis and MySQL are healthy.
10. Labs and test routes are **not registered** when `APP_ENV` is not `local`.

---

## Private media

- New uploads go to the `media` disk (`storage/app/media` locally, S3 private in production).
- Legacy files in `storage/app/public` still work until migrated.
- Optional migration: `php artisan media:migrate-to-private` (copies `projects/` from public to media).

---

## Sentry

- Backend: `SENTRY_LARAVEL_DSN` in `.env`
- Frontend: `VITE_SENTRY_DSN` (rebuild assets after setting)
- Rebuild: `npm run build`

---

## Health Check Endpoint

- DEPLOY_HEALTHCHECK_URL=https://your-domain.com/up
