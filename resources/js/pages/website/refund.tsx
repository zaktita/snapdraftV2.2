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

export default function Refund() {
    return (
        <LegalShell title="Refund Policy">
            <h1>Refund Policy</h1>
            <p>Last updated: July 14, 2026</p>
            <p>
                Payments are processed by Lemon Squeezy as merchant of record. Subscriptions renew
                automatically until cancelled in the customer portal.
            </p>
            <h2>Cancellations</h2>
            <p>
                You can cancel anytime from the subscription portal. Access continues until the end
                of the paid period. Unused credits do not roll over unless stated on your plan.
            </p>
            <h2>Refunds</h2>
            <p>
                If you experience a billing error or a material service failure caused by us,
                contact contact@snapdraft.com within 14 days of the charge. Approved refunds are
                processed via Lemon Squeezy.
            </p>
            <h2>Invite / beta access</h2>
            <p>
                Beta invite credits are complimentary promotional access and are not refundable
                cash value.
            </p>
        </LegalShell>
    );
}
