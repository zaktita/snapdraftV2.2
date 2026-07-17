import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import type { ReactNode } from 'react';

export function LegalShell({
    title,
    lastUpdated,
    children,
}: {
    title: string;
    lastUpdated: string;
    children: ReactNode;
}) {
    return (
        <MarketingLayout title={`${title} - SnapDraft`}>
            <div className="sd-page-hero">
                <Reveal>
                    <div className="sd-sec-eyebrow">Legal</div>
                    <h1>{title}</h1>
                </Reveal>
            </div>
            <p className="sd-legal-updated">Last updated: {lastUpdated}</p>
            <main className="sd-legal">
                <div className="sd-prose">{children}</div>
            </main>
        </MarketingLayout>
    );
}
