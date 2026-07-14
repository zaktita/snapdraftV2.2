# Changelog

All notable changes to SnapDraft are documented here. Version tags follow semver (`vMAJOR.MINOR.PATCH`).

Rollback: redeploy the previous git tag (e.g. `git checkout v1.0.0` on the production branch).

## [1.0.0] — 2026-07-14

### Added
- Public plans: Starter ($29/100), Pro ($49/200), Business ($99/500) with yearly (2 months free)
- Invite-only Beta grandfather plan (1 month or until credits empty)
- Privacy, Terms, and Refund pages
- App version (`APP_VERSION`) shown in admin footer
- Local-only gating for Test Labs and other non-product surfaces
- Admin hardening: 2FA required outside local; password confirmation on destructive actions
- Atomic credit debit; Lemon webhook resolves plan by variant ID and is idempotent

### Security
- CSRF re-enabled for authenticated `/api/*` canvas routes
- Suspended users blocked globally
- Client cannot set privileged project `settings` keys (`wizard_type`, `skip_credits`, etc.)
- Credit skip for labs only when `APP_ENV=local` and `skip_credits` is set server-side

### Changed
- Subscription checkout/portal routes restored (no longer redirect to feedback)
