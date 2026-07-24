import '../css/landing.css';

function initReveal() {
    const nodes = document.querySelectorAll('.reveal');
    if (!nodes.length) return;

    if (!('IntersectionObserver' in window)) {
        nodes.forEach((el) => el.classList.add('revealed'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );

    nodes.forEach((el) => observer.observe(el));
}

function initNav() {
    const wrap = document.querySelector('.sd-nav-wrap');
    const menuBtn = document.querySelector('.sd-nav-menu-btn');
    const mobile = document.querySelector('.sd-nav-mobile');
    if (!wrap) return;

    const onScroll = () => {
        wrap.classList.toggle('sd-nav-wrap-scrolled', window.scrollY > 12);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (menuBtn && mobile) {
        menuBtn.addEventListener('click', () => {
            const willOpen = !mobile.classList.contains('is-open');
            mobile.classList.toggle('is-open', willOpen);
            menuBtn.setAttribute('aria-expanded', String(willOpen));
            menuBtn.setAttribute(
                'aria-label',
                willOpen ? 'Close menu' : 'Open menu',
            );
            const icon = menuBtn.querySelector('i');
            if (icon) {
                icon.className = willOpen
                    ? 'fa-solid fa-xmark'
                    : 'fa-solid fa-bars';
            }
        });
    }
}

function initPricingToggle() {
    document.querySelectorAll('[data-pricing-toggle]').forEach((root) => {
        const buttons = root.querySelectorAll('[data-period]');
        const cards = document.querySelectorAll(
            root.dataset.pricingTarget
                ? `${root.dataset.pricingTarget} [data-price-card]`
                : '[data-price-card]',
        );

        const apply = (period) => {
            buttons.forEach((btn) => {
                btn.classList.toggle(
                    'active',
                    btn.dataset.period === period,
                );
            });
            cards.forEach((card) => {
                const amount = card.querySelector('[data-price-amount]');
                const billed = card.querySelector('[data-price-billed]');
                if (!amount) return;
                const monthly = card.dataset.monthly;
                const yearly = card.dataset.yearly;
                const yearlyTotal = card.dataset.yearlyTotal;
                const symbol = card.dataset.symbol || '';
                const value = period === 'yearly' ? yearly : monthly;
                amount.textContent = `${symbol}${value}`;
                if (billed) {
                    billed.textContent =
                        period === 'yearly'
                            ? `Billed ${symbol}${yearlyTotal} yearly`
                            : 'Billed monthly';
                }
            });
        };

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => apply(btn.dataset.period));
        });

        apply('yearly');
    });
}

function initFeatureTabs() {
    document.querySelectorAll('[data-feat-tabs]').forEach((root) => {
        const tabs = root.querySelectorAll('[data-feat-tab]');
        const panels = root.querySelectorAll('[data-feat-panel]');

        const activate = (index) => {
            tabs.forEach((tab, i) => {
                const active = i === index;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', String(active));
                const tone = tab.dataset.tone;
                if (tone) {
                    tab.classList.toggle(`sd-feat-tab-${tone}`, active);
                }
            });
            panels.forEach((panel, i) => {
                panel.hidden = i !== index;
            });
        };

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => activate(i));
        });

        activate(0);
    });
}

function initFaq() {
    document.querySelectorAll('[data-faq]').forEach((wrap) => {
        wrap.querySelectorAll('.sd-faq-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const item = btn.closest('.sd-faq-item');
                if (!item) return;
                const wasOpen = item.classList.contains('open');
                wrap.querySelectorAll('.sd-faq-item.open').forEach((el) => {
                    el.classList.remove('open');
                });
                if (!wasOpen) item.classList.add('open');
            });
        });
    });
}

function initMarketingCtaTracking() {
    if (typeof window.posthog?.capture !== 'function') return;

    document.querySelectorAll('a[href]').forEach((link) => {
        const href = link.getAttribute('href') || '';
        let event = null;
        if (href.includes('/register') || link.classList.contains('sd-btn-hero') || link.classList.contains('sd-btn-price')) {
            event = 'marketing_cta_click';
        } else if (href.includes('/pricing')) {
            event = 'marketing_pricing_click';
        } else if (href.includes('/contact')) {
            event = 'marketing_contact_click';
        }
        if (!event) return;
        link.addEventListener('click', () => {
            window.posthog.capture(event, {
                href,
                label: (link.textContent || '').trim().slice(0, 120),
                path: window.location.pathname,
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initReveal();
    initNav();
    initPricingToggle();
    initFeatureTabs();
    initFaq();
    initMarketingCtaTracking();
});
