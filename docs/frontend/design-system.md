# SnapDraft Design System

Shared visual system for **marketing**, **auth**, and the **logged-in app**. One palette on `:root`; marketing `.sd-*` classes and shadcn semantic tokens both consume it.

## Principles

- Canvas-first: pages sit on light grey (`--sd-canvas` / `bg-canvas`), content on white cards.
- Orange is the primary CTA / active accent (`#ff5806`). Use blue, yellow, and pink as supporting accents only.
- Display type is **Archivo Black**; body is **Inter**.
- Prefer pill radii for CTAs and badges; larger rounded corners (`rounded-2xl`) for cards/panels.
- Reuse primitives (`Button`, `Card`, `Badge`, `ds-*`) before inventing new utility blocks.

## Source of truth

Token source: `resources/css/app.css` (`:root` + `.dark`).

| Layer | What it is |
|-------|------------|
| `--sd-*` | Brand palette (hex). Marketing CSS and semantic tokens both reference these. |
| Semantic (`--primary`, `--background`, …) | App/shadcn layer mapped from `--sd-*`. |
| `@theme inline` | Bridges semantic vars → Tailwind (`bg-primary`, `bg-canvas`, `font-display`, …). |
| `.sd-home` / `.sd-auth` | Layout scopes for marketing/auth; inherit tokens from `:root`. |

## Brand palette

| Token | Value | Use |
|-------|-------|-----|
| `--sd-or` | `#ff5806` | Primary CTA, brand, active |
| `--sd-or2` / `--sd-or-soft` | `#e64e05` | Hover / pressed (darker shade of the same orange) |
| `--sd-blue` | `#5B6CFF` | Accent panels / badges |
| `--sd-yellow` | `#F5E800` | Section badges, highlights |
| `--sd-yellow-soft` | `#F1F58F` | Soft yellow panels |
| `--sd-pink` | `#FFD6E0` | Accent panels / badges |
| `--sd-canvas` | `#EBEBEB` | Page background |
| `--sd-ink` / `--sd-text` | `#0A0A0A` / `#1A1A1A` | Headings / body |
| `--sd-surface` | `#FFFFFF` | Cards, sidebar, inputs |
| `--sd-border` | `#D6D6D6` | Borders |

## Semantic → Tailwind map

| Semantic | Tailwind | Notes |
|----------|----------|-------|
| `--background` / `--canvas` | `bg-background`, `bg-canvas` | Page canvas |
| `--primary` / `--brand` | `bg-primary`, `bg-brand` | Orange CTA |
| `--card` / `--surface-0` | `bg-card`, `bg-surface-0` | White surfaces |
| `--surface-1` | `bg-surface-1` | Muted fill |
| `--muted-foreground` | `text-muted-foreground` | Secondary copy |
| `--accent-blue/yellow/pink` | `bg-accent-blue`, etc. | Marketing accents |
| `--font-display` | `font-display` | Archivo Black |
| `--font-sans` | `font-sans` | Inter |
| `--radius-pill` | `rounded-full` / `ds-pill` | CTAs, badges |

Dark mode keeps the same orange brand; surfaces invert to near-black.

## Typography

Utilities in `@layer components` + wrappers in `resources/js/components/ds/typography.tsx`:

| Class | Role |
|-------|------|
| `ds-display-1` | Hero / marketing display |
| `ds-heading-1` | Page title |
| `ds-heading-2` | Section title |
| `ds-body` | Body copy |
| `ds-subtext` | Supporting text |
| `ds-caption` / `ds-label` | Meta / labels |
| `ds-number-lg` | Large metrics |

Display and headings use `font-display` (Archivo Black, weight 400).

## App primitives

| Primitive | Path | Marketing alignment |
|-----------|------|---------------------|
| Button | `components/ui/button.tsx` | Pill (`rounded-full`), orange default / `brand` |
| Card | `components/ui/card.tsx` | `rounded-2xl`, soft border |
| Badge | `components/ui/badge.tsx` | Pill; variants `yellow`, `pink`, `blue` |
| Input | `components/ui/input.tsx` | `rounded-xl`, white fill, orange focus ring |
| Sidebar | `components/ui/sidebar.tsx` | White surface, orange active via `--sidebar-primary` |

Helpers: `ds-card-minimal`, `ds-card-plain`, `ds-app-shell`, `ds-pill`, `ds-badge-yellow`.

## Marketing primitives

Hand-authored `.sd-*` sections in `app.css` + optional React helpers in `resources/js/components/ds/marketing.tsx`.

Marketing may keep bespoke layout CSS, but **must not redefine brand hex values** — use `--sd-*` from `:root`.

## Do and Don't

Do:

- Use semantic / brand classes (`bg-primary`, `bg-canvas`, `text-muted-foreground`, `font-display`).
- Prefer `Button` / `Card` / `Badge` over one-off styles.
- Keep accents (blue / yellow / pink) for highlights, not every surface.

Don't:

- Hardcode `indigo-*`, `violet-*`, or old slate stacks in product UI.
- Redefine `--sd-or` (or other brand tokens) inside page CSS.
- Use orange as a full-page background except intentional CTA bands.

## Migration map (app pages)

| Old | New |
|-----|-----|
| `bg-white` | `bg-card` / `bg-surface-0` |
| `bg-slate-50` / page grey | `bg-canvas` / `bg-background` |
| `border-slate-200` | `border-border` |
| `text-slate-600` | `text-muted-foreground` |
| `text-slate-900` | `text-foreground` |
| `bg-indigo-600` / black primary | `bg-primary` / `bg-brand` |
| `rounded-md` CTAs | `rounded-full` (default `Button`) |
| System font headings | `font-display` / `ds-heading-*` |

## Updating an app screen checklist

1. Page root sits on `bg-background` (canvas) — already true via app shell.
2. Panels use `Card` / `bg-card` with `rounded-2xl`.
3. Primary actions use default `Button` (orange pill).
4. Section titles use `ds-heading-*` or `font-display`.
5. Badges use pill variants; yellow for “section” labels when matching marketing.
6. No hardcoded marketing hex outside `--sd-*` / semantic tokens.
7. Spot-check light + dark.

## App page shell

Use `AppPage` + `AppPageHeader` from `resources/js/components/app-page.tsx` for new logged-in screens:

```tsx
import { AppPage, AppPageHeader } from '@/components/app-page';
import { Button } from '@/components/ui/button';

<AppPage>
  <AppPageHeader
    title="Projects"
    description="Manage and organize your projects"
    actions={<Button>New Project</Button>}
  />
  {/* Card content */}
</AppPage>
```

Existing pages may inline the same pattern (`font-display` title + `text-muted-foreground` + `rounded-2xl` cards).

## Mental model

```
:root (--sd-* palette)
   ├─ semantic tokens (--primary, --background, …)
   │     └─ @theme → Tailwind (bg-primary, bg-canvas, font-display)
   │           └─ shadcn UI + app layouts
   └─ .sd-home / .sd-auth
         └─ marketing / auth CSS components
```
