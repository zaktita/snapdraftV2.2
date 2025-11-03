import { useState, FormEvent } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Search, MoreVertical, Ban, CheckCircle, Trash2, Crown } from 'lucide-react';
import { Textarea } from '@/components/ui/textarea';

interface User {
    id: number;
    name: string;
    email: string;
    subscription_tier: string;
    credits_remaining: number;
    credits_total: number;
    is_admin: boolean;
    is_suspended: boolean;
    suspension_reason: string | null;
    total_generations: number;
    created_at: string;
}

interface UsersPageProps {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        tier?: string;
        status?: string;
    };
}

export default function UsersPage({ users, filters }: UsersPageProps) {
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [actionType, setActionType] = useState<'suspend' | 'delete' | 'tier' | null>(null);
    const [suspensionReason, setSuspensionReason] = useState('');
    const [newTier, setNewTier] = useState('');

    const { data, setData, get } = useForm({
        search: filters.search || '',
        tier: filters.tier || 'all',
        status: filters.status || 'all',
    });

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        get('/admin/users', { preserveState: true });
    };

    const handleAction = () => {
        if (!selectedUser) return;

        if (actionType === 'suspend') {
            router.post(`/admin/users/${selectedUser.id}/suspend`, {
                reason: suspensionReason,
            }, {
                onSuccess: () => {
                    setSelectedUser(null);
                    setActionType(null);
                    setSuspensionReason('');
                },
            });
        } else if (actionType === 'delete') {
            router.delete(`/admin/users/${selectedUser.id}`, {
                onSuccess: () => {
                    setSelectedUser(null);
                    setActionType(null);
                },
            });
        } else if (actionType === 'tier' && newTier) {
            router.put(`/admin/users/${selectedUser.id}/tier`, {
                tier: newTier,
            }, {
                onSuccess: () => {
                    setSelectedUser(null);
                    setActionType(null);
                    setNewTier('');
                },
            });
        }
    };

    const handleReactivate = (userId: number) => {
        router.post(`/admin/users/${userId}/reactivate`);
    };

    const getTierBadge = (tier: string) => {
        switch (tier) {
            case 'pro':
                return <Badge className="bg-blue-500">Pro</Badge>;
            case 'enterprise':
                return <Badge className="bg-purple-500">Enterprise</Badge>;
            default:
                return <Badge variant="outline">Free</Badge>;
        }
    };

    return (
        <AppLayout>
            <Head title="User Management" />

            <div className="min-h-screen bg-gray-50 p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">User Management</h1>
                        <p className="text-gray-600 mt-2">Manage users, subscriptions, and access</p>
                    </div>

                    {/* Filters */}
                    <Card className="p-6 mb-6">
                        <form onSubmit={handleSearch} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Search by name or email..."
                                    value={data.search}
                                    onChange={(e) => setData('search', e.target.value)}
                                    className="pl-10"
                                />
                            </div>

                            <Select value={data.tier} onValueChange={(value) => setData('tier', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="All Tiers" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Tiers</SelectItem>
                                    <SelectItem value="free">Free</SelectItem>
                                    <SelectItem value="pro">Pro</SelectItem>
                                    <SelectItem value="enterprise">Enterprise</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="suspended">Suspended</SelectItem>
                                </SelectContent>
                            </Select>

                            <Button type="submit">Apply Filters</Button>
                        </form>
                    </Card>

                    {/* Users Table */}
                    <Card>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b">
                                    <tr>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">User</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Tier</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Credits</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Generations</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Status</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Joined</th>
                                        <th className="text-right p-4 font-medium text-sm text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="border-b hover:bg-gray-50">
                                            <td className="p-4">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium">{user.name}</p>
                                                        {user.is_admin && (
                                                            <Crown className="h-4 w-4 text-yellow-500" />
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-500">{user.email}</p>
                                                </div>
                                            </td>
                                            <td className="p-4">{getTierBadge(user.subscription_tier)}</td>
                                            <td className="p-4">
                                                <span className="text-sm">
                                                    {user.credits_remaining} / {user.credits_total}
                                                </span>
                                            </td>
                                            <td className="p-4">{user.total_generations}</td>
                                            <td className="p-4">
                                                {user.is_suspended ? (
                                                    <Badge variant="destructive">Suspended</Badge>
                                                ) : (
                                                    <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
                                                        Active
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {new Date(user.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="p-4 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        {!user.is_suspended ? (
                                                            <DropdownMenuItem
                                                                disabled={user.is_admin}
                                                                onClick={() => {
                                                                    setSelectedUser(user);
                                                                    setActionType('suspend');
                                                                }}
                                                            >
                                                                <Ban className="h-4 w-4 mr-2" />
                                                                Suspend
                                                            </DropdownMenuItem>
                                                        ) : (
                                                            <DropdownMenuItem
                                                                onClick={() => handleReactivate(user.id)}
                                                            >
                                                                <CheckCircle className="h-4 w-4 mr-2" />
                                                                Reactivate
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                setSelectedUser(user);
                                                                setActionType('tier');
                                                                setNewTier(user.subscription_tier);
                                                            }}
                                                        >
                                                            <Crown className="h-4 w-4 mr-2" />
                                                            Change Tier
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            disabled={user.is_admin}
                                                            className="text-red-600"
                                                            onClick={() => {
                                                                setSelectedUser(user);
                                                                setActionType('delete');
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4 mr-2" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {users.last_page > 1 && (
                            <div className="flex items-center justify-between px-6 py-4 border-t">
                                <p className="text-sm text-gray-600">
                                    Showing {users.data.length} of {users.total} users
                                </p>
                                <div className="flex gap-2">
                                    {users.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            onClick={() => get(`/admin/users?page=${users.current_page - 1}`)}
                                        >
                                            Previous
                                        </Button>
                                    )}
                                    {users.current_page < users.last_page && (
                                        <Button
                                            variant="outline"
                                            onClick={() => get(`/admin/users?page=${users.current_page + 1}`)}
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {/* Suspend Dialog */}
            <Dialog open={actionType === 'suspend'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Suspend User</DialogTitle>
                        <DialogDescription>
                            Suspend {selectedUser?.name}? They will not be able to log in until reactivated.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="my-4">
                        <label className="text-sm font-medium mb-2 block">Suspension Reason</label>
                        <Textarea
                            value={suspensionReason}
                            onChange={(e) => setSuspensionReason(e.target.value)}
                            placeholder="Enter reason for suspension..."
                            rows={3}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => {
                            setSelectedUser(null);
                            setActionType(null);
                            setSuspensionReason('');
                        }}>
                            Cancel
                        </Button>
                        <Button onClick={handleAction} disabled={!suspensionReason}>
                            Suspend User
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={actionType === 'delete'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete User</DialogTitle>
                        <DialogDescription>
                            Permanently delete {selectedUser?.name}? This action cannot be undone.
                            All their projects and data will be deleted.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => {
                            setSelectedUser(null);
                            setActionType(null);
                        }}>
                            Cancel
                        </Button>
                        <Button onClick={handleAction} variant="destructive">
                            Delete User
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Change Tier Dialog */}
            <Dialog open={actionType === 'tier'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change Subscription Tier</DialogTitle>
                        <DialogDescription>
                            Change {selectedUser?.name}'s subscription tier. Credits will be reset based on the new tier.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="my-4">
                        <label className="text-sm font-medium mb-2 block">New Tier</label>
                        <Select value={newTier} onValueChange={setNewTier}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="free">Free (10 credits)</SelectItem>
                                <SelectItem value="pro">Pro (100 credits)</SelectItem>
                                <SelectItem value="enterprise">Enterprise (Unlimited)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => {
                            setSelectedUser(null);
                            setActionType(null);
                            setNewTier('');
                        }}>
                            Cancel
                        </Button>
                        <Button onClick={handleAction}>
                            Change Tier
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
