# SnapDraft Design System

This guide defines the shared visual system used by dashboard and marketing pages.

## Principles

- Keep visual hierarchy calm and readable.
- Use neutral surfaces first.
- Use brand accent sparingly for CTA, key metrics, and active states.
- Reuse primitives before writing new utility blocks.

## Tokens

Token source: `resources/css/app.css`.

### Core semantic groups

- `background`, `foreground`
- `surface-0`, `surface-1`, `surface-2`
- `card`, `card-foreground`
- `border`, `divider-subtle`, `divider-strong`
- `primary`, `primary-foreground`
- `brand`, `brand-foreground`
- `muted`, `muted-foreground`
- `success`, `warning`, `info`, `destructive`

### Theme behavior

- Every new token must have both light and dark mode values.
- Use semantic token classes (`bg-surface-1`, `text-muted-foreground`) instead of hardcoded color families.

## Typography

Typography utilities in `resources/css/app.css`:

- `ds-display-1`: Hero display headline
- `ds-heading-1`: Primary app heading
- `ds-heading-2`: Section heading
- `ds-body`: Body copy
- `ds-subtext`: Supporting text
- `ds-caption`: Captions and legal lines
- `ds-label`: Small labels

Wrappers are exposed in `resources/js/components/ds/typography.tsx`.

## Marketing Primitives

Reusable homepage/marketing primitives are in `resources/js/components/ds/marketing.tsx`.

- `Section`
- `SectionHeader`
- `FeatureCard`
- `StepCard`
- `MarketingFrame`
- `WireframePlaceholder`
- `StatPill`
- `PlanBadge`
- `FeatureChecklist`
- `DividerBand`

## Do and Don't

Do:

- Use `Button`, `Card`, and `Badge` primitives with semantic token classes.
- Build new sections by composing `Section` + `SectionHeader` + cards.
- Keep placeholders neutral and layout-correct until final assets are ready.

Don't:

- Hardcode `indigo-*`, `violet-*`, or `slate-*` in product-facing pages.
- Recreate existing card/button visual styles inline in page files.
- Use accent color as a full-page background except intentional CTA bands.

## Migration Map

Use this mapping when updating old UI:

- `bg-white` -> `bg-surface-0` or `bg-card`
- `bg-slate-50` -> `bg-surface-1`
- `border-slate-200` -> `border-divider-subtle` or `border-border`
- `text-slate-600` -> `text-muted-foreground`
- `text-slate-900` -> `text-foreground`
- `bg-indigo-600` -> `bg-brand`
- `text-indigo-600` -> `text-brand`

## Section Checklist

For every new homepage section:

1. Uses semantic tokens only.
2. Reuses ds primitives where possible.
3. Maintains spacing rhythm (`py-20`, `gap-4/5/6`).
4. Handles mobile and desktop cleanly.
5. Preserves contrast in both light and dark themes.
