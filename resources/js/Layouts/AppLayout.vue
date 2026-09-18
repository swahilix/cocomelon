<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Flash from '../Components/Flash.vue';

const page = usePage();
const open = ref(false);
const categoryOpen = ref(false);
const search = ref('');
const user = computed(() => page.props.auth?.user);
const cartCount = computed(() => page.props.cartCount || 0);
const wishlistCount = computed(() => page.props.wishlistCount || 0);
const productCategories = computed(() => page.props.navigationCategories || []);
const store = computed(() => page.props.store || {});
const whatsappHref = computed(() => {
    const digits = String(store.value.whatsapp || '').replace(/\D/g, '');
    return digits ? `https://wa.me/${digits}` : null;
});
const socialLinks = computed(() => Object.entries(store.value.social || {})
    .filter(([, url]) => Boolean(url))
    .map(([key, url]) => {
        const label = key === 'x' ? 'X' : key.charAt(0).toUpperCase() + key.slice(1);
        const short = {
            facebook: 'F',
            instagram: 'IG',
            youtube: 'YT',
            tiktok: 'TT',
            x: 'X',
        }[key] || label.slice(0, 2).toUpperCase();

        return { key, url, label, short };
    }));
const submitSearch = () => {
    const query = search.value.trim();
    router.get('/shop', query ? { search: query } : {}, { preserveState: false });
};
const logout = () => router.post('/logout');
const active = (url) => page.url === url || (url !== '/' && page.url.startsWith(url));
</script>

