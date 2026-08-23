<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Flash from '../Components/Flash.vue';

const page = usePage();
const open = ref(false);
const search = ref('');
const searchCategory = ref('');
const user = computed(() => page.props.auth?.user);
const cartCount = computed(() => page.props.cartCount || 0);
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
    const params = {};
    if (search.value.trim()) params.search = search.value.trim();
    if (searchCategory.value) params.category = searchCategory.value;
    router.get('/shop', params, { preserveState: false });
};
const logout = () => router.post('/logout');
const active = (url) => page.url === url || (url !== '/' && page.url.startsWith(url));
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <Flash />

        <header class="border-t border-prinx-100 bg-white text-slate-900">
            <div class="border-b border-slate-200 bg-[#f7f7f7]">
                <div class="container-shell flex h-[34px] items-center justify-between gap-4 text-[11px] text-slate-600 sm:text-[12px]">
                    <p class="min-w-0 truncate">
                        <strong class="font-semibold text-slate-950">Welcome to {{ store.name || 'Prinx Electronics' }}!</strong>
                        <span class="hidden sm:inline"> Great electronics, accessories and professional services every day.</span>
                    </p>
                    <div class="hidden shrink-0 items-center text-[11px] lg:flex">
                        <span class="border-r border-slate-200 px-4 font-medium">{{ store.currency || 'TZS' }}</span>
                        <span class="inline-flex items-center gap-1.5 pl-4 font-medium"><span class="text-[10px] font-semibold">GB</span> English
                            <svg viewBox="0 0 20 20" class="h-3 w-3 fill-current" aria-hidden="true"><path d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4Z"/></svg>
                        </span>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block">
                <div class="container-shell grid min-h-[92px] grid-cols-[230px_minmax(420px,1fr)_auto] items-center gap-7 py-4">
                    <Link href="/" class="flex items-center">
                        <img src="/assets/prinx-logo.png" :alt="store.name" class="h-[52px] w-auto max-w-[220px] object-contain">
                    </Link>

                    <form class="flex h-[46px] min-w-0 overflow-hidden border border-slate-300 bg-white" @submit.prevent="submitSearch">
                        <select v-model="searchCategory" class="w-[180px] shrink-0 border-0 border-r border-slate-200 bg-[#f8f8f8] px-4 text-[13px] font-medium text-slate-700 outline-none">
                            <option value="">All Categories</option>
                            <option v-for="category in productCategories" :key="category.id" :value="category.slug">{{ category.name }}</option>
                        </select>
                        <input v-model="search" class="min-w-0 flex-1 border-0 px-4 text-[13px] outline-none placeholder:text-slate-400" placeholder="Search products...">
                        <button type="submit" class="flex w-[62px] shrink-0 items-center justify-center bg-prinx-500 text-white transition hover:bg-prinx-600" aria-label="Search">
                            <svg viewBox="0 0 24 24" class="h-[19px] w-[19px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                        </button>
                    </form>

                    <div class="flex shrink-0 items-center gap-2.5">
                        <Link href="/services" class="flex h-10 w-10 items-center justify-center text-slate-800 transition hover:text-prinx-500" title="Services" aria-label="Services">
                            <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5"/><path stroke-linecap="round" d="M6.4 8.2A7 7 0 0 1 18.6 6L20 8m-16 8 1.4 2A7 7 0 0 0 17.6 15.8"/></svg>
                        </Link>
                        <Link href="/pages/contact" class="flex h-10 w-10 items-center justify-center text-slate-800 transition hover:text-prinx-500" title="Customer support" aria-label="Customer support">
                            <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                        </Link>
                        <Link href="/cart" class="group ml-1 flex items-center gap-2.5" aria-label="Shopping cart">
                            <span class="relative flex h-[46px] w-[46px] items-center justify-center rounded-full bg-prinx-500 text-white transition group-hover:bg-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-[20px] w-[20px] fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 9V7a5 5 0 0 1 10 0v2M5.5 9h13l-1 11h-11l-1-11Z"/></svg>
                                <span class="absolute -right-1 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-800 px-1 text-[9px] font-semibold text-white">{{ cartCount }}</span>
                            </span>
                            <span class="whitespace-nowrap text-[13px] font-semibold text-slate-950">MY CART <span class="font-medium text-prinx-600">- {{ cartCount }} item{{ cartCount === 1 ? '' : 's' }}</span></span>
                        </Link>
                    </div>
                </div>

                <div class="border-b border-slate-200">
                    <div class="container-shell flex h-[48px] items-stretch">
                        <div class="group relative flex w-[280px] shrink-0 items-center">
                            <button type="button" class="flex h-full w-full items-center gap-3 pr-6 text-left text-[13px] font-bold text-slate-950">
                                <svg viewBox="0 0 24 24" class="h-[19px] w-[19px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                                <span>ALL CATEGORIES</span>
                                <svg viewBox="0 0 20 20" class="ml-auto h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4Z"/></svg>
                            </button>
                            <div class="invisible absolute left-0 top-full z-50 w-[280px] translate-y-1 border border-slate-200 bg-white py-1.5 opacity-0 shadow-xl transition group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                                <Link href="/shop" class="block px-4 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 hover:text-prinx-600">Shop all products</Link>
                                <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="block border-t border-slate-100 px-4 py-2 text-[12px] font-medium text-slate-600 hover:bg-slate-50 hover:text-prinx-600">{{ category.name }}</Link>
                            </div>
                        </div>

                        <nav class="flex min-w-0 flex-1 items-stretch">
                            <Link href="/" :class="['flex items-center px-[18px] text-[13px] font-bold transition hover:text-prinx-600', active('/') ? 'text-prinx-600' : 'text-slate-950']">HOME</Link>
                            <Link href="/shop" :class="['flex items-center px-[18px] text-[13px] font-bold transition hover:text-prinx-600', active('/shop') || active('/products') ? 'text-prinx-600' : 'text-slate-950']">SHOP</Link>
                            <Link href="/services" :class="['flex items-center px-[18px] text-[13px] font-bold transition hover:text-prinx-600', active('/services') ? 'text-prinx-600' : 'text-slate-950']">SERVICES</Link>
                            <Link href="/pages/about" class="flex items-center px-[18px] text-[13px] font-bold text-slate-950 transition hover:text-prinx-600">ABOUT</Link>
                            <Link href="/pages/contact" class="flex items-center px-[18px] text-[13px] font-bold text-slate-950 transition hover:text-prinx-600">CONTACT</Link>
                            <Link href="/shop" class="flex items-center px-[18px] text-[13px] font-bold text-slate-950 transition hover:text-prinx-600">SPECIALS!</Link>
                        </nav>

                        <div class="ml-auto flex shrink-0 items-center pl-5">
                            <Link v-if="user?.is_admin" href="/admin" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition hover:text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20v-8h16v8M7 12V6h10v6M9 9h6M8 16h3m2 0h3"/></svg>
                                Admin panel
                            </Link>
                            <button v-else-if="user" type="button" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition hover:text-prinx-600" @click="logout">Sign out</button>
                            <Link v-else href="/login" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition hover:text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><rect x="6" y="10" width="12" height="10" rx="2"/><path stroke-linecap="round" d="M9 10V7a3 3 0 0 1 6 0v3"/></svg>
                                Login or Register
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="border-b border-slate-200 bg-[#fafafa]">
                    <div class="container-shell flex h-[36px] items-center gap-6 overflow-hidden whitespace-nowrap text-[12px] text-slate-600">
                        <strong class="shrink-0 font-bold text-slate-950">Top Categories:</strong>
                        <Link v-for="category in productCategories.slice(0, 10)" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="shrink-0 transition hover:text-prinx-600">{{ category.name }}</Link>
                        <Link v-if="!productCategories.length" href="/shop" class="transition hover:text-prinx-600">Electronics</Link>
                        <Link v-if="!productCategories.length" href="/shop" class="transition hover:text-prinx-600">Accessories</Link>
                        <Link v-if="!productCategories.length" href="/shop" class="transition hover:text-prinx-600">Smartphones</Link>
                        <Link v-if="!productCategories.length" href="/shop" class="transition hover:text-prinx-600">Laptops</Link>
                    </div>
                </div>
            </div>

            <div class="lg:hidden">
                <div class="container-shell flex h-[64px] items-center gap-3">
                    <Link href="/" class="min-w-0 flex-1">
                        <img src="/assets/prinx-logo.png" :alt="store.name" class="h-9 w-auto max-w-[170px] object-contain">
                    </Link>
                    <Link href="/cart" class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-prinx-500 text-white" aria-label="Shopping cart">
                        <svg viewBox="0 0 24 24" class="h-[19px] w-[19px] fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 9V7a5 5 0 0 1 10 0v2M5.5 9h13l-1 11h-11l-1-11Z"/></svg>
                        <span v-if="cartCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-900 px-1 text-[9px] font-bold text-white">{{ cartCount }}</span>
                    </Link>
                    <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center border border-slate-200 bg-white text-slate-800" aria-label="Menu" @click="open = !open">
                        <svg v-if="!open" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <svg v-else viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="container-shell pb-3">
                    <form class="flex h-11 overflow-hidden border border-slate-300 bg-white" @submit.prevent="submitSearch">
                        <input v-model="search" class="min-w-0 flex-1 border-0 px-3 text-[13px] outline-none" placeholder="Search products...">
                        <button type="submit" class="flex w-12 items-center justify-center bg-prinx-500 text-white" aria-label="Search">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                        </button>
                    </form>
                </div>

                <div v-if="open" class="border-t border-slate-200 bg-white">
                    <div class="container-shell max-h-[calc(100vh-120px)] overflow-y-auto py-3">
                        <div class="grid grid-cols-2 gap-2 text-[13px] font-semibold">
                            <Link href="/" class="border border-slate-200 px-3 py-3">Home</Link>
                            <Link href="/shop" class="border border-slate-200 px-3 py-3">Shop all</Link>
                            <Link href="/services" class="border border-slate-200 px-3 py-3">Services</Link>
                            <Link href="/pages/about" class="border border-slate-200 px-3 py-3">About</Link>
                            <Link href="/pages/contact" class="border border-slate-200 px-3 py-3">Contact</Link>
                            <Link v-if="!user" href="/login" class="border border-slate-200 px-3 py-3">Login / Register</Link>
                            <Link v-if="user?.is_admin" href="/admin" class="border border-slate-200 px-3 py-3">Admin panel</Link>
                            <button v-else-if="user" type="button" class="border border-slate-200 px-3 py-3 text-left" @click="logout">Sign out</button>
                        </div>
                        <p class="mt-4 text-[11px] font-bold uppercase tracking-[.14em] text-slate-400">Categories</p>
                        <div class="mt-2 divide-y divide-slate-100 border border-slate-200">
                            <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="flex items-center justify-between px-3 py-3 text-[13px] font-medium text-slate-700">
                                <span>{{ category.name }}</span>
                                <svg viewBox="0 0 20 20" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M7.3 4.6a1 1 0 0 1 1.4 0l4.7 4.7a1 1 0 0 1 0 1.4l-4.7 4.7A1 1 0 1 1 7.3 14l4-4-4-4a1 1 0 0 1 0-1.4Z"/></svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main><slot /></main>

        <footer class="mt-20 border-t border-slate-800 bg-slate-950 text-slate-300">
            <div class="container-shell py-14 sm:py-16">
                <div class="grid gap-12 md:grid-cols-2 lg:grid-cols-[1.45fr_.8fr_.9fr_.9fr] lg:gap-10 xl:gap-16">
                    <div class="max-w-md">
                        <img src="/assets/prinx-logo.png" :alt="store.name" class="h-11 w-auto brightness-0 invert sm:h-12">
                        <p class="mt-5 max-w-sm text-sm leading-7 text-slate-400">{{ store.description }}</p>

                        <div v-if="socialLinks.length" class="mt-7 flex flex-wrap items-center">
                            <template v-for="(social, index) in socialLinks" :key="social.key">
                                <span v-if="index" class="mx-3 h-5 w-px bg-slate-800"></span>
                                <a
                                    :href="social.url"
                                    target="_blank"
                                    rel="noopener"
                                    :aria-label="social.label"
                                    :title="social.label"
                                    class="inline-flex min-w-6 items-center justify-center text-[12px] font-semibold tracking-[.08em] text-slate-400 transition hover:text-prinx-400"
                                >{{ social.short }}</a>
                            </template>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-white">Company</h3>
                        <nav class="mt-6 space-y-4 text-sm text-slate-400">
                            <Link href="/pages/about" class="block transition hover:translate-x-0.5 hover:text-white">About Us</Link>
                            <Link href="/shop" class="block transition hover:translate-x-0.5 hover:text-white">Shop</Link>
                            <Link href="/services" class="block transition hover:translate-x-0.5 hover:text-white">Services</Link>
                            <Link href="/pages/contact" class="block transition hover:translate-x-0.5 hover:text-white">Contact Us</Link>
                        </nav>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-white">Support</h3>
                        <nav class="mt-6 space-y-4 text-sm text-slate-400">
                            <Link href="/pages/contact" class="block transition hover:translate-x-0.5 hover:text-white">Customer Support</Link>
                            <Link href="/services" class="block transition hover:translate-x-0.5 hover:text-white">Technical Services</Link>
                            <Link href="/pages/shipping" class="block transition hover:translate-x-0.5 hover:text-white">Shipping & Delivery</Link>
                            <Link href="/pages/returns" class="block transition hover:translate-x-0.5 hover:text-white">Returns & Refunds</Link>
                            <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener" class="block transition hover:translate-x-0.5 hover:text-white">WhatsApp Support</a>
                        </nav>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-white">Legal Policies</h3>
                        <nav class="mt-6 space-y-4 text-sm text-slate-400">
                            <Link href="/pages/terms" class="block transition hover:translate-x-0.5 hover:text-white">Terms & Conditions</Link>
                            <Link href="/pages/privacy" class="block transition hover:translate-x-0.5 hover:text-white">Privacy Policy</Link>
                            <Link href="/pages/returns" class="block transition hover:translate-x-0.5 hover:text-white">Refund Policy</Link>
                            <a href="/sitemap.xml" class="block transition hover:translate-x-0.5 hover:text-white">Sitemap</a>
                        </nav>
                    </div>
                </div>

                <div class="mt-12 border-t border-slate-800 pt-6 text-center text-xs leading-5 text-slate-500">
                    Copyright © {{ store.year }} {{ store.name }}. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
