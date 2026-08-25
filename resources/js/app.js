import '../css/app.css';
import '../css/shop-orange.css';
import '../css/store-footer.css';
import '../css/frontsite-typography.css';
import { createSSRApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

const appName = 'Prinx Electronics';
const formatTitle = (title) => !title
    ? appName
    : title.toLowerCase().includes(appName.toLowerCase())
        ? title
        : `${title} - ${appName}`;

const isShopCatalog = () => window.location.pathname === '/shop'
    || window.location.pathname.startsWith('/shop/category/');

const closeMobileShopFilters = () => {
    document.body.classList.remove('shop-mobile-filters-open');
    document.documentElement.style.overflow = '';
};

const openMobileShopFilters = () => {
    document.body.classList.add('shop-mobile-filters-open');
    document.documentElement.style.overflow = 'hidden';
};

const syncShopPageClass = () => {
    if (isShopCatalog()) {
        document.body.classList.add('shop-catalog-page');
        return;
    }

    document.body.classList.remove('shop-catalog-page');
    closeMobileShopFilters();
};

const installMobileShopFilters = () => {
    if (typeof window === 'undefined') return;

    syncShopPageClass();

    document.addEventListener('click', (event) => {
        if (!isShopCatalog() || !window.matchMedia('(max-width: 767px)').matches) return;

        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        const button = target?.closest?.('button');
        const filterAside = document.querySelector('body.shop-catalog-page section.container-shell.pb-5.pt-6 > div.grid > aside');

        if (button) {
            const label = button.textContent.replace(/\s+/g, ' ').trim();
            const opensFilters = Array.from(button.querySelectorAll('span'))
                .some((span) => span.textContent.trim() === 'Filters')
                && label !== 'Hide Filters';

            if (opensFilters) {
                if (filterAside) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    openMobileShopFilters();
                } else {
                    window.setTimeout(openMobileShopFilters, 0);
                }
                return;
            }

            if (label === 'Hide Filters') {
                event.preventDefault();
                event.stopImmediatePropagation();
                closeMobileShopFilters();
                return;
            }
        }

        if (filterAside && event.target === filterAside) {
            closeMobileShopFilters();
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('shop-mobile-filters-open')) {
            closeMobileShopFilters();
        }
    });

    document.addEventListener('inertia:navigate', syncShopPageClass);
    window.addEventListener('popstate', () => window.setTimeout(syncShopPageClass, 0));
};

let wishlistPage = null;
const wishlistPending = new Set();

const wishlistProps = () => wishlistPage?.props || {};
const wishlistUser = () => wishlistProps().auth?.user || null;
const wishlistCount = () => Number(wishlistProps().wishlistCount || 0);
const wishlistSlugs = () => new Set((wishlistProps().wishlistProductSlugs || []).map((slug) => String(slug)));

const productSlugFromHref = (href) => {
    if (!href) return null;
    try {
        const url = new URL(href, window.location.origin);
        const match = url.pathname.match(/^\/products\/([^/]+)\/?$/);
        return match ? decodeURIComponent(match[1]) : null;
    } catch {
        return null;
    }
};

const productSlugForWishlistButton = (button) => {
    const article = button.closest('article');
    const productLink = article?.querySelector('a[href*="/products/"]');
    const linkedSlug = productSlugFromHref(productLink?.getAttribute('href'));
    if (linkedSlug) return linkedSlug;

    const current = window.location.pathname.match(/^\/products\/([^/]+)\/?$/);
    return current ? decodeURIComponent(current[1]) : null;
};

const wishlistProductName = (button) => {
    const raw = String(button.getAttribute('aria-label') || '').trim();
    const cleaned = raw
        .replace(/^(Add|Remove)\s+/i, '')
        .replace(/^(to|from)\s+wishlist$/i, '')
        .replace(/\s+(to|from)\s+wishlist$/i, '')
        .trim();

    if (cleaned) return cleaned;
    return document.querySelector('h1')?.textContent?.trim() || 'product';
};

const setWishlistHeartState = (button, wished) => {
    const svg = button.querySelector('svg');
    if (svg) {
        svg.classList.toggle('fill-none', !wished);
        svg.classList.toggle('fill-prinx-500', wished);
        svg.classList.toggle('text-prinx-500', wished);
    }

    const slug = productSlugForWishlistButton(button);
    if (slug) button.dataset.wishlistSlug = slug;

    const productName = wishlistProductName(button);
    button.setAttribute('aria-label', wished ? `Remove ${productName} from wishlist` : `Add ${productName} to wishlist`);
};

const syncWishlistHeartButtons = () => {
    const wished = wishlistSlugs();
    document.querySelectorAll('button[aria-label*="wishlist" i]').forEach((button) => {
        const slug = productSlugForWishlistButton(button);
        if (slug) setWishlistHeartState(button, wished.has(slug));
    });
};

