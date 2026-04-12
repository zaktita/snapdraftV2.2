import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    children,
    title,
    description,
    logoClassName,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    logoClassName?: string;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            logoClassName={logoClassName}
            {...props}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
