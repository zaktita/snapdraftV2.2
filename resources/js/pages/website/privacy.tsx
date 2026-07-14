import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

function LegalShell({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="min-h-screen bg-neutral-50 text-neutral-900">
            <Head title={title} />
            <header className="border-b border-neutral-200 bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                    <Link href="/" className="font-semibold tracking-tight">
                        SnapDraft
                    </Link>
                    <nav className="flex gap-4 text-sm text-neutral-600">
                        <Link href="/privacy">Privacy</Link>
                        <Link href="/terms">Terms</Link>
                        <Link href="/refund">Refunds</Link>
                    </nav>
                </div>
            </header>
            <main className="prose prose-neutral mx-auto max-w-3xl px-6 py-12">{children}</main>
        </div>
    );
}

export default function Privacy() {
    return (
        <LegalShell title="Privacy Policy">
            <h1>Privacy Policy</h1>
            <p>Last updated: July 14, 2026</p>
            <p>
                SnapDraft (&quot;we&quot;, &quot;us&quot;) provides AI-assisted visual generation for
                brands. This policy explains what we collect and how we use it.
            </p>
            <h2>What we collect</h2>
            <ul>
                <li>Account details (name, email, authentication data)</li>
                <li>Billing details processed by Lemon Squeezy (we do not store full card numbers)</li>
                <li>Project content you upload (brand references, CSV captions, generated images)</li>
                <li>Usage and product analytics to improve the product</li>
            </ul>
            <h2>How we use data</h2>
            <ul>
                <li>To provide generation, editing, billing, and support</li>
                <li>To send transactional email (password reset, generation failures)</li>
                <li>To operate AI providers necessary to generate your outputs</li>
            </ul>
            <h2>AI providers</h2>
            <p>
                Captions, brand references, and prompts are sent to third-party AI providers solely
                to deliver the service.
            </p>
            <h2>Retention &amp; deletion</h2>
            <p>
                You may delete your account from Settings. Project and image records are removed
                with your account; residual storage objects are cleaned by scheduled jobs.
            </p>
            <h2>Contact</h2>
            <p>Questions: contact@snapdraft.com</p>
        </LegalShell>
    );
}
