import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Facebook,
    Instagram,
    Linkedin,
    Menu,
    X as XIcon,
} from 'lucide-react';
import { FormEvent, useEffect, useState, type ReactNode } from 'react';

const NAV_LINKS = [
    { href: '/features', label: 'Features' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/blog', label: 'Blog' },
    { href: '/faq', label: 'FAQ' },
    { href: '/contact', label: 'Contact' },
];

const SOCIAL_LINKS = [
    {
        label: 'Facebook',
        href: 'https://www.facebook.com',
        icon: Facebook,
    },
    {
        label: 'LinkedIn',
        href: 'https://www.linkedin.com',
        icon: Linkedin,
    },
    {
        label: 'X',
        href: 'https://x.com',
        icon: XIcon,
    },
    {
        label: 'Instagram',
        href: 'https://www.instagram.com',
        icon: Instagram,
    },
];

interface MarketingLayoutProps {
    title: string;
    description?: string;
    ogImage?: string;
    children: ReactNode;
}

export default function MarketingLayout({
    title,
    description,
    ogImage = '/images/marketing/og.png',
    children,
}: MarketingLayoutProps) {
    const [scrolled, setScrolled] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [newsletterEmail, setNewsletterEmail] = useState('');
    const [newsletterDone, setNewsletterDone] = useState(false);
    const { url } = usePage();

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 12);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        setMenuOpen(false);
    }, [url]);

    const onNewsletter = (e: FormEvent) => {
        e.preventDefault();
        if (!newsletterEmail.trim()) return;
        setNewsletterDone(true);
        setNewsletterEmail('');
    };

    return (
        <div className="sd-home">
            <Head title={title}>
                {description && (
                    <meta name="description" content={description} />
                )}
                <meta property="og:title" content={title} />
                {description && (
                    <meta property="og:description" content={description} />
                )}
                <meta property="og:type" content="website" />
                <meta property="og:image" content={ogImage} />
                <meta name="twitter:card" content="summary_large_image" />
            </Head>

            <header
                className={cn('sd-nav-wrap', scrolled && 'sd-nav-wrap-scrolled')}
            >
                <nav className="sd-nav" aria-label="Primary">
                    <Link href="/" className="sd-logo">
                        <img
                            src="/SnapdraftLogoBlack.svg"
                            alt="SnapDraft"
                            className="sd-logo-image"
                        />
                    </Link>

                    <ul className="sd-nav-links">
                        {NAV_LINKS.map((link) => (
                            <li key={link.href}>
                                <Link
                                    href={link.href}
                                    className={cn(
                                        url.startsWith(link.href) &&
                                            'sd-nav-link-active',
                                    )}
                                >
                                    {link.label}
                                </Link>
                            </li>
                        ))}
                    </ul>

                    <div className="sd-nav-end">
                        <Link
                            href={login().url}
                            className="sd-btn-sm-ghost sd-nav-signin"
                        >
                            Sign in
                        </Link>
                        <Link href={register().url} className="sd-btn-sm">
                            Get started
                        </Link>
                        <button
                            type="button"
                            className="sd-nav-menu-btn"
                            aria-expanded={menuOpen}
                            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                            onClick={() => setMenuOpen((o) => !o)}
                        >
                            {menuOpen ? <XIcon size={20} /> : <Menu size={20} />}
                        </button>
                    </div>
                </nav>

                {menuOpen && (
                    <div className="sd-nav-mobile">
                        {NAV_LINKS.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={cn(
                                    url.startsWith(link.href) &&
                                        'sd-nav-link-active',
                                )}
                            >
                                {link.label}
                            </Link>
                        ))}
                        <Link href={login().url} className="sd-btn-sm-ghost">
                            Sign in
                        </Link>
                        <Link href={register().url} className="sd-btn-sm">
                            Get started
                        </Link>
                    </div>
                )}
            </header>

            <main>{children}</main>

            <footer className="sd-footer">
                <div className="sd-footer-shell">
                    <div className="sd-footer-newsletter">
                        <div className="sd-footer-newsletter-inner">
                            <div className="sd-footer-newsletter-copy">
                                <h3>
                                    Workflow tips for people who publish weekly
                                </h3>
                            </div>
                            {newsletterDone ? (
                                <p className="sd-footer-newsletter-thanks">
                                    Thanks, you&apos;re on the list.
                                </p>
                            ) : (
                                <form
                                    className="sd-footer-newsletter-form"
                                    onSubmit={onNewsletter}
                                >
                                    <input
                                        type="email"
                                        name="email"
                                        required
                                        placeholder="Enter your email"
                                        value={newsletterEmail}
                                        onChange={(e) =>
                                            setNewsletterEmail(e.target.value)
                                        }
                                        aria-label="Email for newsletter"
                                    />
                                    <button type="submit">Subscribe</button>
                                </form>
                            )}
                        </div>
                    </div>

                    <div className="sd-footer-main">
                        <div className="sd-footer-grid">
                            <div className="sd-footer-brand">
                                <div>
                                    <Link
                                        href="/"
                                        className="sd-logo sd-logo-light"
                                    >
                                        <img
                                            src="/SnapdraftLogoBlack.svg"
                                            alt="SnapDraft"
                                            className="sd-logo-image"
                                        />
                                    </Link>
                                    <p>
                                        On-brand visuals in minutes for social
                                        managers, freelancers, and agencies.
                                    </p>
                                </div>
                                {/* <div className="sd-footer-social flex flex-row flex-nowrap items-center gap-3">
                                    {SOCIAL_LINKS.map(
                                        ({ label, href, icon: Icon }) => (
                                            <a
                                                key={label}
                                                href={href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label={label}
                                                className="sd-footer-social-btn flex   size-[38px] shrink-0"
                                            >
                                                <Icon
                                                    size={15}
                                                    strokeWidth={2.25}
                                                />
                                            </a>
                                        ),
                                    )}
                                </div> */}
                            </div>
                            <div className="sd-footer-col">
                                <h5>Pages</h5>
                                <Link
                                    href="/"
                                    className={
                                        url === '/' ? 'sd-footer-link-active' : undefined
                                    }
                                >
                                    Home
                                </Link>
                                <Link
                                    href="/features"
                                    className={
                                        url.startsWith('/features')
                                            ? 'sd-footer-link-active'
                                            : undefined
                                    }
                                >
                                    Features
                                </Link>
                                <Link
                                    href="/pricing"
                                    className={
                                        url.startsWith('/pricing')
                                            ? 'sd-footer-link-active'
                                            : undefined
                                    }
                                >
                                    Pricing
                                </Link>
                                <Link href="/faq">FAQ</Link>
                            </div>
                            <div className="sd-footer-col">
                                <h5>Pages</h5>
                                <Link href="/blog">Blog</Link>
                                <Link href="/contact">Contact</Link>
                                <Link href="/privacy">Privacy</Link>
                                <Link href="/terms">Terms</Link>
                                <Link href="/refund">Refunds</Link>
                            </div>
                            <div className="sd-footer-col">
                                <h5>Pages</h5>
                                <Link href={register().url}>Create account</Link>
                                <Link href={login().url}>Sign in</Link>
                                <Link href="/contact">Contact</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}
