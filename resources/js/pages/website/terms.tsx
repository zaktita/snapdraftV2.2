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

export default function Terms() {
    return (
        <LegalShell title="Terms of Service">
            <h1>Terms of Service</h1>
            <p>Last updated: July 14, 2026</p>
            <p>
                By using SnapDraft you agree to these terms. If you do not agree, do not use the
                service.
            </p>
            <h2>The service</h2>
            <p>
                SnapDraft generates visual marketing assets from your brand references and CSV
                content. Output quality depends on your inputs and third-party AI systems.
            </p>
            <h2>Accounts &amp; plans</h2>
            <ul>
                <li>You must provide accurate registration information</li>
                <li>Credits and plan limits reset according to your subscription period</li>
                <li>You are responsible for activity under your account</li>
            </ul>
            <h2>Acceptable use</h2>
            <p>
                Do not use SnapDraft for illegal content, abuse of our APIs, attempts to bypass
                billing, or infringement of others&apos; rights.
            </p>
            <h2>Intellectual property</h2>
            <p>
                You retain rights to content you upload. Subject to third-party AI provider terms,
                you may use generated assets for your business. You grant us a limited license to
                process your content to provide the service.
            </p>
            <h2>Disclaimer</h2>
            <p>
                The service is provided &quot;as is&quot;. We do not guarantee uninterrupted
                availability or specific creative outcomes.
            </p>
            <h2>Contact</h2>
            <p>contact@snapdraft.com</p>
        </LegalShell>
    );
}
