<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Flash from '../Components/Flash.vue';
import Icon from '../Components/Icon.vue';

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
                <div class="container-shell flex min-h-[32px] items-center justify-between gap-4 text-[11px] text-slate-600 sm:text-[12px]">
                    <p class="truncate">
                        <strong class="font-semibold text-slate-950">Welcome to {{ store.name || 'Prinx Electronics' }}!</strong>
                        <span class="hidden sm:inline"> Great electronics, accessories and professional services every day.</span>
                    </p>
                    <div class="hidden shrink-0 items-center divide-x divide-slate-200 lg:flex">
                        <span class="px-3 font-medium">{{ store.currency || 'TZS' }}</span>
                        <span class="inline-flex items-center gap-1 pl-3 font-medium"><span aria-hidden="true">🇬🇧</span> English <Icon name="arrow-down-1" :size="10" /></span>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block">
                <div class="container-shell flex min-h-[92px] items-center gap-5 py-4">
                    <Link href="/" class="w-[190px] shrink-0">
                        <img src="/assets/prinx-logo.png" :alt="store.name" class="h-11 w-auto max-w-[180px] object-contain">
                    </Link>

                    <form class="flex h-[44px] min-w-0 flex-1 overflow-hidden border border-slate-300 bg-white" @submit.prevent="submitSearch">
                        <select v-model="searchCategory" class="w-[170px] shrink-0 border-0 border-r border-slate-200 bg-[#f8f8f8] px-4 text-[13px] font-medium text-slate-700 outline-none">
                            <option value="">All Categories</option>
                            <option v-for="category in productCategories" :key="category.id" :value="category.slug">{{ category.name }}</option>
                        </select>
                        <input v-model="search" class="min-w-0 flex-1 border-0 px-4 text-[13px] outline-none placeholder:text-slate-400" placeholder="Search products...">
                        <button type="submit" class="flex w-[70px] shrink-0 items-center justify-center bg-prinx-500 text-white transition hover:bg-prinx-600" aria-label="Search">
                            <Icon name="search-normal" :size="19" />
                        </button>
                    </form>

                    <div class="flex shrink-0 items-center gap-3">
                        <Link href="/services" class="flex h-10 w-10 items-center justify-center text-slate-800 transition hover:text-prinx-500" title="Services">
                            <Icon name="refresh-2" :size="23" />
                        </Link>
                        <Link href="/pages/contact" class="flex h-10 w-10 items-center justify-center text-slate-800 transition hover:text-prinx-500" title="Customer support">
                            <Icon name="heart" :size="23" />
                        </Link>
                        <Link href="/cart" class="group flex items-center gap-2.5" aria-label="Shopping cart">
                            <span class="relative flex h-[46px] w-[46px] items-center justify-center rounded-full bg-prinx-500 text-white transition group-hover:bg-prinx-600">
                                <Icon name="bag-2" :size="21" />
                                <span class="absolute -right-1 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-800 px-1 text-[9px] font-semibold text-white">{{ cartCount }}</span>
                            </span>
                            <span class="whitespace-nowrap text-[13px] font-semibold text-slate-950">MY CART <span class="font-medium text-prinx-600">- {{ cartCount }} item{{ cartCount === 1 ? '' : 's' }}</span></span>
                        </Link>
                    </div>
                </div>

                <div class="border-b border-slate-200">
                    <div class="container-shell flex h-[46px] items-stretch">
                        <div class="group relative flex w-[260px] shrink-0 items-center">
                            <button type="button" class="flex h-full w-full items-center gap-2.5 pr-5 text-left text-[13px] font-bold text-slate-950">
                                <Icon name="menu" :size="20" />
                                <span>ALL CATEGORIES</span>
                                <Icon name="arrow-down-1" :size="14" class="ml-auto" />
                            </button>
                            <div class="invisible absolute left-0 top-full z-50 w-[260px] translate-y-1 border border-slate-200 bg-white py-1.5 opacity-0 shadow-xl transition group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                                <Link href="/shop" class="block px-4 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 hover:text-prinx-600">Shop all products</Link>
                                <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="block border-t border-slate-100 px-4 py-2 text-[12px] font-medium text-slate-600 hover:bg-slate-50 hover:text-prinx-600">{{ category.name }}</Link>
                            </div>
                        </div>

                        <nav class="flex min-w-0 flex-1 items-stretch gap-0.5">
                            <Link href="/" :class="['flex items-center px-4 text-[13px] font-bold transition hover:text-prinx-600', active('/') ? 'text-prinx-600' : 'text-slate-950']">HOME</Link>
                            <Link href="/shop" :class="['flex items-center px-4 text-[13px] font-bold transition hover:text-prinx-600', active('/shop') || active('/products') ? 'text-prinx-600' : 'text-slate-950']">SHOP</Link>
                            <Link href="/services" :class="['flex items-center px-4 text-[13px] font-bold transition hover:text-prinx-600', active('/services') ? 'text-prinx-600' : 'text-slate-950']">SERVICES</Link>
                            <Link href="/pages/about" class="flex items-center px-4 text-[13px] font-bold text-slate-950 transition hover:text-prinx-600">ABOUT</Link>
                            <Link href="/pages/contact" class="flex items-center px-4 text-[13px] font-bold text-slate-950 transition hover:text-prinx-600">CONTACT</Link>
                            <Link href="/shop" class="flex items-center px-4 text-[13px] font-bold text-slate-950 transition hover:text-prinx-600">SPECIALS!</Link>
                        </nav>

                        <div class="ml-auto flex shrink-0 items-center pl-3">
                            <Link v-if="user?.is_admin" href="/admin" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition hover:text-prinx-600"><Icon name="category" :size="16" />Admin panel</Link>
                            <button v-else-if="user" type="button" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition hover:text-prinx-600" @click="logout"><Icon name="logout" :size="16" />Sign out</button>
                            <Link v-else href="/login" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition hover:text-prinx-600"><Icon name="lock" :size="16" />Login or Register</Link>
                        </div>
                    </div>
                </div>

                <div class="border-b border-slate-200 bg-white">
                    <div class="container-shell flex h-[34px] items-center gap-6 overflow-hidden whitespace-nowrap text-[12px] text-slate-600">
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
                <div class="container-shell flex h-[70px] items-center gap-3">
                    <Link href="/" class="min-w-0 flex-1">
                        <img src="/assets/prinx-logo.png" :alt="store.name" class="h-9 w-auto max-w-[175px] object-contain">
                    </Link>
                    <Link href="/cart" class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-prinx-500 text-white" aria-label="Shopping cart">
                        <Icon name="bag-2" :size="20" />
                        <span v-if="cartCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-900 px-1 text-[9px] font-bold text-white">{{ cartCount }}</span>
                    </Link>
                    <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center border border-slate-200 bg-white" aria-label="Menu" @click="open = !open">
                        <Icon :name="open ? 'close-circle' : 'menu'" :size="22" />
                    </button>
                </div>

                <div class="container-shell pb-3">
                    <form class="flex h-11 overflow-hidden border border-slate-300 bg-white" @submit.prevent="submitSearch">
                        <input v-model="search" class="min-w-0 flex-1 border-0 px-3 text-[13px] outline-none" placeholder="Search products...">
                        <button type="submit" class="flex w-12 items-center justify-center bg-prinx-500 text-white" aria-label="Search"><Icon name="search-normal" :size="19" /></button>
                    </form>
                </div>

                <div v-if="open" class="border-t border-slate-200 bg-white">
                    <div class="container-shell max-h-[calc(100vh-125px)] overflow-y-auto py-3">
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
                            <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="flex items-center justify-between px-3 py-3 text-[13px] font-medium text-slate-700"><span>{{ category.name }}</span><Icon name="arrow-right-3" :size="14" /></Link>
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
