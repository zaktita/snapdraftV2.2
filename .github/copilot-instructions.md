# Copilot Instructions for SnapDraft

## Project Purpose

**SnapDraft** is an AI-powered visual content generator that creates brand-consistent images from CSV data. The core workflow:

1. **Brand Analysis**: Users upload 5-10 reference images → AI extracts brand style (colors, typography, composition)
2. **Content Input**: Users provide CSV with `title`, `description`, `format` columns → Each row = one visual to generate
3. **Batch Generation**: AI generates visuals matching brand style consistently across all outputs

**Key workflows**: CSV Wizard (3-step batch generation), Images Wizard, Text Wizard, Canvas Editor (fine-tuning).

## Architecture Overview

This is a **Laravel 12 + React 19 + Inertia.js** monorepo using TypeScript, Tailwind CSS 4, and shadcn/ui components. The architecture follows server-driven routing with client-side SPA behavior via Inertia.

- **Backend**: Laravel controllers return Inertia responses, not JSON APIs
- **Frontend**: React pages in `resources/js/pages/` mirror PHP routes, not standalone SPA
- **Type-safe routing**: Laravel Wayfinder auto-generates TypeScript route helpers from PHP routes
- **UI components**: Mix of shadcn/ui (in `resources/js/components/ui/`) and custom app components
- **Authentication**: Laravel Fortify handles auth with 2FA support
- **AI Integration**: Google Gemini (primary), OpenRouter (testing/alternatives) with rate limiting

## Critical Developer Workflows

### Starting the Development Environment
```bash
composer dev          # Starts all services: Laravel server, queue, logs, Vite (Hot Module Replacement)
composer dev:ssr      # For SSR mode with inertia:start-ssr
```

This single command runs 4 concurrent processes (via `concurrently` in composer.json). Never manually run `php artisan serve` or `npm run dev` separately unless debugging.

### Building for Production
```bash
npm run build         # Client-only build
npm run build:ssr     # Client + SSR build (requires Node.js server)
```

### Running Tests
```bash
composer test         # Clears config cache + runs PHPUnit
```

### Code Quality
```bash
npm run lint          # ESLint with auto-fix
npm run format        # Prettier with auto-organize imports + Tailwind class sorting
npm run types         # TypeScript type checking (no emit)
./vendor/bin/pint     # PHP formatting (Laravel Pint)
```

## Routing & Navigation

### Type-Safe Route Generation (Laravel Wayfinder)
Routes are auto-generated in `resources/js/routes/` and `resources/js/actions/` from PHP route definitions:

```tsx
// ✅ Correct - import generated route helper
import { dashboard, login } from '@/routes';

// In components
<Link href={dashboard().url}>Dashboard</Link>

// With Inertia router
router.post(`/projects/${id}/toggle-favorite`, data);
```

**Important**: Route helpers are generated at build time. After adding routes in `routes/*.php`, restart Vite to regenerate TypeScript files.

### Page Resolution
Inertia pages in `resources/js/pages/` use **case-sensitive directory structure** matching controller render calls:

```php
// Controller: Inertia::render('projects/index')
// Maps to: resources/js/pages/projects/index.tsx
```

## Component Patterns

### Layout Composition
Pages use layout wrappers, never direct layout imports:

```tsx
// ✅ Correct
export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {/* page content */}
        </AppLayout>
    );
}
```

Common layouts:
- `AppLayout` → `resources/js/layouts/app-layout.tsx` (wraps app/app-sidebar-layout.tsx)
- `AuthLayout` → For login/register pages
- `SettingsLayout` → Nested layout for settings pages

### shadcn/ui Components
UI components are in `resources/js/components/ui/`. Use the `cn()` utility for conditional classes:

```tsx
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

<Button className={cn('base-class', condition && 'conditional-class')} />
```

### Import Paths
Always use `@/` alias (resolves to `resources/js/`):

```tsx
import { dashboard } from '@/routes';
import { Button } from '@/components/ui/button';
import { type User } from '@/types';
```

## Project-Specific Conventions

### Form Handling with Inertia
Use Inertia's `useForm` hook for forms with Laravel validation:

```tsx
import { useForm } from '@inertiajs/react';

const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
});

const submit = (e: FormEvent) => {
    e.preventDefault();
    post('/profile', { preserveScroll: true });
};
```

