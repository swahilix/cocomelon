import { createSSRApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

const appName = 'Prinx Tech';
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

const syncWishlistUi = (page = null) => {
    if (page) wishlistPage = page;
    window.setTimeout(syncWishlistHeartButtons, 0);
};

const installWishlist = (initialPage) => {
    if (typeof window === 'undefined') return;

    wishlistPage = initialPage || null;
    syncWishlistUi();

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        if (!target) return;

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

    document.addEventListener('inertia:navigate', (event) => syncWishlistUi(event.detail?.page));
    document.addEventListener('inertia:success', (event) => syncWishlistUi(event.detail?.page));

};

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: formatTitle,
    resolve: async (name) => {
        const resolver = pages[`./Pages/${name}.vue`];
        if (!resolver) throw new Error(`Unknown Inertia page: ${name}`);
        return resolver();
    },
    setup({ el, App, props, plugin }) {
        createSSRApp({ render: () => h(App, props) }).use(plugin).mount(el);
        installMobileShopFilters();
        installWishlist(props.initialPage);
    },
    progress: { color: '#ff4d0a', showSpinner: true },
});
