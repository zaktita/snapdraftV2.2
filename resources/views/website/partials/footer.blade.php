@php
    $path = '/'.ltrim(request()->path(), '/');
    if ($path === '//') {
        $path = '/';
    }
@endphp
<footer class="sd-footer">
    <div class="sd-footer-shell">
        <div class="sd-footer-newsletter">
            <div class="sd-footer-newsletter-inner">
                <div class="sd-footer-newsletter-copy">
                    <h3>Workflow tips for people who publish weekly</h3>
                </div>
                <p class="sd-footer-newsletter-thanks" data-newsletter-thanks hidden>
                    Thanks, you're on the list.
                </p>
                <form class="sd-footer-newsletter-form" data-newsletter-form>
                    <input
                        type="email"
                        name="email"
                        required
                        placeholder="Enter your email"
                        aria-label="Email for newsletter"
                    >
                    <button type="submit">Subscribe</button>
                </form>
            </div>
        </div>

        <div class="sd-footer-main">
            <div class="sd-footer-grid">
                <div class="sd-footer-brand">
                    <div>
                        <a href="/" class="sd-logo sd-logo-light">
                            <img
                                src="/SnapdraftLogoBlack.svg"
                                alt="SnapDraft"
                                class="sd-logo-image"
                            >
                        </a>
                        <p>
                            On-brand visuals in minutes for social
                            managers, freelancers, and agencies.
                        </p>
                    </div>
                </div>
                <div class="sd-footer-col">
                    <h5>Pages</h5>
                    <a href="/" @class(['sd-footer-link-active' => $path === '/'])>Home</a>
                    <a href="/features" @class(['sd-footer-link-active' => str_starts_with($path, '/features')])>Features</a>
                    <a href="/pricing" @class(['sd-footer-link-active' => str_starts_with($path, '/pricing')])>Pricing</a>
                    <a href="/faq">FAQ</a>
                </div>
                <div class="sd-footer-col">
                    <h5>Pages</h5>
                    <a href="/blog">Blog</a>
                    <a href="/contact">Contact</a>
                    <a href="/privacy">Privacy</a>
                    <a href="/terms">Terms</a>
                    <a href="/refund">Refunds</a>
                </div>
                <div class="sd-footer-col">
                    <h5>Pages</h5>
                    <a href="{{ route('register') }}">Create account</a>
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="/contact">Contact</a>
                </div>
            </div>
        </div>
    </div>
</footer>
