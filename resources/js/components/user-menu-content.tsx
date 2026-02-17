import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { type User } from '@/types';
import { Link, router } from '@inertiajs/react';
import { LogOut, Settings, HelpCircle, UserPlus, Crown, CreditCard, Shield } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href={edit()}
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Account Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href="/subscription"
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <CreditCard className="mr-2" />
                        Billing & Credits
                    </Link>
                </DropdownMenuItem>
                {(user as User & { subscription_tier?: string }).subscription_tier === 'free' && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full"
                            href="/subscription/plans"
                            as="button"
                            prefetch
                            onClick={cleanup}
                        >
                            <Crown className="mr-2 text-yellow-500" />
                            Upgrade Plan
                        </Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href="/invite"
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <UserPlus className="mr-2" />
                        Invite Members
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href="/support"
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <HelpCircle className="mr-2" />
                        Help & Support
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            {(user as User & { is_admin?: boolean }).is_admin && (
                <>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full bg-yellow-50 text-yellow-900"
                                href="/admin/dashboard"
                                as="button"
                                prefetch
                                onClick={cleanup}
                            >
                                <Shield className="mr-2" />
                                Admin Dashboard
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
