@php
    $done = (bool) session('beta_applied');
    $submittedEmail = session('beta_email');
    $pageTitle = $done
        ? "You're in our queue - SnapDraft"
        : 'Apply for beta access - SnapDraft';
    $roleLabels = [
        'founder' => 'Founder / solo builder',
        'marketer' => 'Marketer',
        'agency' => 'Agency',
        'designer' => 'Designer',
        'content_creator' => 'Content creator',
        'other' => 'Other',
    ];
    $volumeLabels = [
        '1-5' => '1-5',
        '6-20' => '6-20',
        '21-50' => '21-50',
        '51-100' => '51-100',
        '100-plus' => '100+',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="Apply for SnapDraft beta access. For social media managers, freelancers, and agencies.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="SnapDraft">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="Apply for SnapDraft beta access. For social media managers, freelancers, and agencies.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/images/marketing/og.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="Apply for SnapDraft beta access. For social media managers, freelancers, and agencies.">
    <meta name="twitter:image" content="{{ url('/images/marketing/og.png') }}">
    <script>
        (function () {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        })();
    </script>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700;800;900&family=Raleway:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/marketing.js'])
</head>
<body class="font-sans antialiased">
    <div class="sd-beta-apply-page">
        <div class="sd-beta-apply-page-inner">
            <a href="/" class="sd-beta-apply-logo">
                <img src="/SnapdraftLogoBlack.svg" alt="SnapDraft" class="sd-logo-image">
            </a>

            <div class="sd-beta-apply-page-card">
                @if ($done)
                    <div class="sd-beta-apply-thanks">
                        <div class="sd-beta-apply-thanks-icon" aria-hidden="true">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <p class="sd-beta-apply-thanks-eyebrow">Application received</p>
                        <h1 class="sd-beta-apply-thanks-title">
                            Thank you, <em>we'll be in touch</em>
                        </h1>
                        <p class="sd-beta-apply-thanks-lead">
                            A real person reads every submission. If
                            you're a fit for this beta cohort,
                            we'll email you within <strong>24 hours</strong> with next steps.
                        </p>
                        @if ($submittedEmail)
                            <p class="sd-beta-apply-thanks-email">
                                <span class="sd-beta-apply-thanks-email-label">Confirmation will go to</span>
                                <span class="sd-beta-apply-thanks-email-value">{{ $submittedEmail }}</span>
                                <span class="sd-beta-apply-thanks-email-hint">
                                    Check spam or Promotions, sometimes filters get it wrong.
                                </span>
                            </p>
                        @endif
                        <ul class="sd-beta-apply-thanks-list">
                            <li>We review your role and how you create visuals today.</li>
                            <li>Approved testers get a one-time invite code, no credit card, free during beta.</li>
                        </ul>
                        <div class="sd-beta-apply-thanks-actions">
                            <a href="/" class="sd-beta-apply-thanks-btn sd-beta-apply-thanks-btn-primary">
                                Back to home
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('login') }}" class="sd-beta-apply-thanks-btn sd-beta-apply-thanks-btn-ghost">
                                I already have an account
                            </a>
                        </div>
                    </div>
                @else
                    <div class="sd-beta-apply-head">
                        <p class="sd-beta-apply-eyebrow">
                            <span class="sd-badge-dot"></span>
                            BETA · 20 SPOTS
                        </p>
                        <h1 class="sd-beta-apply-title">
                            Apply for <em>early access</em>
                        </h1>
                        <p class="sd-beta-apply-sub">
                            For social media managers, freelancers, and
                            agencies tired of waiting on designers. Four
                            questions. About 90 seconds.
                        </p>
                    </div>
                    <form class="sd-beta-apply-form" method="post" action="{{ route('beta.apply') }}">
                        @csrf
                        <label class="sd-beta-field">
                            <span>Email</span>
                            <input
                                type="email"
                                name="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                                value="{{ old('email') }}"
                                required
                                autofocus
                            >
                        </label>
                        <label class="sd-beta-field">
                            <span>
                                What best describes you?
                                <i class="sd-beta-hint">pick one</i>
                            </span>
                            <select name="role" required>
                                <option value="">Select your role</option>
                                @foreach ($roleLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="sd-beta-field">
                            <span>How many social posts do you produce per month?</span>
                            <select name="monthly_post_volume" required>
                                <option value="">Select a range</option>
                                @foreach ($volumeLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('monthly_post_volume') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="sd-beta-field">
                            <span>How do you handle visuals right now?</span>
                            <textarea
                                name="visual_workflow"
                                rows="4"
                                placeholder="e.g. I do everything in Canva myself, or I brief a designer but it creates delays..."
                                required
                            >{{ old('visual_workflow') }}</textarea>
                        </label>
                        <p class="sd-beta-apply-foot">
                            We'll review and reply within 24h. No spam, ever.
                        </p>
                        <div class="sd-beta-apply-actions">
                            <button type="submit">
                                Apply for access
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </form>
                    @if ($errors->any())
                        <p class="sd-beta-apply-page-error">{{ $errors->first() }}</p>
                    @endif
                @endif
            </div>

            @unless ($done)
                <p class="sd-beta-apply-page-footer">
                    <a href="{{ route('login') }}">Already have an account? Sign in</a>
                    ·
                    <a href="/">Back to home</a>
                </p>
            @endunless
        </div>
    </div>
</body>
</html>