<template>
    <div class="min-h-screen bg-[#fbfbfb] text-slate-900">
        <Flash />

        <header class="relative z-50 border-b border-slate-200 bg-white">
            <div class="hidden border-b border-slate-100 lg:block">
                <div class="container-shell grid h-[92px] grid-cols-[180px_180px_minmax(300px,1fr)_240px_auto] items-center gap-5">
                    <Link href="/" class="flex min-w-0 items-center">
                        <picture><source type="image/webp" srcset="/assets/prinx-logo-180.webp 180w, /assets/prinx-logo-360.webp 360w" sizes="170px"><img src="/assets/prinx-logo.png" :alt="store.name" width="340" height="100" class="h-[46px] w-auto max-w-[170px] object-contain"></picture>
                    </Link>

                    <div class="relative">
                        <button type="button" class="flex h-[50px] w-full items-center gap-3 rounded-[10px] bg-[#f7b900] px-4 text-left text-[13px] font-semibold text-slate-950 shadow-sm transition hover:bg-[#eeb200]" aria-controls="desktop-category-menu" :aria-expanded="categoryOpen ? 'true' : 'false'" @click="categoryOpen = !categoryOpen">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                            <span class="min-w-0 flex-1 truncate">All Categories</span>
                            <svg viewBox="0 0 20 20" class="h-3.5 w-3.5 fill-current transition" :class="categoryOpen ? 'rotate-180' : ''" aria-hidden="true"><path d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4Z"/></svg>
                        </button>
                        <div v-if="categoryOpen" id="desktop-category-menu" class="absolute left-0 top-[58px] z-50 w-[280px] overflow-hidden rounded-[9px] border border-slate-200 bg-white py-1.5 shadow-[0_18px_50px_rgba(15,23,42,.14)]">
                            <Link href="/shop" class="block px-4 py-2.5 text-[12px] font-semibold text-slate-700 transition hover:bg-amber-50 hover:text-amber-700" @click="categoryOpen = false">Shop all products</Link>
                            <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="block border-t border-slate-100 px-4 py-2.5 text-[12px] text-slate-600 transition hover:bg-amber-50 hover:text-amber-700" @click="categoryOpen = false">{{ category.name }}</Link>
                        </div>
                    </div>

                    <form class="flex h-[50px] min-w-0 items-center rounded-[10px] border border-slate-200 bg-white px-4 shadow-[0_3px_15px_rgba(15,23,42,.03)] transition focus-within:border-amber-300" @submit.prevent="submitSearch">
                        <input v-model="search" aria-label="Search products" class="min-w-0 flex-1 border-0 bg-transparent text-[13px] text-slate-800 outline-none placeholder:text-slate-400" placeholder="Search for products...">
                        <button type="submit" class="ml-3 flex h-11 w-11 shrink-0 items-center justify-center text-slate-600 transition hover:text-amber-700" aria-label="Search">
                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                        </button>
                    </form>

                    <a v-if="store.phone" :href="`tel:${store.phone}`" class="flex min-w-0 items-center gap-3 rounded-[9px] px-2 py-2 transition hover:bg-slate-50">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center text-slate-950">
                            <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/><path stroke-linecap="round" d="M18 20c-1 1-2.3 1.5-4 1.5"/></svg>
                        </span>
                        <span class="min-w-0 leading-tight"><span class="block text-[11px] font-semibold text-slate-600">Support</span><strong class="mt-1 block truncate text-[12px] font-medium text-slate-800">{{ store.phone }}</strong></span>
                    </a>
                    <div v-else class="flex items-center gap-3 px-2">
                        <span class="flex h-9 w-9 items-center justify-center text-slate-950"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg></span>
                        <span class="leading-tight"><span class="block text-[11px] font-semibold text-slate-600">Support</span><strong class="mt-1 block text-[12px] font-medium text-slate-800">Contact us</strong></span>
                    </div>

                    <div class="flex items-center justify-end gap-1.5">
                        <Link v-if="user?.is_admin" href="/admin" class="flex h-11 w-11 items-center justify-center rounded-full text-slate-900 transition hover:bg-amber-50 hover:text-amber-700" title="Admin panel" aria-label="Admin panel">
                            <svg viewBox="0 0 24 24" class="h-[22px] w-[22px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M5 20c.7-4.2 3-6.3 7-6.3s6.3 2.1 7 6.3"/></svg>
                        </Link>
                        <Link v-else :href="user ? '/account' : '/login'" class="flex h-11 w-11 items-center justify-center rounded-full text-slate-900 transition hover:bg-amber-50 hover:text-amber-700" title="Account" aria-label="Account">
                            <svg viewBox="0 0 24 24" class="h-[22px] w-[22px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M5 20c.7-4.2 3-6.3 7-6.3s6.3 2.1 7 6.3"/></svg>
                        </Link>

                        <Link :href="user ? '/wishlist' : '/login'" class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-900 transition hover:bg-amber-50 hover:text-amber-700" title="Wishlist" aria-label="Wishlist">
                            <svg viewBox="0 0 24 24" class="h-[22px] w-[22px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                            <span v-if="wishlistCount" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#f7b900] px-1 text-[9px] font-bold text-slate-950">{{ wishlistCount }}</span>
                        </Link>

                        <Link href="/cart" class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-900 transition hover:bg-amber-50 hover:text-amber-700" title="Cart" aria-label="Shopping cart">
                            <svg viewBox="0 0 24 24" class="h-[23px] w-[23px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2 10h10l2-7H7M9 19.2a1.2 1.2 0 1 0 0 .1Zm7 0a1.2 1.2 0 1 0 0 .1Z"/></svg>
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#f7b900] px-1 text-[8px] font-bold text-slate-950">{{ cartCount }}</span>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="hidden border-b border-slate-100 lg:block">
                <div class="container-shell flex h-[66px] items-center justify-between gap-6">
                    <nav class="flex h-full items-stretch gap-1 text-[12px] font-medium text-slate-700">
                        <Link href="/" :class="['relative flex items-center gap-2 px-2.5 transition hover:text-amber-600', active('/') ? 'text-amber-600' : '']">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 10 8-7 8 7v10h-6v-6h-4v6H4V10Z"/></svg>Home
                            <span v-if="active('/')" class="absolute inset-x-2 bottom-0 h-[2px] bg-[#f7b900]"></span>
                        </Link>
                        <Link href="/shop" :class="['flex items-center gap-2 px-3 transition hover:text-amber-600', active('/shop') || active('/products') ? 'text-amber-600' : '']">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14l-1 12H6L5 8Zm3 0a4 4 0 0 1 8 0"/></svg>Shop
                        </Link>
                        <Link href="/software" :class="['relative flex items-center gap-2 px-3 transition hover:text-amber-600', active('/software') ? 'text-amber-600' : '']">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>Software
                            <span v-if="active('/software')" class="absolute inset-x-2 bottom-0 h-[2px] bg-[#f7b900]"></span>
                        </Link>
                        <Link href="/services" :class="['flex items-center gap-2 px-3 transition hover:text-amber-600', active('/services') ? 'text-amber-600' : '']">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg>Services
                        </Link>
                        <Link href="/shop" class="flex items-center gap-2 px-3 transition hover:text-amber-600">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 12 8-8 8 8-8 8-8-8Z"/><path stroke-linecap="round" d="M9 12h6"/></svg>Offers
                        </Link>
                        <Link href="/guides" :class="['flex items-center gap-2 px-3 transition hover:text-amber-600', active('/guides') ? 'text-amber-600' : '']">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14v16H5V4Zm3 4h8M8 12h8M8 16h5"/></svg>Guides
                        </Link>
                        <Link href="/pages/about" class="flex items-center gap-2 px-3 transition hover:text-amber-600">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5M12 8h.01"/></svg>About Us
                        </Link>
                        <Link href="/pages/contact" class="flex items-center gap-2 px-3 transition hover:text-amber-600">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4V6Zm1 1 7 6 7-6"/></svg>Contact Us
                        </Link>
                    </nav>

                    <div class="inline-flex h-[40px] shrink-0 items-center gap-2.5 rounded-[9px] border border-slate-200 bg-white px-4 text-[11px] font-semibold text-slate-700 shadow-sm">
                        <svg viewBox="0 0 24 24" class="h-[19px] w-[19px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        <span v-if="store.delivery_enabled">Delivery <strong class="text-amber-500">{{ store.delivery_eta || 'available' }}</strong></span><span v-else-if="store.pickup_enabled"><strong class="text-amber-500">Store pickup available</strong></span><span v-else>Order assistance available</span>
                    </div>
                </div>
            </div>

            <div class="lg:hidden">
                <div class="container-shell flex h-[64px] items-center gap-3">
                    <Link href="/" class="min-w-0 flex-1"><picture><source type="image/webp" srcset="/assets/prinx-logo-180.webp 180w, /assets/prinx-logo-360.webp 360w" sizes="150px"><img src="/assets/prinx-logo.png" :alt="store.name" width="340" height="100" class="h-9 w-auto max-w-[165px] object-contain"></picture></Link>
                    <Link data-mobile-wishlist-link :href="user ? '/wishlist' : '/login'" class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-900" aria-label="Wishlist">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                        <span v-if="wishlistCount" data-wishlist-badge class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#f7b900] px-1 text-[9px] font-bold text-slate-950">{{ wishlistCount }}</span>
                    </Link>
                    <Link href="/cart" class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-900" aria-label="Shopping cart">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2 10h10l2-7H7M9 19.2a1.2 1.2 0 1 0 0 .1Zm7 0a1.2 1.2 0 1 0 0 .1Z"/></svg>
                        <span v-if="cartCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#f7b900] px-1 text-[9px] font-bold text-slate-950">{{ cartCount }}</span>
                    </Link>
                    <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-800" aria-label="Menu" aria-controls="mobile-navigation" :aria-expanded="open ? 'true' : 'false'" @click="open = !open">
                        <svg v-if="!open" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <svg v-else viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="container-shell pb-3">
                    <form class="flex h-11 overflow-hidden rounded-[8px] border border-slate-200 bg-white" @submit.prevent="submitSearch">
                        <input v-model="search" aria-label="Search products" class="min-w-0 flex-1 border-0 px-3 text-[13px] outline-none" placeholder="Search for products...">
                        <button type="submit" class="flex w-12 items-center justify-center bg-[#f7b900] text-slate-950" aria-label="Search"><svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg></button>
                    </form>
                </div>

                <div v-if="open" id="mobile-navigation" class="border-t border-slate-100 bg-white">
                    <div class="container-shell max-h-[calc(100vh-120px)] overflow-y-auto py-3">
                        <div class="grid grid-cols-2 gap-2 text-[13px] font-medium">
                            <Link href="/" class="rounded-[8px] border border-slate-200 px-3 py-3">Home</Link>
                            <Link href="/shop" class="rounded-[8px] border border-slate-200 px-3 py-3">Shop</Link>
                            <Link href="/software" class="rounded-[8px] border border-slate-200 px-3 py-3">Software</Link>
                            <Link href="/services" class="rounded-[8px] border border-slate-200 px-3 py-3">Services</Link>
                            <Link href="/guides" class="rounded-[8px] border border-slate-200 px-3 py-3">Buying Guides</Link>
                            <Link href="/pages/about" class="rounded-[8px] border border-slate-200 px-3 py-3">About</Link>
                            <Link href="/pages/contact" class="rounded-[8px] border border-slate-200 px-3 py-3">Contact</Link>
                            <Link v-if="!user" href="/login" class="rounded-[8px] border border-slate-200 px-3 py-3">Account</Link>
                            <Link v-else-if="user?.is_admin" href="/admin" class="rounded-[8px] border border-slate-200 px-3 py-3">Admin panel</Link>
                            <Link v-else href="/account" class="rounded-[8px] border border-slate-200 px-3 py-3">My Account</Link>
                            <Link data-mobile-wishlist-menu :href="user ? '/wishlist' : '/login'" class="rounded-[8px] border border-slate-200 px-3 py-3">Wishlist<span v-if="wishlistCount"> ({{ wishlistCount }})</span></Link>
                        </div>
                        <p class="mt-4 text-[10px] font-bold uppercase tracking-[.14em] text-slate-400">All Categories</p>
                        <div class="mt-2 divide-y divide-slate-100 overflow-hidden rounded-[8px] border border-slate-200">
                            <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="flex items-center justify-between px-3 py-3 text-[13px] text-slate-700"><span>{{ category.name }}</span><span>›</span></Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main><slot /></main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="container-shell py-8">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-[1.35fr_.8fr_1fr_.9fr_1fr]">
                    <div class="max-w-[270px]">
                        <picture><source type="image/webp" srcset="/assets/prinx-logo-180.webp 180w, /assets/prinx-logo-360.webp 360w" sizes="160px"><img src="/assets/prinx-logo.png" :alt="store.name" width="340" height="100" class="h-10 w-auto max-w-[160px] object-contain"></picture>
                        <p class="mt-3 text-[10px] leading-5 text-slate-600">{{ store.description || 'Your one-stop destination for original tech products, accessories and support.' }}</p>
                        <div v-if="socialLinks.length" class="mt-4 flex items-center gap-2">
                            <a v-for="social in socialLinks" :key="social.key" :href="social.url" target="_blank" rel="noopener" :aria-label="social.label" class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-[12px] font-semibold text-slate-700 transition hover:border-prinx-200 hover:text-prinx-700">{{ social.short }}</a>
                        </div>
                        <p class="mt-5 text-[9px] text-slate-400">© {{ store.year }} {{ store.name }}. All rights reserved.</p>
                    </div>

                    <div>
                        <h3 class="text-[12px] font-semibold text-slate-900">Shop</h3>
                        <nav class="mt-3 space-y-2 text-[10px] text-slate-600">
                            <Link href="/shop" class="block hover:text-prinx-600">All Products</Link>
                            <Link href="/shop?sort=newest" class="block hover:text-prinx-600">New Arrivals</Link>
                            <Link href="/shop" class="block hover:text-prinx-600">Best Sellers</Link>
                            <Link href="/shop" class="block hover:text-prinx-600">Deals Of The Day</Link>
                            <Link href="/cart" class="block hover:text-prinx-600">My Cart</Link>
                        </nav>
                    </div>

                    <div>
                        <h3 class="text-[12px] font-semibold text-slate-900">Customer Service</h3>
                        <nav class="mt-3 space-y-2 text-[10px] text-slate-600">
                            <Link href="/pages/faq" class="block hover:text-prinx-600">FAQ</Link>
                            <Link href="/pages/warranty" class="block hover:text-prinx-600">Warranty Information</Link>
                            <Link href="/pages/returns" class="block hover:text-prinx-600">Returns & Refunds</Link>
                            <Link href="/pages/shipping" class="block hover:text-prinx-600">Delivery & Store Pickup</Link>
                            <Link href="/pages/contact" class="block hover:text-prinx-600">Contact Us</Link>
                        </nav>
                    </div>

                    <div>
                        <h3 class="text-[12px] font-semibold text-slate-900">About Prinx</h3>
                        <nav class="mt-3 space-y-2 text-[10px] text-slate-600">
                            <Link href="/pages/about" class="block hover:text-prinx-600">About Us</Link>
                            <Link href="/electronics-shop-dodoma" class="block hover:text-prinx-600">Prinx Tech Dodoma</Link>
                            <Link href="/guides" class="block hover:text-prinx-600">Buying Guides</Link>
                            <Link href="/services" class="block hover:text-prinx-600">Our Services</Link>
                            <Link href="/pages/terms" class="block hover:text-prinx-600">Terms & Conditions</Link>
                            <Link href="/pages/privacy" class="block hover:text-prinx-600">Privacy Policy</Link>
                        </nav>
                    </div>

                    <div>
                        <h3 class="text-[12px] font-semibold text-slate-900">Contact Us</h3>
                        <div class="mt-3 space-y-3 text-[10px] text-slate-600">
                            <a v-if="store.phone" :href="`tel:${store.phone}`" class="flex items-center gap-2 hover:text-prinx-600"><span>☎</span><span>{{ store.phone }}</span></a>
                            <a v-if="store.email" :href="`mailto:${store.email}`" class="flex items-center gap-2 hover:text-prinx-600"><span>✉</span><span>{{ store.email }}</span></a>
                            <p v-if="store.address" class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                                    <path d="M12 13.4295C13.7231 13.4295 15.12 12.0326 15.12 10.3095C15.12 8.58633 13.7231 7.18945 12 7.18945C10.2769 7.18945 8.88 8.58633 8.88 10.3095C8.88 12.0326 10.2769 13.4295 12 13.4295Z" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M3.61995 8.49C5.58995 -0.169998 18.42 -0.159997 20.38 8.5C21.53 13.58 18.37 17.88 15.6 20.54C13.59 22.48 10.41 22.48 8.38995 20.54C5.62995 17.88 2.46995 13.57 3.61995 8.49Z" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                                <span>{{ store.address }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="store.payment_methods?.length" class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-end">
                    <div class="flex flex-wrap items-center gap-2 text-[10px] text-slate-600">
                        <span class="font-semibold text-slate-800">Payment options:</span>
                        <span v-for="method in store.payment_methods" :key="method.value" class="rounded-[5px] bg-slate-100 px-2 py-1">{{ method.title }}</span>
                    </div>
                    <img v-if="store.payment_strip_url" :src="store.payment_strip_url" alt="Configured payment method logos" width="360" height="70" loading="lazy" decoding="async" class="h-auto max-h-[35px] w-auto max-w-full object-contain">
                </div>
            </div>
        </footer>
    </div>
</template>