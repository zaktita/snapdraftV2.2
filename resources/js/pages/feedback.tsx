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

            <div className="flex flex-col gap-6 p-6 max-w-2xl">
                <div className="flex items-center gap-3">
                    <MessageSquareHeart className="h-6 w-6 text-muted-foreground" />
                    <div>
                        <h1 className="text-xl font-semibold">Share Your Feedback</h1>
                        <p className="text-sm text-muted-foreground">
                            Help us improve SnapDraft. Every response goes directly to the team.
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-6">
                    {/* Rating */}
                    <div className="flex flex-col gap-2">
                        <Label>
                            How would you rate your experience so far?{' '}
                            {data.rating > 0 && (
                                <span className="font-semibold text-foreground">{data.rating}/10</span>
                            )}
                        </Label>
                        <div className="flex gap-2 flex-wrap">
                            {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
                                <button
                                    key={n}
                                    type="button"
                                    onClick={() => setData('rating', n)}
                                    className={`h-10 w-10 rounded-lg border text-sm font-medium transition-colors ${data.rating === n
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-border bg-background text-muted-foreground hover:border-foreground hover:text-foreground'
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
