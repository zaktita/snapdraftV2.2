@extends('layouts.marketing', [
    'title' => 'Terms of Service - SnapDraft',
    'description' => 'Terms governing use of SnapDraft accounts, plans, acceptable use, and intellectual property.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Legal</div>
            <h1>Terms of Service</h1>
        </div>
    </div>
    <p class="sd-legal-updated">Last updated: July 14, 2026</p>
    <div class="sd-legal">
        <div class="sd-prose">
            <p>
                By using SnapDraft you agree to these terms. If you do not agree, do not use the
                service.
            </p>
            <h2>The service</h2>
            <p>
                SnapDraft generates visual marketing assets from your brand references and CSV
                content. Output quality depends on your inputs and third-party AI systems.
            </p>
            <h2>Accounts &amp; plans</h2>
            <ul>
                <li>You must provide accurate registration information</li>
                <li>Credits and plan limits reset according to your subscription period</li>
                <li>You are responsible for activity under your account</li>
            </ul>
            <h2>Acceptable use</h2>
            <p>
                Do not use SnapDraft for illegal content, abuse of our APIs, attempts to bypass
                billing, or infringement of others' rights.
            </p>
            <h2>Intellectual property</h2>
            <p>
                You retain rights to content you upload. Subject to third-party AI provider terms,
                you may use generated assets for your business. You grant us a limited license to
                process your content to provide the service.
            </p>
            <h2>Disclaimer</h2>
            <p>
                The service is provided "as is". We do not guarantee uninterrupted
                availability or specific creative outcomes.
            </p>
            <h2>Contact</h2>
            <p>contact@snapdraft.com</p>
        </div>
    </div>
@endsection
