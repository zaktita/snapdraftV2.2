import AdminLayout from '@/layouts/admin-layout';
import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, XCircle, ExternalLink } from 'lucide-react';

interface Plan {
    id: string; name: string; slug: string; description: string;
    price: number; currency: string; billing_cycle: string;
    is_active: boolean; is_featured: boolean; has_trial: boolean; trial_days: number;
    sort_order: number; provider: string;
    provider_product_id: string; provider_variant_monthly: string; provider_variant_yearly: string;
    subscriptions_count: number; capabilities: Record<string, unknown> | null;
    deleted_at: string | null;
}

export default function PlansPage({ plans }: { plans: Plan[] }) {
    return (
        <AdminLayout title="Plans">
            <Head title="Plans" />
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500">{plans.length} plans synced from Lemon Squeezy</p>
                    <a href="https://app.lemonsqueezy.com/products" target="_blank" rel="noopener noreferrer"
                        className="flex items-center gap-1 text-xs text-indigo-600 hover:underline">
                        Manage in Lemon Squeezy <ExternalLink className="h-3 w-3" />
                    </a>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    {plans.map((plan) => (
                        <div key={plan.id} className={`rounded-xl bg-white p-5 shadow-sm ring-1 ${plan.deleted_at ? 'opacity-50 ring-red-100' : plan.is_active ? 'ring-gray-100' : 'ring-gray-100 opacity-70'}`}>
                            <div className="mb-3 flex items-start justify-between">
                                <div>
                                    <h3 className="font-semibold text-gray-900">{plan.name}</h3>
                                    <p className="text-xs text-gray-400">{plan.slug}</p>
                                </div>
                                <div className="flex flex-col items-end gap-1">
                                    {plan.is_active
                                        ? <Badge className="bg-emerald-100 text-emerald-700 text-[10px]">Active</Badge>
                                        : <Badge variant="outline" className="text-[10px]">Inactive</Badge>}
                                    {plan.is_featured && <Badge className="bg-indigo-100 text-indigo-700 text-[10px]">Featured</Badge>}
                                    {plan.deleted_at && <Badge variant="destructive" className="text-[10px]">Deleted</Badge>}
                                </div>
                            </div>

                            <p className="mb-3 text-2xl font-bold text-gray-900">
                                ${plan.price} <span className="text-sm font-normal text-gray-400">/{plan.billing_cycle}</span>
                            </p>

                            {plan.description && <p className="mb-3 text-xs text-gray-500">{plan.description}</p>}

                            <div className="space-y-1 text-xs text-gray-600">
                                <div className="flex justify-between">
                                    <span>Subscriptions</span>
                                    <span className="font-semibold text-gray-900">{plan.subscriptions_count}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Trial</span>
                                    <span>{plan.has_trial ? `${plan.trial_days} days` : '-'}</span>
                                </div>
                                {plan.capabilities && (
                                    <>
                                        <div className="flex justify-between">
                                            <span>Credits/mo</span>
                                            <span className="font-semibold text-gray-900">
                                                {(plan.capabilities as Record<string,unknown>)['credits_per_month'] as number ?? '-'}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Projects limit</span>
                                            <span>{(plan.capabilities as Record<string,unknown>)['projects_limit'] as number ?? '-'}</span>
                                        </div>
                                    </>
                                )}
                            </div>

                            {(plan.provider_variant_monthly || plan.provider_variant_yearly) && (
                                <div className="mt-3 border-t border-gray-50 pt-3 text-xs text-gray-400">
                                    <p>Provider: <span className="font-medium text-gray-600">{plan.provider}</span></p>
                                    {plan.provider_variant_monthly && <p>Monthly variant: {plan.provider_variant_monthly}</p>}
                                    {plan.provider_variant_yearly && <p>Yearly variant: {plan.provider_variant_yearly}</p>}
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                {plans.length === 0 && (
                    <div className="rounded-xl bg-white p-10 text-center text-gray-400 ring-1 ring-gray-100">
                        No plans synced yet. Run <code className="text-xs bg-gray-100 px-1 rounded">php artisan lemon:sync-plans</code> to import from Lemon Squeezy.
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
