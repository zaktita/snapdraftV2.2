@extends('layouts.marketing', [
    'title' => 'Privacy Policy - SnapDraft',
    'description' => 'How SnapDraft collects, uses, and retains account, billing, and project data.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Legal</div>
            <h1>Privacy Policy</h1>
        </div>
    </div>
    <p class="sd-legal-updated">Last updated: July 14, 2026</p>
    <div class="sd-legal">
        <div class="sd-prose">
            <p>
                SnapDraft ("we", "us") provides AI-assisted visual generation for
                brands. This policy explains what we collect and how we use it.
            </p>
            <h2>What we collect</h2>
            <ul>
                <li>Account details (name, email, authentication data)</li>
                <li>Billing details processed by Lemon Squeezy (we do not store full card numbers)</li>
                <li>Project content you upload (brand references, CSV captions, generated images)</li>
                <li>Usage and product analytics to improve the product</li>
            </ul>
            <h2>How we use data</h2>
            <ul>
                <li>To provide generation, editing, billing, and support</li>
                <li>To send transactional email (password reset, generation failures)</li>
                <li>To operate AI providers necessary to generate your outputs</li>
            </ul>
            <h2>AI providers</h2>
            <p>
                Captions, brand references, and prompts are sent to third-party AI providers solely
                to deliver the service.
            </p>
            <h2>Retention &amp; deletion</h2>
            <p>
                You may delete your account from Settings. Project and image records are removed
                with your account; residual storage objects are cleaned by scheduled jobs.
            </p>
            <h2>Contact</h2>
            <p>Questions: contact@snapdraft.com</p>
        </div>
    </div>
@endsection
