import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { MessageSquareHeart } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Share Feedback', href: '/feedback' },
];

const CATEGORIES = ['Bug Report', 'Feature Request', 'General Feedback', 'UX / Design'] as const;

export default function Feedback() {
    const { auth, flash } = usePage<SharedData & { flash?: { success?: string } }>().props;
    const user = auth.user;

    const { data, setData, post, processing, errors, reset } = useForm({
        rating: 0,
        category: '',
        message: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/feedback', {
            onSuccess: () => reset('message', 'rating', 'category'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Share Feedback" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--sd-or-pale)] text-primary">
                        <MessageSquareHeart className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="font-display text-3xl font-normal tracking-tight">
                            Share Your Feedback
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Help us improve SnapDraft. Every response goes directly to the team.
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-foreground">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-6 rounded-2xl border border-border bg-card p-6">
                    {/* Rating */}
                    <div className="flex flex-col gap-2">
                        <Label>
                            How would you rate your experience so far?{' '}
                            {data.rating > 0 && (
                                <span className="font-semibold text-foreground">{data.rating}/10</span>
                            )}
                        </Label>
                        <div className="flex flex-wrap gap-2">
                            {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
                                <button
                                    key={n}
                                    type="button"
                                    onClick={() => setData('rating', n)}
                                    className={`h-10 w-10 rounded-full border text-sm font-semibold transition-colors ${
                                        data.rating === n
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-card text-muted-foreground hover:border-primary hover:text-primary'
                                    }`}
                                >
                                    {n}
                                </button>
                            ))}
                        </div>
                        {errors.rating && <p className="text-sm text-destructive">{errors.rating}</p>}
                    </div>

                    {/* Category */}
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="category">Category</Label>
                        <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                            <SelectTrigger id="category">
                                <SelectValue placeholder="What kind of feedback is this?" />
                            </SelectTrigger>
                            <SelectContent>
                                {CATEGORIES.map((c) => (
                                    <SelectItem key={c} value={c}>{c}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.category && <p className="text-sm text-destructive">{errors.category}</p>}
                    </div>

                    {/* Message */}
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="message">Your feedback</Label>
                        <Textarea
                            id="message"
                            rows={6}
                            placeholder="Tell us what's working, what's broken, or what you wish existed…"
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                        />
                        {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing || data.rating === 0 || !data.category}>
                            {processing ? 'Sending…' : 'Send Feedback'}
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            Sending as <span className="font-medium">{user.name}</span> ({user.email})
                        </p>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
