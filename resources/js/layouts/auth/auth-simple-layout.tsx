import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
    logoClassName?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="sd-auth">
            <div className="sd-auth-card">
                <Link href="/" className="sd-auth-logo">
                    <img
                        src="/SnapdraftLogoBlack.svg"
                        alt="SnapDraft"
                    />
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
