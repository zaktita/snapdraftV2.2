@extends('layouts.marketing', [
    'title' => 'Contact - SnapDraft',
    'description' => 'Questions about SnapDraft for social managers, freelancers, or agencies? Pricing, workflows, or your account. Send a message.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Contact</div>
            <h1>Talk to a <em>human</em></h1>
            <p>
                Questions about fitting SnapDraft into your client
                workflow, pricing, or something else? Send a message.
                It lands in the founder's inbox.
            </p>
        </div>
    </div>

    <div class="sd-contact-wrap">
        <div class="reveal">
            <div class="sd-contact-card">
                @if (session('success'))
                    <div class="sd-contact-success">
                        <div class="sd-contact-success-icon">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                        </div>
                        <h3>Message sent</h3>
                        <p>
                            Thanks for reaching out. We usually reply
                            within one business day.
                        </p>
                    </div>
                @else
                    <form class="sd-contact-form" method="post" action="{{ route('contact.submit') }}">
                        @csrf
                        <div class="sd-contact-field">
                            <label for="contact-name">Name</label>
                            <input
                                id="contact-name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="Your name"
                                required
                            >
                            @error('name')
                                <span class="sd-contact-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="sd-contact-field">
                            <label for="contact-email">Email</label>
                            <input
                                id="contact-email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="you@company.com"
                                required
                            >
                            @error('email')
                                <span class="sd-contact-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="sd-contact-field">
                            <label for="contact-message">Message</label>
                            <textarea
                                id="contact-message"
                                name="message"
                                placeholder="What can we help with?"
                                required
                            >{{ old('message') }}</textarea>
                            @error('message')
                                <span class="sd-contact-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="sd-btn-hero">
                            Send message
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                @endif
            </div>
            <p class="sd-contact-aside">
                Prefer email? Write to
                <a href="mailto:contact@snapdraft.com">contact@snapdraft.com</a>
            </p>
        </div>
    </div>
@endsection
