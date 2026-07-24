@php
    $path = '/'.ltrim(request()->path(), '/');
    if ($path === '//') {
        $path = '/';
    }
@endphp
<footer class="sd-footer">
    <div class="sd-footer-shell">
        <div class="sd-footer-main" style="padding-top: 48px">
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
                            SnapDraft turns brand references and content calendars
                            into on-brand visuals for social managers, freelancers,
                            and agencies.
                        </p>
                    </div>
                </div>
                <div class="sd-footer-col">
                    <h5>Product</h5>
                    <a href="/" @class(['sd-footer-link-active' => $path === '/'])>Home</a>
                    <a href="/features" @class(['sd-footer-link-active' => str_starts_with($path, '/features')])>Features</a>
                    <a href="/pricing" @class(['sd-footer-link-active' => str_starts_with($path, '/pricing')])>Pricing</a>
                    <a href="/templates" @class(['sd-footer-link-active' => str_starts_with($path, '/templates')])>Templates</a>
                    <a href="{{ route('register') }}">Create account</a>
                    <a href="{{ route('login') }}">Sign in</a>
                </div>
                <div class="sd-footer-col">
                    <h5>Resources</h5>
                    <a href="/use-cases" @class(['sd-footer-link-active' => str_starts_with($path, '/use-cases')])>Use cases</a>
                    <a href="/alternatives" @class(['sd-footer-link-active' => str_starts_with($path, '/alternatives') || str_starts_with($path, '/compare')])>Alternatives</a>
                    <a href="/glossary" @class(['sd-footer-link-active' => str_starts_with($path, '/glossary')])>Glossary</a>
                    <a href="/blog" @class(['sd-footer-link-active' => str_starts_with($path, '/blog')])>Blog</a>
                    <a href="/faq" @class(['sd-footer-link-active' => str_starts_with($path, '/faq')])>FAQ</a>
                    <a href="/about" @class(['sd-footer-link-active' => str_starts_with($path, '/about')])>About</a>
                    <a href="/contact" @class(['sd-footer-link-active' => str_starts_with($path, '/contact')])>Contact</a>
                </div>
                <div class="sd-footer-col">
                    <h5>Legal</h5>
                    <a href="/privacy">Privacy</a>
                    <a href="/terms">Terms</a>
                    <a href="/refund">Refunds</a>
                </div>
            </div>
        </div>
    </div>
</footer>
