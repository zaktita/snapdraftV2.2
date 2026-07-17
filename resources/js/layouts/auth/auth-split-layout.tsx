import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { quote } = usePage<SharedData>().props;

    return (
        <div className="sd-auth sd-auth-split">
            <div className="sd-auth-split-aside">
                <Link href="/">
                    <img
                        src="/SnapdraftLogoBlack.svg"
                        alt="SnapDraft"
                        className="h-8 w-auto"
                        style={{ filter: 'brightness(0) invert(1)' }}
                    />
                </Link>
                {quote && (
                    <blockquote>
                        <p className="text-lg leading-relaxed">
                            &ldquo;{quote.message}&rdquo;
                        </p>
                        <footer className="mt-3 text-sm opacity-70">
                            {quote.author}
                        </footer>
                    </blockquote>
                )}
            </div>
            <div className="sd-auth-split-main">
                <div className="sd-auth-card">
                    <Link href="/" className="sd-auth-logo lg:hidden">
                        <img src="/SnapdraftLogoBlack.svg" alt="SnapDraft" />
                    </Link>
                    <h1 className="sd-auth-title">{title}</h1>
                    {description && (
                        <p className="sd-auth-desc">{description}</p>
                    )}
                    {children}
                </div>
            </div>
        </div>
    );
}
