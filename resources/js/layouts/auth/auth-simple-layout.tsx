import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
    logoClassName?: string;
}

function AuthMark({ className }: { className?: string }) {
    return (
        <svg
            className={className}
            width="36"
            height="36"
            viewBox="0 0 36 36"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M18 4.5L20.4 13.2L29.1 15.6L20.4 18L18 26.7L15.6 18L6.9 15.6L15.6 13.2L18 4.5Z"
                fill="var(--sd-or)"
            />
            <path
                d="M27 21L28.1 24.9L32 26L28.1 27.1L27 31L25.9 27.1L22 26L25.9 24.9L27 21Z"
                fill="var(--sd-or)"
            />
        </svg>
    );
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="sd-auth">
            <div className="sd-auth-shell">
                <aside className="sd-auth-aside">
                    <img
                        src="/images/marketing/feature-brand-dna.png"
                        alt=""
                        className="sd-auth-aside-image"
                    />
                </aside>

                <div className="sd-auth-panel">
                    <Link href="/" className="sd-auth-mark" aria-label="SnapDraft home">
                        <AuthMark />
                    </Link>

                    <h1 className="sd-auth-title">{title}</h1>
                    {description && (
                        <p className="sd-auth-desc">{description}</p>
                    )}
                    <div className="sd-auth-divider" />

                    {children}
                </div>
            </div>
        </div>
    );
}
