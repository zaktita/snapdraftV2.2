# Marketing SEO — indexation policy & QA checklist

Use this before publishing or expanding programmatic marketing URLs (use cases, alternatives, glossary, templates, blog).

## Indexation policy

| URL class | Index? | Notes |
|-----------|--------|--------|
| Home, Features, Pricing, FAQ, About, Contact | Yes | Core product IA |
| Blog index + posts | Yes | Require unique title, excerpt, ≥ substantive body |
| Use cases hub + ICP pages | Yes | Must have unique pain/outcome/evidence/FAQ |
| Alternatives hub + pages + compare | Yes | Matrix cells must be factual; no boilerplate-only pages |
| Glossary hub + terms | Yes | Unique definition + how SnapDraft implements |
| Templates gallery + detail | Yes | Product UI / workflow evidence required |
| Privacy, Terms, Refund | Yes (low priority) | Legal |
| `/beta/apply` | **No** (`noindex,nofollow`) | Waitlist form; excluded from sitemaps |
| Thin duplicates / near-clones | **No** | `noindex` or do not publish |

## Sitemap segments

| Sitemap | Contents |
|---------|----------|
| `/sitemap.xml` | Index of child sitemaps |
| `/sitemap-core.xml` | Static marketing + legal hubs |
| `/sitemap-blog.xml` | Blog posts |
| `/sitemap-use-cases.xml` | Use-case detail pages |
| `/sitemap-alternatives.xml` | Alternatives + compare URLs |
| `/sitemap-glossary.xml` | Glossary terms |
| `/sitemap-templates.xml` | Template detail pages |

`robots.txt` is served dynamically at `/robots.txt` with an **absolute** `Sitemap:` URL from `APP_URL`.

## Pre-publish QA checklist

### Content uniqueness
- [ ] Title and H1 unique vs other URLs in the same category
- [ ] Intro is not a find-replace of another page (different pain, evidence, decision)
- [ ] Evidence block uses product facts, screenshots, or verified matrix cells—no invented stats
- [ ] FAQ answers (if any) differ per page where intent differs
- [ ] No shared closing formula across >30% of same-category pages (scan for repeated sentence tails)

### Technical
- [ ] Unique meta title + description
- [ ] Canonical is the public URL
- [ ] In sitemap segment (or intentionally omitted)
- [ ] Images have meaningful `alt` when they show product UI
- [ ] Internal links to ≥2 related hubs (features, use cases, glossary, blog, templates)
- [ ] JSON-LD valid where used (FAQPage, BlogPosting, BreadcrumbList)—**never** fake Review/AggregateRating

### Launch hygiene
- [ ] Publish in small batches (e.g. 3 use cases, then 5–10 alternatives)—not a dump
- [ ] After launch: check Google Search Console coverage + CWV on templates
- [ ] Prune or `noindex` URLs with no impressions after a defined window (e.g. 6 months) if thin

## N-gram / sameness scan (manual)

Before a batch goes live:

1. Export titles + first paragraph + closing paragraph for the category.
2. Flag any sentence appearing on more than one page unchanged.
3. Rewrite P0 template filler; vary P1 formulaic lines; thin out overused adjectives (~30%).

## Ownership

Marketing + engineering share responsibility: content owners write/ground facts; engineering keeps routes, sitemaps, schema, and `noindex` rules aligned with this policy.