const ensureMobileWishlistHeader = () => {
    const mobileCart = document.querySelector('header .lg\\:hidden a[aria-label="Shopping cart"]');
    if (!mobileCart?.parentElement) return;

    let link = mobileCart.parentElement.querySelector('[data-mobile-wishlist-link]');
    if (!link) {
        link = document.createElement('a');
        link.dataset.mobileWishlistLink = '1';
        link.className = 'relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-900';
        link.title = 'Wishlist';
        link.setAttribute('aria-label', 'Wishlist');
        link.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"></path></svg><span data-wishlist-badge class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#f7b900] px-1 text-[9px] font-bold text-slate-950">0</span>';
        mobileCart.parentElement.insertBefore(link, mobileCart);
    }

    const href = wishlistUser() ? '/wishlist' : '/login';
    if (link.getAttribute('href') !== href) link.setAttribute('href', href);

    const badge = link.querySelector('[data-wishlist-badge]');
    if (badge) {
        const count = wishlistCount();
        const nextText = String(count);
        if (badge.textContent !== nextText) badge.textContent = nextText;
        badge.hidden = count < 1;
    }
};

const ensureMobileWishlistMenu = () => {
    const mobileShell = document.querySelector('header .lg\\:hidden');
    if (!mobileShell) return;

    const grids = Array.from(mobileShell.querySelectorAll('div.grid.grid-cols-2'));
    const menuGrid = grids.find((grid) => grid.querySelector('a[href="/shop"]'));
    if (!menuGrid) return;

    let link = menuGrid.querySelector('[data-mobile-wishlist-menu]');
    if (!link) {
        link = document.createElement('a');
        link.dataset.mobileWishlistMenu = '1';
        link.className = 'rounded-[8px] border border-slate-200 px-3 py-3';
        menuGrid.appendChild(link);
    }

    const href = wishlistUser() ? '/wishlist' : '/login';
    if (link.getAttribute('href') !== href) link.setAttribute('href', href);
    const label = wishlistCount() ? `Wishlist (${wishlistCount()})` : 'Wishlist';
    if (link.textContent !== label) link.textContent = label;
};

const syncWishlistNavigation = () => {
    const desktop = document.querySelector('header [title="Wishlist"]:not([data-mobile-wishlist-link])');
    if (desktop) {
        desktop.setAttribute('role', 'link');
        desktop.setAttribute('tabindex', '0');
        desktop.style.cursor = 'pointer';
        const badge = desktop.querySelector('span');
        if (badge) {
            const nextText = String(wishlistCount());
            if (badge.textContent !== nextText) badge.textContent = nextText;
        }
    }

    ensureMobileWishlistHeader();
    ensureMobileWishlistMenu();
};

const syncWishlistUi = (page = null) => {
    if (page) wishlistPage = page;
    window.setTimeout(() => {
        syncWishlistNavigation();
        syncWishlistHeartButtons();
    }, 0);
};

const installWishlist = (initialPage) => {
    if (typeof window === 'undefined') return;

    wishlistPage = initialPage || null;
    syncWishlistUi();

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        if (!target) return;

        const headerWishlist = target.closest('header [title="Wishlist"]');
        if (headerWishlist) {
            event.preventDefault();
            router.visit(wishlistUser() ? '/wishlist' : '/login');
            return;
        }

        const button = target.closest('button[aria-label*="wishlist" i]');
        if (!button) return;

        const slug = productSlugForWishlistButton(button);
        if (!slug) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        if (!wishlistUser()) {
            router.visit('/login');
            return;
        }

        if (wishlistPending.has(slug)) return;
        wishlistPending.add(slug);

        const currentlyWished = wishlistSlugs().has(slug);
        setWishlistHeartState(button, !currentlyWished);

        router.post(`/wishlist/${encodeURIComponent(slug)}`, {}, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => wishlistPending.delete(slug),
            onError: () => setWishlistHeartState(button, currentlyWished),
        });
    }, true);

    document.addEventListener('keydown', (event) => {
        if (!['Enter', ' '].includes(event.key)) return;
        const target = event.target instanceof Element ? event.target : null;
        if (!target?.matches('header [title="Wishlist"]')) return;
        event.preventDefault();
        router.visit(wishlistUser() ? '/wishlist' : '/login');
    });

    document.addEventListener('inertia:navigate', (event) => syncWishlistUi(event.detail?.page));
    document.addEventListener('inertia:success', (event) => syncWishlistUi(event.detail?.page));

    const observer = new MutationObserver(() => {
        ensureMobileWishlistHeader();
        ensureMobileWishlistMenu();
    });
    observer.observe(document.body, { childList: true, subtree: true });
};

createInertiaApp({
    title: formatTitle,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createSSRApp({ render: () => h(App, props) }).use(plugin).mount(el);
        installMobileShopFilters();
        installWishlist(props.initialPage);
    },
    progress: { color: '#ff4d0a', showSpinner: true },
});
