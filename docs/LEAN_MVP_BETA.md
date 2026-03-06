# SnapDraft — Lean MVP Beta Plan

> **Goal**: Ship a working beta to 20 users (agencies/freelancers + social media managers) in 1–2 weeks.
> **Principle**: One hero flow, one plan, zero confusion. Expand after validation.

---

## 1. Product Definition (Beta)

**One-liner**: Upload your brand images + a CSV → get batch-generated, brand-consistent visuals in minutes.

**Hero flow (CSV Wizard)**:
1. User uploads 3–10 brand reference images
2. User uploads CSV (`title`, `description`, `format`)
3. AI analyzes brand DNA → generates all visuals in batch
4. User reviews, downloads (bulk or single), optionally edits in Canvas

**Target users**: Agencies managing multiple brands, social media managers producing daily content at volume.

---

## 2. Feature Scope — Keep vs. Cut vs. Defer

### ✅ KEEP (Beta Core)

| Feature | Status | Notes |
|---|---|---|
| **CSV Wizard** (3-step) | Built | Hero flow — polish & stabilize |
| **Dashboard** | Built | Stats, recent projects, credits display |
| **Projects CRUD** | Built | Create, view, edit, delete, favorites |
| **Project Gallery** | Built | View generated images, download, bulk download |
| **Canvas Editor (limited)** | Built | Keep: text replace, AI edit, erase. Cut the rest |
| **Auth (email/pass + 2FA)** | Built | Keep as-is |
| **Social Login (Google)** | Not built | **BUILD** — reduces signup friction |
| **Single Plan billing** | Partially built | Simplify Lemon Squeezy to 1 plan |
| **Admin (minimal)** | Built | Keep: users, credits, usage — cut the rest |
| **Image regenerate** | Built | Single image regenerate within a project |
| **Generate More (CSV)** | Built | Upload additional CSV to existing project |
| **Credits system** | Built | Monthly credits via subscription |
| **Marketing Homepage** | Built | `website/home` — polish for launch |

### ❌ CUT (Remove from beta routes/nav)

| Feature | Reason |
|---|---|
| **Images Wizard** | Overlaps with CSV Wizard (just CSV without text rows) |
| **Text Wizard** | Overlaps with CSV Wizard |
| **Brand Kit Wizard** | Overlaps — brand analysis happens inside CSV Wizard already |
| **Brand Analysis Wizard** | Lab/testing tool, not user-facing |
| **Simple Text Wizard** | Overlaps with Quick Generate |
| **Quick Generate** | Nice-to-have, not core. Defer to v1.1 |
| **Canvas: Outpainting** | Advanced — defer |
| **Canvas: Upscale** | Advanced — defer |
| **Canvas: Remove Background** | Advanced — defer |
| **Canvas: Resize Canvas** | Advanced — defer |
| **Canvas: Generate from Prompt** | Overlaps with AI Edit |
| **Admin: Analytics page** | Not needed for 20 users |
| **Admin: Plans management** | 1 plan = no management needed |
| **Admin: Subscriptions page** | Manage directly in Lemon Squeezy dashboard |
| **Admin: Projects page** | Not needed at scale of 20 |
| **Search** | Low priority for small project count |
| **Updates page** | No updates to show yet |
| **Startup page** | Not needed |
| **Settings: Appearance** | Cosmetic, not core |

### 🔜 DEFER (Post-beta v1.1+)

| Feature | Priority | Target |
|---|---|---|
| Quick Generate (single image) | High | v1.1 |
| Additional pricing tiers (Launch/Growth/Scale) | High | v1.1 (after validating pricing) |
| Canvas: Full editing suite | Medium | v1.2 |
| Team/multi-seat support | Medium | v1.2 |
| Version history | Medium | v1.2 |
| Search | Low | v1.1 |
| Social login (GitHub) | Low | v1.1 |
| Admin: Full analytics | Low | v1.2 |

---

## 3. Simplified Architecture (Beta)

