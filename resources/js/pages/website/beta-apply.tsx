import {
    BETA_ROLE_OPTIONS,
    BETA_VOLUME_OPTIONS,
} from '@/constants/beta-application-form';
import { Head, Link } from '@inertiajs/react';
import { login } from '@/routes';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';

function csrfToken(): string {
    if (typeof document === 'undefined') return '';
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export default function BetaApplyPage() {
    const [email, setEmail] = useState('');
    const [role, setRole] = useState('');
    const [volume, setVolume] = useState('');
    const [visual, setVisual] = useState('');
    const [done, setDone] = useState(false);
    const [submittedEmail, setSubmittedEmail] = useState<string | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const emailRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        emailRef.current?.focus();
    }, []);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const em = email.trim();
        if (!em || !role || !volume || !visual.trim()) {
            setError('Please fill in every field.');
            return;
        }
        setError('');
        setLoading(true);
        try {
            const res = await fetch('/beta/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    email: em,
                    role,
                    monthly_post_volume: volume,
                    visual_workflow: visual.trim(),
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setSubmittedEmail(em);
                setDone(true);
            } else {
                const fromErrors = data.errors
                    ? (Object.values(data.errors) as string[][]).flat().join(' ')
                    : '';
                setError(
                    data.message ||
                        fromErrors ||
                        'Something went wrong. Please try again.',
                );
            }
        } catch {
            setError('Something went wrong. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="sd-beta-apply-page">
            <Head
                title={
                    done
                        ? "You're in our queue — SnapDraft"
                        : 'Apply for beta access — SnapDraft'
                }
            />

            <div className="sd-beta-apply-page-inner">
                <Link href="/" className="sd-beta-apply-logo">
                    <img
                        src="/SnapdraftLogoBlack.svg"
                        alt="SnapDraft"
                        className="sd-logo-image"
                    />
                </Link>

                <div className="sd-beta-apply-page-card">
                    {done ? (
                        <div className="sd-beta-apply-thanks">
                            <div
                                className="sd-beta-apply-thanks-icon"
                                aria-hidden
                            >
                                <CheckCircle2 size={36} strokeWidth={1.65} />
                            </div>
                            <p className="sd-beta-apply-thanks-eyebrow">
                                Application received
                            </p>
                            <h1 className="sd-beta-apply-thanks-title">
                                Thank you, <em>we&apos;ll be in touch</em>
                            </h1>
                            <p className="sd-beta-apply-thanks-lead">
                                A real person reads every submission. If
                                you&apos;re a fit for this beta cohort,
                                we&apos;ll email you within{' '}
                                <strong>24 hours</strong> with next steps.
                            </p>
                            {submittedEmail ? (
                                <p className="sd-beta-apply-thanks-email">
                                    <span className="sd-beta-apply-thanks-email-label">
                                        Confirmation will go to
                                    </span>
                                    <span className="sd-beta-apply-thanks-email-value">
                                        {submittedEmail}
                                    </span>
                                    <span className="sd-beta-apply-thanks-email-hint">
                                        Check spam or Promotions, sometimes
                                        filters get it wrong.
                                    </span>
                                </p>
                            ) : null}
                            <ul className="sd-beta-apply-thanks-list">
                                <li>
                                    We review your role and how you create
                                    visuals today.
                                </li>
                                <li>
                                    Approved testers get a one-time invite code
                                    , no credit card, free during beta.
                                </li>
                            </ul>
                            <div className="sd-beta-apply-thanks-actions">
                                <Link
                                    href="/"
                                    className="sd-beta-apply-thanks-btn sd-beta-apply-thanks-btn-primary"
                                >
                                    Back to home
                                    <ArrowRight size={16} aria-hidden />
                                </Link>
                                <Link
                                    href={login().url}
                                    className="sd-beta-apply-thanks-btn sd-beta-apply-thanks-btn-ghost"
                                >
                                    I already have an account
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="sd-beta-apply-head">
                                <p className="sd-beta-apply-eyebrow">
                                    <span className="sd-badge-dot" />
                                    BETA · 20 SPOTS
                                </p>
                                <h1 className="sd-beta-apply-title">
                                    Apply for <em>early access</em>
                                </h1>
                                <p className="sd-beta-apply-sub">
                                    Four questions. Takes 90 seconds. We review
                                    every application and send codes manually.
                                </p>
                            </div>
                            <form
                                className="sd-beta-apply-form"
                                onSubmit={handleSubmit}
                            >
                                <label className="sd-beta-field">
                                    <span>Email</span>
                                    <input
                                        ref={emailRef}
                                        type="email"
                                        autoComplete="email"
                                        placeholder="you@example.com"
                                        value={email}
                                        onChange={(e) =>
                                            setEmail(e.target.value)
                                        }
                                    />
                                </label>
                                <label className="sd-beta-field">
                                    <span>
                                        What best describes you?{' '}
                                        <i className="sd-beta-hint">
                                            pick one
                                        </i>
                                    </span>
                                    <select
                                        value={role}
                                        onChange={(e) =>
                                            setRole(e.target.value)
                                        }
                                    >
                                        <option value="">
                                            Select your role
                                        </option>
                                        {BETA_ROLE_OPTIONS.map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="sd-beta-field">
                                    <span>
                                        How many social posts do you produce per
                                        month?
                                    </span>
                                    <select
                                        value={volume}
                                        onChange={(e) =>
                                            setVolume(e.target.value)
                                        }
                                    >
                                        <option value="">
                                            Select a range
                                        </option>
                                        {BETA_VOLUME_OPTIONS.map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="sd-beta-field">
                                    <span>How do you handle visuals right now?</span>
                                    <textarea
                                        rows={4}
                                        placeholder="e.g. I do everything in Canva myself, or I brief a designer but it creates delays..."
                                        value={visual}
                                        onChange={(e) =>
                                            setVisual(e.target.value)
                                        }
                                    />
                                </label>
                                <p className="sd-beta-apply-foot">
                                    We&apos;ll review and reply within 24h. No
                                    spam, ever.
                                </p>
                                <div className="sd-beta-apply-actions">
                                    <button
                                        type="submit"
                                        disabled={
                                            loading ||
                                            !email.trim() ||
                                            !role ||
                                            !volume ||
                                            !visual.trim()
                                        }
                                    >
                                        {loading ? 'Sending…' : 'Apply for access'}
                                        <ArrowRight size={16} aria-hidden />
                                    </button>
                                </div>
                            </form>
                            {error && (
                                <p className="sd-beta-apply-page-error">{error}</p>
                            )}
                        </>
                    )}
                </div>

                {!done ? (
                    <p className="sd-beta-apply-page-footer">
                        <Link href={login().url}>
                            Already have an account? Sign in
                        </Link>
                        {' · '}
                        <Link href="/">Back to home</Link>
                    </p>
                ) : null}
            </div>
        </div>
    );
}
