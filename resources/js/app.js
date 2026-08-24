import '../css/app.css';
import '../css/shop-orange.css';
import '../css/store-footer.css';
import '../css/frontsite-typography.css';
import { createSSRApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

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

        const button = event.target.closest('button');
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
                    // Let Vue restore the filter panel first if it was hidden on desktop,
                    // then present it as the mobile modal.
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

createInertiaApp({
    title: formatTitle,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createSSRApp({ render: () => h(App, props) }).use(plugin).mount(el);
        installMobileShopFilters();
    },
    progress: { color: '#ff4d0a', showSpinner: true },
});
