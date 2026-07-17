import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="sd-auth">
            <div className="sd-auth-card">
                <Link href="/" className="sd-auth-logo">
                    <img src="/SnapdraftLogoBlack.svg" alt="SnapDraft" />
                </Link>
                <h1 className="sd-auth-title">{title}</h1>
                {description && (
                    <p className="sd-auth-desc">{description}</p>
                )}
                {children}
            </div>
        </div>
    );
}
