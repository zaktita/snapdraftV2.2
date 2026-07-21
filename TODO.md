# SnapDraft — Open Launch TODO (Hundreds of Users)

> **Goal**: Leave closed beta; support open signup + paid plans at ~100–500 users  
> **Updated**: July 20, 2026  
> **Code readiness**: Strong — **ops still blocks go-live**

---

## Gate model (open)

```
Register (open) → choose plan OR optional invite → has.credits → product
<!-- Re-enable: verify email step — Features::emailVerification() + User MustVerifyEmail -->
```

---

## ✅ In repo for production

- [x] Billing/throttle/admin/plan-limit code fixes
- [x] `php artisan production:check` (must-have env + Redis/DB checks)
- [x] `php artisan admin:create` (prod admin — seeder skips production)
- [x] `deploy/supervisor-snapdraft-worker.conf`
- [x] `deploy/nginx-snapdraft.example.conf`
- [x] Ops runbook: `docs/PRODUCTION_DEPLOY_OPS.md`

---

## 🔴 Manual before production traffic

See detailed steps in `docs/PRODUCTION_DEPLOY_OPS.md`.

- [ ] Server + SSL + DNS
- [ ] `.env` secrets (DB, Redis, S3, mail, Gemini, OpenRouter)
- [ ] `migrate` + `PlanSeeder` + `production:check --skip-payments --probe-storage --strict`
- [ ] Supervisor workers + scheduler cron
- [ ] `admin:create` + 2FA
- [ ] GitHub deploy secrets (if using Actions)
- [ ] Backups / uptime (recommended)
- [ ] Lemon Squeezy (later)
- [ ] Full test suite (later)

---

## Reference

- Ops runbook: `docs/PRODUCTION_DEPLOY_OPS.md`