```
┌─────────────────────────────────────────────┐
│                  FRONTEND                    │
├──────────┬──────────┬──────────┬────────────┤
│ Homepage │ Dashboard│ CSV      │ Canvas     │
│ (market) │ (hub)    │ Wizard   │ (limited)  │
│          │          │ (hero)   │            │
├──────────┴──────────┴──────────┴────────────┤
│              Project Gallery                 │
│         (view, download, manage)             │
├─────────────────────────────────────────────┤
│           Settings (profile/pass/2FA)        │
├─────────────────────────────────────────────┤
│           Admin (users/credits/usage)        │
└─────────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────┐
│                  BACKEND                     │
├─────────────────────────────────────────────┤
│ Auth (Fortify + 2FA + Google OAuth)          │
│ CSV Wizard Controller                        │
│ Project Controller                           │
│ Image Controller                             │
│ Canvas Controller (3 operations only)        │
│ Subscription Controller (1 plan)             │
│ Admin Controller (minimal)                   │
├─────────────────────────────────────────────┤
│ AI Services                                  │
│ ├── Google Gemini (primary)                  │
│ ├── Brand Reference Analyzer                 │
│ └── Prompt Generator Service                 │
├─────────────────────────────────────────────┤
│ Jobs                                         │
│ ├── AnalyzeBrandStyleJob                     │
│ ├── GenerateBatchImagesJob                   │
│ └── GenerateSingleImageJob                   │
├─────────────────────────────────────────────┤
│ Billing: Lemon Squeezy (1 plan)             │
└─────────────────────────────────────────────┘
```

---

## 4. Single Beta Plan

| | **SnapDraft Beta** |
|---|---|
| **Price** | $29/month (or $290/year — 2 months free) |
| **Credits** | 100 generations/month |
| **Projects** | 10 active projects |
| **CSV rows** | 25 per upload |
| **Brand refs** | 3–10 images per project |
| **Canvas editing** | Text replace, AI edit, erase |
| **Support** | Email |
| **Trial** | 7-day free trial, 10 credits |

> **Why one plan**: Eliminates decision paralysis. Validates willingness-to-pay at a single price point. Add tiers only after you see what users actually need more of (credits? projects? team seats?).

---

## 5. User Journey (Beta)

```
Landing Page → Sign Up (email or Google) → 7-day Trial (10 credits)
    │
    ▼
Dashboard (empty state with CTA)
    │
    ▼
"Create Project" → CSV Wizard
    │
    ├── Step 1: Name + Upload 3-10 brand reference images
    ├── Step 2: Upload CSV (title, description, format)
    └── Step 3: Review + Generate (brand analysis → batch generation)
    │
    ▼
Processing page (real-time progress)
    │
    ▼
Results page → Project Gallery
    │
    ├── Download all (ZIP)
    ├── Download single
    ├── Regenerate single image
    ├── Edit in Canvas (text replace / AI edit / erase)
    └── Generate More (upload another CSV)
    │
    ▼
Hits credit limit → Upgrade prompt → Lemon Squeezy checkout
```

---

## 6. Implementation Checklist (1–2 Weeks)

### Week 1 — Stabilize Core + Cut Bloat

- [ ] **Hide cut features from UI**
  - Remove sidebar links: Images Wizard, Text Wizard, Brand Kit, Brand Analysis, Simple Wizard, Quick Generate, Search, Updates
  - Remove or gate routes (keep code, just remove from navigation)
  - Keep `projects/create` page but only show CSV Wizard option
  
- [ ] **Simplify Canvas Editor**
  - Hide outpainting, upscale, remove-bg, resize, generate-from-prompt buttons
  - Keep: text replace (`generateWithMask`), AI edit (`aiEditImage`), erase (`erase`)
  - Ensure these 3 operations work reliably end-to-end

- [ ] **Simplify Billing**
  - Create single "SnapDraft Beta" plan in DB seeder
  - Hide plan selection page — go straight to checkout
  - Ensure Lemon Squeezy webhook handles: subscription created, payment success, cancellation
  - Test credit deduction + refund on failed generation
  - Add trial: 7 days, 10 credits

- [ ] **Simplify Admin**
  - Keep only: dashboard, users list (with suspend/credits adjust), usage
  - Hide: analytics, plans, subscriptions, projects admin pages from nav

- [ ] **CSV Wizard hardening**
  - Test with 5, 10, 25 row CSVs
  - Test with various image formats (JPG, PNG, WebP)
  - Test brand analysis failure graceful degradation
  - Test queue job retries and failure handling
  - Validate progress polling works reliably
  - Test result page: all images display, download works

