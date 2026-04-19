import { useInView } from '@/hooks/use-in-view';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';

/* ── Scroll-reveal wrapper ── */

function Reveal({
    children,
    className,
    delay = 0,
}: {
    children: React.ReactNode;
    className?: string;
    delay?: number;
}) {
    const { ref, inView } = useInView<HTMLDivElement>();
    return (
        <div
            ref={ref}
            className={cn('reveal', inView && 'revealed', className)}
            style={delay ? { transitionDelay: `${delay}ms` } : undefined}
        >
            {children}
        </div>
    );
}

/* ── CSRF helper ── */
function csrfToken(): string {
    if (typeof document === 'undefined') return '';
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

/* ── Page ── */

export default function HomePage() {
    const [scrolled, setScrolled] = useState(false);
    const [openFaq, setOpenFaq] = useState(0);
    const [showWaitlistCard, setShowWaitlistCard] = useState(false);
    const [showInviteRequiredNotice, setShowInviteRequiredNotice] =
        useState(false);

    // Invite code form
    const [inviteCode, setInviteCode] = useState('');
    const [inviteError, setInviteError] = useState('');
    const [inviteLoading, setInviteLoading] = useState(false);

    // Waitlist form
    const [waitlistEmail, setWaitlistEmail] = useState('');
    const [waitlistDone, setWaitlistDone] = useState(false);
    const [waitlistError, setWaitlistError] = useState('');
    const [waitlistLoading, setWaitlistLoading] = useState(false);

    const inviteRef = useRef<HTMLInputElement>(null);
    const waitlistRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        const params = new URLSearchParams(window.location.search);
        if (params.get('invite') === 'required') {
            setShowInviteRequiredNotice(true);
        }
    }, []);

    function openWaitlistCard() {
        setShowWaitlistCard(true);

        // Let the card render before trying to focus the email input.
        requestAnimationFrame(() => {
            waitlistRef.current?.focus();
            document
                .getElementById('hero-forms')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    async function handleInvite(e: FormEvent) {
        e.preventDefault();
        const code = inviteCode.trim().toUpperCase();
        if (!code) return;
        setInviteError('');
        setInviteLoading(true);
        try {
            const res = await fetch(
                `/invite/validate?code=${encodeURIComponent(code)}`,
            );
            const data = await res.json();
            if (data.valid) {
                router.visit(
                    `${register().url}?invite=${encodeURIComponent(code)}`,
                );
            } else {
                setInviteError(
                    data.message || 'Invalid or expired invite code.',
                );
            }
        } catch {
            setInviteError('Something went wrong. Please try again.');
        } finally {
            setInviteLoading(false);
        }
    }

    async function handleWaitlist(e: FormEvent) {
        e.preventDefault();
        const email = waitlistEmail.trim();
        if (!email) return;
        setWaitlistError('');
        setWaitlistLoading(true);
        try {
            const res = await fetch('/waitlist', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ email }),
            });
            if (res.ok) {
                setWaitlistDone(true);
            } else {
                const data = await res.json();
                setWaitlistError(
                    data.errors?.email?.[0] || 'Please enter a valid email.',
                );
            }
        } catch {
            setWaitlistError('Something went wrong. Please try again.');
        } finally {
            setWaitlistLoading(false);
        }
    }

    const steps = [
        {
            num: '01',
            iconSrc: '/images/landing/upload.png',
            title: 'Upload brand references',
            desc: 'Add 5-10 images that represent your style. We extract color, composition, and typography cues.',
        },
        {
            num: '02',
            iconSrc: '/images/landing/csv.png',
            title: 'Drop in your spreadsheet',
            desc: 'Use title, description, and format columns. Each row becomes one generated visual.',
        },
        {
            num: '03',
            iconSrc: '/images/landing/generate.png',
            title: 'Generate & refine',
            desc: 'Download consistent images in batch, then fine-tune any result in the Canvas Editor.',
        },
    ];

    const betaPerks = [
        {
            iconSrc: '/images/landing/full product.png',
            title: 'Full product access',
            desc: 'Every feature unlocked — spreadsheet batch generation, brand analysis, Canvas Editor, and more.',
        },
        {
            iconSrc: '/images/landing/access.png',
            title: 'Direct founder access',
            desc: 'Report bugs, suggest features, and chat directly with the person building it.',
        },
        {
            iconSrc: '/images/landing/discount.png',
            title: 'Keep your discount',
            desc: 'Beta testers lock in a special rate when SnapDraft launches publicly.',
        },
    ];

    return (
        <div className="sd-home">
            <Head title="SnapDraft — Closed Beta · 20 spots" />

            {/* ── Nav ── */}
            <header
                className={cn(
                    'sd-nav-wrap',
                    scrolled && 'sd-nav-wrap-scrolled',
                )}
            >
                <nav className="sd-nav">
                    <Link href="/" className="sd-logo">
                        <img
                            src="/SnapdraftLogoBlack.svg"
                            alt="SnapDraft"
                            className="sd-logo-image"
                        />
                    </Link>
                    <ul className="sd-nav-links">
                        <li>
                            <a href="#how">How it works</a>
                        </li>
                        <li>
                            <a href="#beta">Beta perks</a>
                        </li>
                        <li>
                            <a href="#faq">FAQ</a>
                        </li>
                    </ul>
                    <div className="sd-nav-end">
                        <Link href={login().url} className="sd-btn-sm-ghost">
                            Sign in
                        </Link>
                        <button
                            type="button"
                            className="sd-btn-sm"
                            onClick={openWaitlistCard}
                        >
                            Request access
                            <ArrowRight size={14} />
                        </button>
                    </div>
                </nav>
            </header>

            {/* ── Hero ── */}
            <section className="sd-hero-shell">
                <div className="sd-hero-glow" aria-hidden="true" />
                <div className="sd-hero">
                    <div className="sd-hero-grid">
                        <div className="sd-hero-copy">
                            <Reveal>
                                <div className="sd-hero-badge">
                                    <span className="sd-badge-dot" />
                                    Closed Beta : 20 spots only
                                </div>
                            </Reveal>
                            <Reveal delay={60}>
                                <h1>
                                    Help us build
                                    <br />
                                    the future of
                                    <br />
                                    <em>brand visuals</em>
                                </h1>
                            </Reveal>
                            <Reveal delay={120}>
                                <p className="sd-hero-desc">
                                    SnapDraft turns a spreadsheet and a few
                                    reference images into a full batch of
                                    on-brand visuals. We need 20 testers to
                                    push it to its limits before launch.
                                </p>
                                {showInviteRequiredNotice && (
                                    <p className="sd-invite-required-msg">
                                        A valid invite code is required to
                                        create an account.
                                    </p>
                                )}
                            </Reveal>

                            <Reveal delay={180}>
                                <div className="sd-hero-forms" id="hero-forms">
                                    <form
                                        onSubmit={handleInvite}
                                        className="sd-hero-inline-form"
                                    >
                                        <input
                                            ref={inviteRef}
                                            type="text"
                                            placeholder="Have an invite code? XXXX-XXXX"
                                            value={inviteCode}
                                            onChange={(e) =>
                                                setInviteCode(e.target.value)
                                            }
                                            autoComplete="off"
                                            spellCheck={false}
                                            className="sd-code-input"
                                        />
                                        <button
                                            type="submit"
                                            disabled={inviteLoading}
                                        >
                                            {inviteLoading
                                                ? 'Checking…'
                                                : 'Redeem'}
                                        </button>
                                    </form>
                                    {inviteError && (
                                        <p className="sd-hero-inline-error">
                                            {inviteError}
                                        </p>
                                    )}

                                    {showWaitlistCard && (
                                        <div className="sd-waitlist-inline-wrap">
                                            {waitlistDone ? (
                                                <p className="sd-hero-card-msg sd-hero-card-msg--success">
                                                    <CheckCircle2 size={16} />
                                                    We&apos;ve got your request
                                                    — we&apos;ll email you when a
                                                    spot opens.
                                                </p>
                                            ) : (
                                                <>
                                                    <form
                                                        onSubmit={
                                                            handleWaitlist
                                                        }
                                                        className="sd-hero-inline-form sd-hero-inline-form-waitlist"
                                                    >
                                                        <input
                                                            ref={waitlistRef}
                                                            type="email"
                                                            placeholder="Your email to request access"
                                                            value={
                                                                waitlistEmail
                                                            }
                                                            onChange={(e) =>
                                                                setWaitlistEmail(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                        <button
                                                            type="submit"
                                                            disabled={
                                                                waitlistLoading
                                                            }
                                                        >
                                                            {waitlistLoading
                                                                ? 'Sending…'
                                                                : 'Request access'}
                                                        </button>
                                                    </form>
                                                    {waitlistError && (
                                                        <p className="sd-hero-inline-error">
                                                            {waitlistError}
                                                        </p>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </Reveal>

                            <Reveal delay={240}>
                                <div className="sd-hero-trust">
                                    <span>Free during beta</span>
                                    <span className="sd-hero-trust-dot" />
                                    <span>No credit card</span>
                                    <span className="sd-hero-trust-dot" />
                                    <span>Direct line to the founder</span>
                                </div>
                            </Reveal>
                        </div>

                        <Reveal className="sd-hero-image-wrap" delay={120}>
                            <img
                                src="/images/landing/hero_image.png"
                                alt="SnapDraft visual generation preview"
                                className="sd-hero-image"
                            />
                        </Reveal>
                    </div>
                </div>
            </section>

            {/* ── Stats ── */}
            <section className="sd-stats-strip">
                <div className="sd-stats-inner">
                    {[
                        { val: '20', label: 'Beta spots' },
                        { val: 'Free', label: 'No cost during beta' },
                        { val: '~30d', label: 'Before public launch' },
                        { val: 'You', label: 'Shape the product' },
                    ].map((s) => (
                        <div className="sd-stat-cell" key={s.label}>
                            <div className="sd-stat-num">{s.val}</div>
                            <div className="sd-stat-lbl">{s.label}</div>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── How it works ── */}
            <section className="sd-section" id="how">
                <Reveal>
                    <div className="sd-sec-eyebrow">How it works</div>
                    <h2 className="sd-sec-title">
                        From spreadsheet to visuals{' '}
                        <strong>in 3 steps</strong>
                    </h2>
                    <p className="sd-sec-sub">
                        No design skills required. Just brand references and a
                        spreadsheet.
                    </p>
                </Reveal>
                <div className="sd-steps-grid">
                    {steps.map((step, i) => (
                        <Reveal key={step.num} delay={i * 100}>
                            <div className="sd-step-card">
                                <div className="sd-step-head">
                                    <span className="sd-step-ico">
                                        <img
                                            src={step.iconSrc}
                                            alt={step.title}
                                            className="sd-card-icon"
                                        />
                                    </span>
                                    <span className="sd-step-num">
                                        {step.num}
                                    </span>
                                </div>
                                <h3>{step.title}</h3>
                                <p>{step.desc}</p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </section>

            {/* ── Beta perks ── */}
            <section className="sd-section-band" id="beta">
                <div className="sd-section-band-inner">
                    <Reveal>
                        <div className="sd-sec-eyebrow">Beta perks</div>
                        <h2 className="sd-sec-title">
                            What you get as a <em>beta tester</em>
                        </h2>
                    </Reveal>
                    <div className="sd-feature-grid">
                        {betaPerks.map((perk, i) => (
                            <Reveal key={perk.title} delay={i * 100}>
                                <div className="sd-feature-card">
                                    <span className="sd-feature-ico">
                                        <img
                                            src={perk.iconSrc}
                                            alt={perk.title}
                                            className="sd-card-icon"
                                        />
                                    </span>
                                    <h3>{perk.title}</h3>
                                    <p>{perk.desc}</p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── FAQ ── */}
            <section className="sd-section" id="faq">
                <Reveal>
                    <div className="sd-sec-eyebrow">FAQ</div>
                    <h2 className="sd-sec-title">
                        Frequently asked <em>questions</em>
                    </h2>
                </Reveal>
                <div className="sd-faq-wrap">
                    {[
                        {
                            q: 'What is the closed beta?',
                            a: "We're inviting 20 people to test SnapDraft before the public launch. You'll get full access to every feature for free, and your feedback will directly shape the product.",
                        },
                        {
                            q: 'Is it really free?',
                            a: "Yes. During the beta there's no charge and no credit card required. We just want your honest feedback.",
                        },
                        {
                            q: 'How should my spreadsheet be set up?',
                            a: 'Use title, description, and format columns. Each row is turned into one generated visual matching your brand style.',
                        },
                        {
                            q: 'Can I edit the generated images?',
                            a: 'Yes. Use the Canvas Editor to replace text, swap objects, and fine-tune any result.',
                        },
                        {
                            q: 'What happens after the beta ends?',
                            a: 'Beta testers get early access to the paid plans at a discounted rate. Your projects and assets carry over.',
                        },
                    ].map((item, idx) => {
                        const isOpen = openFaq === idx;
                        return (
                            <div
                                key={item.q}
                                className={cn('sd-faq-item', isOpen && 'open')}
                            >
                                <button
                                    type="button"
                                    className="sd-faq-btn"
                                    onClick={() =>
                                        setOpenFaq(isOpen ? -1 : idx)
                                    }
                                >
                                    <span>{item.q}</span>
                                    <span className="sd-faq-ico">+</span>
                                </button>
                                <div className="sd-faq-ans">{item.a}</div>
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* ── CTA ── */}
            <section className="sd-cta">
                <Reveal>
                    <h2>
                        20 spots. <em>Be one of them.</em>
                    </h2>
                    <p>
                        Free during beta. No credit card. Your feedback shapes
                        the product.
                    </p>
                    <div className="sd-cta-row">
                        <button
                            type="button"
                            className="sd-btn-hero"
                            onClick={openWaitlistCard}
                        >
                            Request access
                            <ArrowRight size={16} />
                        </button>
                        <Link
                            href={login().url}
                            className="sd-btn-hero-ghost sd-btn-hero-ghost-inv"
                        >
                            Already have an account? Sign in
                        </Link>
                    </div>
                </Reveal>
            </section>

            {/* ── Footer ── */}
            <footer className="sd-footer">
                <div className="sd-footer-grid">
                    <div>
                        <Link href="/" className="sd-logo sd-logo-light">
                            <img
                                src="/SnapdraftLogoBlack.svg"
                                alt="SnapDraft"
                                className="sd-logo-image"
                            />
                        </Link>
                        <p>
                            AI-powered visual content generation — currently in
                            closed beta.
                        </p>
                    </div>
                    <div>
                        <h5>Product</h5>
                        <a href="#how">How it works</a>
                        <a href="#beta">Beta perks</a>
                        <a href="#faq">FAQ</a>
                    </div>
                    <div>
                        <h5>Account</h5>
                        <Link href={register().url}>Create account</Link>
                        <Link href={login().url}>Sign in</Link>
                    </div>
                </div>
                <div className="sd-footer-bottom">
                    <span>
                        © {new Date().getFullYear()} SnapDraft. All rights
                        reserved.
                    </span>
                    <span>Privacy · Terms</span>
                </div>
            </footer>
        </div>
    );
}
