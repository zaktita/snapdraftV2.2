import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import { register } from '@/routes';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';

const FAQ_SECTIONS: {
    heading: string;
    items: { q: string; a: string }[];
}[] = [
    {
        heading: 'Product',
        items: [
            {
                q: 'What exactly does SnapDraft do?',
                a: 'SnapDraft helps social media managers, freelancers, and agencies get on-brand visuals without waiting on designers. Upload brand references, drop in a content spreadsheet, and get a finished visual per row in minutes. Then tweak anything in the Canvas Editor before you publish or send to a client.',
            },
            {
                q: 'Who is SnapDraft for?',
                a: 'People who ship weekly content but hit a design bottleneck: in-house social managers, freelance creators handling multiple clients, and agencies producing calendars across brands. If you already plan in a spreadsheet and need visuals that stay on-brand, you are the audience.',
            },
            {
                q: 'How should my spreadsheet be set up?',
                a: 'Use title, description, and format columns. Each row becomes one generated visual matching the Brand DNA. Regenerate individual rows without redoing the whole batch. Useful when a client changes one caption mid-week.',
            },
            {
                q: 'What are brand references?',
                a: 'Brand references are 5–10 images that represent the visual identity - past posts, campaign assets, or guideline exports. SnapDraft extracts color, composition, and typography cues so every batch stays consistent for that client or brand.',
            },
            {
                q: 'Can I edit the generated images?',
                a: 'Yes. Canvas is built for last-mile tweaks: replace text, swap objects with AI, erase distractions, expand the canvas, remove backgrounds, and upscale - so you close feedback loops yourself instead of opening another design ticket.',
            },
            {
                q: 'What formats and sizes are supported?',
                a: 'Square, portrait, and landscape aspect ratios are generated natively. No cropping after the fact. Set the format per spreadsheet row and download everything in batch for feed, stories, or banners.',
            },
        ],
    },
    {
        heading: 'Billing & account',
        items: [
            {
                q: 'How do credits work?',
                a: 'One credit generates one visual. Regenerations and variations each use a credit. Credits reset at the start of every billing cycle, and you can top up or upgrade if you run out mid-calendar.',
            },
            {
                q: 'Can I cancel anytime?',
                a: 'Yes. Cancel from your billing settings and your plan stays active until the end of the current period. No emails, no phone calls.',
            },
            {
                q: 'Do you offer refunds?',
                a: 'See our refund policy for the details. If something is not working, contact us first. We usually fix it faster than a refund would take.',
            },
            {
                q: 'Who owns the generated images?',
                a: 'You do. Images generated from your references and spreadsheets are yours to use commercially for clients or your own brands.',
            },
        ],
    },
];

export default function FaqPage() {
    const [open, setOpen] = useState('0-0');

    return (
        <MarketingLayout
            title="FAQ - SnapDraft"
            description="How SnapDraft helps social media managers, freelancers, and agencies get on-brand visuals in minutes. Brand DNA, batch generation, Canvas tweaks, credits, and ownership."
        >
            <div className="sd-page-hero">
                <Reveal>
                    <div className="sd-sec-eyebrow">FAQ</div>
                    <h1>
                        Frequently asked <em>questions</em>
                    </h1>
                    <p>
                        From Brand DNA to billing - and how SnapDraft fits a
                        weekly publishing workflow. Can&apos;t find your answer?{' '}
                        <Link href="/contact">Contact us</Link>.
                    </p>
                </Reveal>
            </div>

            {FAQ_SECTIONS.map((section, sectionIdx) => (
                <section
                    key={section.heading}
                    className="sd-section"
                    style={{ paddingTop: sectionIdx === 0 ? 0 : 24 }}
                >
                    <Reveal>
                        <div className="sd-sec-eyebrow">{section.heading}</div>
                    </Reveal>
                    <div className="sd-faq-wrap" style={{ marginTop: 20 }}>
                        {section.items.map((item, idx) => {
                            const key = `${sectionIdx}-${idx}`;
                            const isOpen = open === key;
                            return (
                                <Reveal key={item.q} delay={idx * 40}>
                                    <div
                                        className={
                                            isOpen
                                                ? 'sd-faq-item open'
                                                : 'sd-faq-item'
                                        }
                                    >
                                        <button
                                            type="button"
                                            className="sd-faq-btn"
                                            onClick={() =>
                                                setOpen(isOpen ? '' : key)
                                            }
                                        >
                                            <span>{item.q}</span>
                                            <span className="sd-faq-ico">
                                                +
                                            </span>
                                        </button>
                                        <div className="sd-faq-ans">
                                            {item.a}
                                        </div>
                                    </div>
                                </Reveal>
                            );
                        })}
                    </div>
                </section>
            ))}

            <section className="sd-cta">
                <Reveal>
                    <h2>
                        Still stuck in a designer queue?{' '}
                        <em>Try a batch.</em>
                    </h2>
                    <p>
                        The fastest answer is generating your next calendar
                        yourself.
                    </p>
                    <div className="sd-cta-row">
                        <Link href={register().url} className="sd-btn-hero">
                            Generate your next batch
                            <ArrowRight size={16} />
                        </Link>
                        <Link
                            href="/contact"
                            className="sd-btn-hero-ghost sd-btn-hero-ghost-inv"
                        >
                            Ask a question
                        </Link>
                    </div>
                </Reveal>
            </section>
        </MarketingLayout>
    );
}
