# Queue Worker Troubleshooting Guide

## Problem: Images Not Generating After CSV Submission

### Symptoms
- CSV wizard submits successfully but no images appear in the project
- No error messages shown to the user
- Jobs appear to be queued but never process
- Application seems "stuck" waiting for generation to complete

### Root Cause
**The Laravel queue worker is not running.**

SnapDraft uses Laravel's queue system to process image generation jobs in the background. When you submit a CSV for batch generation:

1. The controller creates a `GenerateBatchImagesJob` batch
2. This batch dispatches multiple `GenerateSingleImageJob` jobs (one per CSV row)
3. Jobs are added to the `jobs` database table
4. **A queue worker must be running** to pick up and process these jobs
5. Without a running worker, jobs remain in the queue indefinitely

### Why This Keeps Happening
The queue worker is a separate PHP process that must be started manually and can stop for several reasons:

- **Terminal/PowerShell window closed** - Worker stops when terminal is closed
- **Manual termination** - Ctrl+C or process killed
- **System restart** - Worker doesn't auto-start on reboot
- **Code changes** - Some code deployments may require worker restart
- **Memory limits** - Worker may crash if it runs out of memory
- **Errors in jobs** - Fatal errors can crash the worker (though this is less common with proper error handling)

### Solution 1: Use `composer dev` (Recommended)

The development environment is configured to start the queue worker automatically:

```bash
composer dev
```

This command starts **all required services** concurrently:
- Laravel development server (port 8000)
- Queue worker (database connection, 3 tries, 180s timeout)
- Log viewer (Laravel Pail)
- Vite dev server (Hot Module Replacement for React)

**Why this is best:**
- Queue worker starts automatically every time
- All services run together in one terminal
- Consistent development environment
- Matches production behavior (where queue workers run as background services)

### Solution 2: Manual Queue Worker Start (Temporary Fix)

If you need to start the worker separately:

```bash
php artisan queue:work database --tries=3 --timeout=180
```

**Important:** This must be run in a **separate terminal window** that stays open while you work.

Parameters explained:
- `database` - Queue connection (configured in .env as `QUEUE_CONNECTION=database`)
- `--tries=3` - Retry failed jobs up to 3 times before marking as failed
- `--timeout=180` - Maximum 180 seconds (3 minutes) per job before timeout

### How to Check if Worker is Running

**PowerShell:**
```powershell
Get-Process | Where-Object {$_.ProcessName -like "*php*"} | Select-Object Id,ProcessName,CommandLine
```

Look for a process with `queue:work` in the CommandLine.

**Check pending jobs:**
```bash
php artisan tinker --execute="DB::table('jobs')->count();"
```

If this returns a number > 0 and the worker isn't running, jobs are stuck.

**Check recent batches:**
```bash
php artisan queue:batches
```

### How to Verify Images Generated

After submitting CSV generation, check if images were created:

```bash
php artisan tinker --execute="echo App\Models\Project::find(PROJECT_ID)->images()->count();"
```

Replace `PROJECT_ID` with your project ID.

Or check the storage directory:
```
storage/app/public/projects/{project_id}/images/
```

### Production Deployment Notes

For production environments, queue workers should run as:
- **Systemd service** (Linux)
- **Supervisor process** (Linux - recommended by Laravel)
- **Windows Service** (Windows)
- **Background job** in hosting platform (Laravel Forge, Vapor, etc.)

Never rely on manually started queue workers in production.

### Configuration Files

**Queue connection:** `.env`
```env
QUEUE_CONNECTION=database
```

**Composer script:** `composer.json`
```json
"scripts": {
    "dev": "concurrently -n server,queue,logs,vite \"php artisan serve\" \"php artisan queue:work database --tries=3 --timeout=180\" \"php artisan pail\" \"npm run dev\" --kill-others-on-fail"
}
```

**Database tables:**
- `jobs` - Pending queue jobs
- `job_batches` - Batch metadata (for CSV batch generation)
- `failed_jobs` - Jobs that failed after max retries

### Quick Diagnosis Checklist

When images aren't generating:

1. ✅ **Check worker is running** - `Get-Process` command above
2. ✅ **Check pending jobs** - Should be 0 if worker is processing
3. ✅ **Check failed jobs** - `php artisan queue:failed`
4. ✅ **Check logs** - `storage/logs/laravel.log` for errors
5. ✅ **Verify credits** - User must have sufficient credits
6. ✅ **Check API keys** - Google Gemini API key must be valid in `.env`

### Common Mistakes

❌ **Starting only Laravel server** - `php artisan serve` alone doesn't start worker
❌ **Closing worker terminal** - Worker stops immediately
❌ **Using `queue:listen`** - Less efficient than `queue:work`
❌ **Forgetting to restart worker** - After code changes affecting jobs
❌ **Wrong queue connection** - Must match `.env` setting

### Best Practices

✅ **Always use `composer dev`** during development
✅ **Monitor queue in production** with alerts for stuck jobs
✅ **Set up queue monitoring** (Laravel Horizon for Redis, or custom for database)
✅ **Log job failures** and set up error tracking (Sentry, Bugsnag, etc.)
✅ **Test queue locally** before deploying batch features
✅ **Document for team** so everyone knows worker is required

### Related Documentation

- [Laravel Queues Documentation](https://laravel.com/docs/11.x/queues)
- [Laravel Queue Workers](https://laravel.com/docs/11.x/queues#running-the-queue-worker)
- [Supervisor Configuration](https://laravel.com/docs/11.x/queues#supervisor-configuration)
- SnapDraft Copilot Instructions: `copilot-instructions.md`
