@php
    $path = '/'.ltrim(request()->path(), '/');
    if ($path === '//') {
        $path = '/';
    }
    $navLinks = [
        ['href' => '/features', 'label' => 'Features'],
        ['href' => '/use-cases', 'label' => 'Use cases'],
        ['href' => '/pricing', 'label' => 'Pricing'],
        ['href' => '/blog', 'label' => 'Blog'],
        ['href' => '/faq', 'label' => 'FAQ'],
        ['href' => '/contact', 'label' => 'Contact'],
    ];
@endphp
<header class="sd-nav-wrap">
    <nav class="sd-nav" aria-label="Primary">
        <a href="/" class="sd-logo">
            <img
                src="/SnapdraftLogoBlack.svg"
                alt="SnapDraft"
                class="sd-logo-image"
            >
        </a>

        <ul class="sd-nav-links">
            @foreach ($navLinks as $link)
                <li>
                    <a
                        href="{{ $link['href'] }}"
                        @class(['sd-nav-link-active' => str_starts_with($path, $link['href'])])
                    >
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="sd-nav-end">
            <a href="{{ route('login') }}" class="sd-btn-sm-ghost sd-nav-signin">
                Sign in
            </a>
            <a href="{{ route('register') }}" class="sd-btn-sm">
                Get started
            </a>
            <button
                type="button"
                class="sd-nav-menu-btn"
                aria-expanded="false"
                aria-label="Open menu"
            >
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </nav>

    <div class="sd-nav-mobile" id="sd-nav-mobile">
        @foreach ($navLinks as $link)
            <a
                href="{{ $link['href'] }}"
                @class(['sd-nav-link-active' => str_starts_with($path, $link['href'])])
            >
                {{ $link['label'] }}
            </a>
        @endforeach
        <a href="{{ route('login') }}" class="sd-btn-sm-ghost">Sign in</a>
        <a href="{{ route('register') }}" class="sd-btn-sm">Get started</a>
    </div>
</header>