Errors are automatically mapped to form fields via the `errors` object.

### TypeScript Types
Shared types are in `resources/js/types/index.d.ts`. Key interfaces:
- `SharedData`: Props passed to all Inertia pages (auth, user, etc.)
- `BreadcrumbItem`: For breadcrumb navigation
- `NavItem`, `NavGroup`: For sidebar navigation

### Styling Conventions
- **Tailwind 4** is configured via Vite plugin (no tailwind.config.js)
- Prettier sorts Tailwind classes automatically (via prettier-plugin-tailwindcss)
- Use `clsx` or `cn()` for conditional classes, never string interpolation
- Color system uses CSS variables (`--sidebar-border`, etc.) - see `resources/css/app.css`

### Authentication
- Fortify handles login/register/2FA (routes in `vendor/laravel/fortify`)
- Auth routes are generated in `resources/js/routes/` (e.g., `login()`, `register()`)
- Protect routes with `auth` middleware in `routes/web.php`
- Access current user via `usePage().props.auth.user`

## Integration Points

### Laravel Wayfinder
Auto-generates TypeScript route helpers from PHP routes. Key files:
- **Input**: `routes/*.php` (Laravel routes)
- **Output**: `resources/js/routes/` (route helpers) + `resources/js/actions/` (controller action helpers)
- **Config**: `vite.config.ts` (`formVariants: true` enables form action helpers)

Restart Vite after adding routes to regenerate helpers.

### Inertia.js Data Flow
1. Controller returns `Inertia::render('page-name', ['data' => $data])`
2. Inertia serializes data and navigates client-side
3. React page receives props: `({ data }: PageProps<{ data: MyType }>)`
4. On navigation, Inertia fetches new data via XHR without full page reload

### Vite + Laravel Integration
- `laravel-vite-plugin` handles hot reloading and asset bundling
- Entry points: `resources/css/app.css`, `resources/js/app.tsx`, `resources/js/ssr.tsx` (SSR)
- React Compiler plugin enabled (`babel-plugin-react-compiler`) for automatic memoization

## Testing

### Feature Test Pattern
Test Inertia responses with `assertInertia()` assertions (if installed) or check for successful responses:

```php
public function test_dashboard_loads_for_authenticated_users()
{
    $user = User::factory()->create();
    
    $this->actingAs($user)
         ->get(route('dashboard'))
         ->assertOk();
}
```

Use `RefreshDatabase` trait for database tests.

## Common Pitfalls

1. **Route changes not reflected**: Restart Vite after adding routes to regenerate Wayfinder helpers
2. **Import errors**: Always use `@/` alias, never relative paths outside component directory
3. **Layout confusion**: Use `AppLayout` wrapper, not `app/app-sidebar-layout.tsx` directly
4. **Tailwind classes not working**: Check Prettier formatting - it auto-organizes imports and may conflict with manual ordering
5. **SSR issues**: Ensure `npm run build:ssr` is run and `php artisan inertia:start-ssr` is running for SSR mode

## SnapDraft-Specific Patterns

### File Upload Handling
- **Reference images**: 5-10 brand representative images (JPG, PNG, WebP)
- **CSV structure**: Required columns: `title`, `description`, `format`
- **Product images**: Optional overlay images (up to 5)
- Use drag-and-drop interfaces with file validation

### Batch Processing
- **Rate limiting**: 2-second delays between AI generations to respect API limits
- **Progress tracking**: Real-time updates with detailed status indicators
- **Error handling**: Allow partial completion - some items can fail without stopping the batch
- **Chunked processing**: Handle large CSV files efficiently

### Project Organization
- **Main interfaces**: Projects Dashboard, Canvas Editor, Settings
- **Wizards**: CSV Wizard (3-step), Images Wizard, Text Wizard
- **Navigation**: Unified sidebar (except Canvas Editor), project switcher dropdown
- **Quick actions**: Cmd/Ctrl+K for search, quick "New Project" access

### AI Service Integration
- Primary: Google Gemini for brand analysis and generation
- Secondary: OpenRouter for model alternatives
- Implement graceful fallbacks and detailed error messages
- Log comprehensively for troubleshooting

### Output Conventions
- Naming: `{index}_{title}_{format}.png`
- Include generation metadata and style guide documentation
- Maintain version history for edits
- Organize by project for easy retrieval