- [ ] **Polish Dashboard**
  - Clean empty state (first-time user CTA → "Create your first project")
  - Ensure stats display correctly for new users (0/0 shouldn't break)
  - Credits remaining indicator prominent

### Week 2 — Auth, Polish, Deploy

- [ ] **Add Google OAuth**
  - Install Laravel Socialite
  - Configure Google OAuth credentials
  - Add "Continue with Google" button on login/register pages
  - Handle account linking (existing email → merge)

- [ ] **Polish Marketing Homepage**
  - Clear value prop: "Brand-consistent visuals from your CSV, in minutes"
  - Show the 3-step process visually
  - Pricing section (single plan)
  - CTA → Sign up / Start free trial
  - Social proof section (placeholder for beta testimonials)

- [ ] **Error handling & edge cases**
  - AI service down → friendly error message + retry option
  - File upload failures → clear validation messages
  - Credit exhaustion → clear upgrade prompt (not a dead end)
  - CSV parsing errors → show which rows failed and why
  - Rate limiting → show "please wait" not a crash

- [ ] **Settings cleanup**
  - Keep: Profile, Password, 2FA
  - Remove: Appearance tab from settings nav

- [ ] **Production readiness**
  - Queue worker setup (Redis/database — ensure jobs survive restarts)
  - File storage (S3 or equivalent — not local disk)
  - Email setup (Postmark/Resend for transactional: welcome, verification, invoice)
  - SSL + domain configured
  - `.env.production` with all API keys
  - Error tracking (Sentry or similar)
  - Basic uptime monitoring

- [ ] **Beta launch prep**
  - Create 20 invite codes or waitlist mechanism
  - Onboarding email sequence (welcome → quick start guide → check-in)
  - Feedback mechanism (simple in-app form or email link)
  - Seed sample CSV + reference images for demo project

---

## 7. Database — What Stays

| Model | Status | Notes |
|---|---|---|
| `User` | ✅ Keep | Core |
| `Project` | ✅ Keep | Core |
| `Image` | ✅ Keep | Core |
| `BrandReference` | ✅ Keep | For brand DNA analysis |
| `GenerationHistory` | ✅ Keep | Tracking + debugging |
| `CsvWizardSession` | ✅ Keep | Processing state for CSV wizard |
| `Plan` | ✅ Keep | Single plan record |
| `Subscription` | ✅ Keep | User↔Plan billing state |
| `SubscriptionUsage` | ✅ Keep | Credit tracking |
| `Invoice` | ✅ Keep | Billing records |
| `Transaction` | ✅ Keep | Payment records |
| `QuickGenerateSession` | ⏸️ Defer | Not used in beta |

---

## 8. Routes — Beta Surface

### Public
```
GET  /                    → Marketing homepage
GET  /startup             → [REMOVE]
```

### Auth
```
GET  /login               → Login (email + Google OAuth)
GET  /register            → Register (email + Google OAuth)
POST /login               → Authenticate
POST /register            → Create account
POST /logout              → Logout
```

### App (auth required)
```
GET  /dashboard                                    → Dashboard
GET  /projects                                     → Projects list
GET  /projects/create                              → [Simplified] → goes to CSV Wizard directly
GET  /projects/create/csv                          → CSV Wizard form
POST /projects/wizards/csv                         → Submit CSV Wizard
GET  /projects/wizards/csv/sessions/{s}            → Processing page
GET  /projects/wizards/csv/sessions/{s}/result     → Results page
GET  /projects/wizards/csv/sessions/{s}/status     → Polling endpoint
GET  /projects/{id}                                → Project gallery
PUT  /projects/{id}                                → Update project
DELETE /projects/{id}                              → Delete project
POST /projects/{id}/toggle-favorite                → Toggle favorite
POST /projects/{id}/generate                       → [Keep] Generate more
POST /projects/{projectId}/csv                     → Upload more CSV rows
GET  /projects/{id}/generation-progress            → Poll generation progress

PUT  /projects/{pId}/images/{iId}                  → Update image metadata
DELETE /projects/{pId}/images/{iId}                → Delete image
POST /projects/{pId}/images/{iId}/regenerate       → Regenerate single image
POST /projects/{pId}/images/bulk-delete            → Bulk delete
POST /projects/{pId}/images/bulk-download          → Bulk download (ZIP)
POST /projects/{pId}/images/update-order           → Reorder images
POST /projects/{pId}/images/{iId}/toggle-favorite  → Toggle image favorite

GET  /canvas-editor                                → Canvas Editor (limited)
POST /projects/{pId}/images/{iId}/save-edit        → Save canvas edit
POST /api/generate-with-mask                       → Text replace (keep)
POST /api/ai-edit-image                            → AI edit (keep)
POST /api/erase-image                              → Erase (keep)
```

### Billing
```
GET  /subscription/plans     → [Simplified] Single plan + checkout
GET  /subscription/portal    → Lemon Squeezy customer portal
POST /subscription/upgrade   → [Not needed for 1 plan, but keep for trial→paid]
```

### Settings
```
GET  /settings/profile       → Edit profile
PUT  /settings/profile       → Update profile
GET  /settings/password      → Change password  
PUT  /settings/password      → Update password
GET  /settings/two-factor    → 2FA setup
```

### Admin
```
GET  /admin                  → Admin dashboard
GET  /admin/users            → User management
PUT  /admin/users/{id}       → Update user
POST /admin/users/{id}/suspend    → Suspend
POST /admin/users/{id}/reactivate → Reactivate
GET  /admin/credits          → Credits overview
POST /admin/credits/{id}/adjust   → Adjust user credits
GET  /admin/usage            → Usage stats
```

### Webhooks
```
POST /webhooks/lemonsqueezy  → Lemon Squeezy webhook handler
```

---

## 9. Files to Modify (Not Delete)

> **Rule**: Don't delete any code. Just hide features from UI and optionally comment out routes.

### Sidebar Navigation
- `resources/js/layouts/` → Remove nav items for: Images Wizard, Text Wizard, Brand Kit, Brand Analysis, Simple Wizard, Quick Generate, Search, Updates

### Project Create Page
- `resources/js/pages/projects/create.tsx` → Show only CSV Wizard card, hide others

### Canvas Editor
- Hide buttons for: Outpaint, Upscale, Remove BG, Resize, Generate from Prompt
- Keep buttons for: Text Replace, AI Edit, Erase

### Settings Navigation
- Remove Appearance tab from settings nav

### Admin Navigation
- Remove: Analytics, Plans, Subscriptions, Projects tabs

---

## 10. Success Metrics (Beta)

| Metric | Target | How to Measure |
|---|---|---|
| **Signups** | 20 beta users | DB count |
| **Activation** | 60% complete first CSV Wizard | Generation history |
| **Retention** | 40% return in week 2 | Login frequency |
| **Generation quality** | <20% regeneration rate | Regenerate count / total |
| **Conversion** | 25% trial → paid | Subscription records |
| **NPS** | >40 | Survey |
| **Churn** | <10% monthly | Cancellation rate |

---

## 11. Risk Mitigation

| Risk | Impact | Mitigation |
|---|---|---|
| Gemini API rate limits | Batch generation fails | 2-second delays already built, add exponential backoff |
| Gemini API cost spike | Burn money on 20 users | 100 credits/month cap, monitor daily API spend |
| Brand analysis quality inconsistent | Users get bad results | Graceful fallback already built (skip analysis, use raw refs) |
| CSV format confusion | Users upload wrong format | Add sample CSV download + clear column requirements |
| Queue jobs stuck | Images never generate | Add job timeout (5 min), retry 3x, admin alert on failure |
| Low signup rate | No users to validate | Personal outreach to 20 target users, not cold launch |

---

## 12. Post-Beta Roadmap

```
Beta (now)          v1.1 (month 2)         v1.2 (month 3-4)
─────────────       ─────────────          ─────────────
CSV Wizard          + Quick Generate       + Full Canvas suite
1 plan ($29)        + 3 pricing tiers      + Team/multi-seat
Basic Canvas        + Search               + Version history
Google OAuth        + GitHub OAuth         + API access
Minimal Admin       + Full Analytics       + White-label option
Email support       + In-app chat          + Priority support
                    + Template library     + Custom brand presets
```

---

## Summary

**Cut 70% of what's built. Ship the 30% that matters.**

The CSV Wizard IS SnapDraft. Everything else is a nice-to-have that dilutes focus and introduces bugs. Get 20 users through the hero flow, measure what breaks and what they ask for, then build that.

**Next action**: Start hiding features from the UI (sidebar nav, project create page, canvas buttons, admin nav, settings nav). Don't delete code — just remove the entry points.
