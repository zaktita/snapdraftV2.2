# Production Deploy Ops

This complements the GitHub Actions workflow at .github/workflows/deploy.yml.

## Required GitHub Secrets

- DEPLOY_HOST
- DEPLOY_USER
- DEPLOY_SSH_KEY
- DEPLOY_PORT (optional, defaults to 22)
- DEPLOY_PATH (absolute path to app on server, for example /var/www/snapdraft)
- DEPLOY_HEALTHCHECK_URL (recommended, for example https://app.example.com/up)

## Supervisor Worker Config

Create this on your server as:

`/etc/supervisor/conf.d/snapdraft-worker.conf`

```ini
[program:snapdraft-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/snapdraft/artisan queue:work redis --sleep=3 --tries=3 --timeout=180 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/snapdraft/storage/logs/worker.log
stopwaitsecs=3600
```

Then apply it:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start snapdraft-worker:*
```

If you use database queue, replace redis with database in the command.

## Cron Scheduler

Add this cron entry for www-data:

```cron
* * * * * cd /var/www/snapdraft && php artisan schedule:run >> /dev/null 2>&1
```

Set it with:

```bash
sudo crontab -u www-data -e
```

## First-Time Server Prep Checklist

1. Ensure .env exists and APP_ENV=production, APP_DEBUG=false.
2. Ensure PHP extensions are installed for this app.
3. Ensure write permissions for storage and bootstrap/cache.
4. Ensure Nginx site root points to /var/www/snapdraft/public.
5. Ensure SSL is configured.
6. Ensure Redis and MySQL are healthy.

## Health Check Endpoint

Use a stable endpoint that returns HTTP 200 when app is healthy.

Suggested value:

- DEPLOY_HEALTHCHECK_URL=https://your-domain.com/up

If your app does not expose /up yet, use a simple known page (for example the homepage) until you add a dedicated health route.
