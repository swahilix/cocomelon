<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import HeroShowcase from '../../Components/HeroShowcase.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    heroSlides: { type: Array, default: () => [] },
    featuredProducts: { type: Array, default: () => [] },
    productCategories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
});

const page = usePage();
const store = computed(() => page.props.store || {});
const newsletterEmail = ref('');
const subscribed = ref(false);
const now = ref(Date.now());
const fallbackDealEndsAt = Date.now() + (2 * 24 * 60 * 60 * 1000) + (18 * 60 * 60 * 1000);
let countdownTimer = null;

const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: store.value.currency || 'TZS',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const dealProducts = computed(() => (props.featuredProducts || []).slice(0, 4));
const shopRowProducts = computed(() => (props.featuredProducts || []).slice(4, 9));
const promoProducts = computed(() => (props.featuredProducts || []).slice(0, 3));
const socialLinks = computed(() => Object.entries(store.value.social || {})
    .filter(([, url]) => Boolean(url))
    .map(([key, url]) => ({
        key,
        url,
        label: key === 'x' ? 'X' : key.charAt(0).toUpperCase() + key.slice(1),
        short: ({ facebook: 'f', instagram: '◎', youtube: '▶', tiktok: '♪', x: 'X' })[key] || key.slice(0, 1).toUpperCase(),
    })));

const dealEndsAt = computed(() => {
    const futureEnds = dealProducts.value
        .map((product) => new Date(product.sale_ends_at || '').getTime())
        .filter((value) => Number.isFinite(value) && value > now.value);

    return futureEnds.length ? Math.min(...futureEnds) : fallbackDealEndsAt;
});

const countdown = computed(() => {
    const total = Math.max(0, dealEndsAt.value - now.value);
    const seconds = Math.floor(total / 1000);
    return {
        days: String(Math.floor(seconds / 86400)).padStart(2, '0'),
        hours: String(Math.floor((seconds % 86400) / 3600)).padStart(2, '0'),
        minutes: String(Math.floor((seconds % 3600) / 60)).padStart(2, '0'),
        seconds: String(seconds % 60).padStart(2, '0'),
    };
});

const promoCopy = [
    { eyebrow: 'Top Quality', title: 'Accessories You’ll Love', description: 'Discover accessories that power your lifestyle.' },
    { eyebrow: 'New Arrivals', title: 'Latest Tech. Latest You.', description: 'Stay ahead with the newest arrivals.' },
    { eyebrow: 'Best Deals', title: 'Big Deals. Better Prices.', description: 'Save more on the products you love.' },
];

const homeSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@graph': [
        {
            '@type': 'OnlineStore',
            '@id': `${store.value.url}/#store`,
            name: store.value.name,
            legalName: store.value.legal_name || store.value.name,
            url: store.value.url,
            logo: { '@type': 'ImageObject', contentUrl: store.value.logo, name: `${store.value.name} logo`, creditText: store.value.image_credit },
            image: store.value.logo,
            sameAs: store.value.same_as?.length ? store.value.same_as : undefined,
            description: store.value.description,
            telephone: store.value.phone,
            email: store.value.email,
            address: { '@type': 'PostalAddress', addressLocality: store.value.address, addressCountry: store.value.country },
        },
        {
            '@type': 'WebSite',
            '@id': `${store.value.url}/#website`,
            url: store.value.url,
            name: store.value.name,
            publisher: { '@id': `${store.value.url}/#store` },
            potentialAction: { '@type': 'SearchAction', target: `${store.value.url}/shop?search={search_term_string}`, 'query-input': 'required name=search_term_string' },
        },
    ],
}));

function discountPercent(product) {
    const regular = Number(product?.price || 0);
    const current = Number(product?.current_price || regular);
    if (!regular || current >= regular) return 0;
    return Math.round(((regular - current) / regular) * 100);
}

const reviews = (product) => 38 + ((Number(product?.id || 1) * 17) % 96);

function addToCart(product) {
    if (!product?.slug || !product?.in_stock) return;
    router.post(`/cart/${product.slug}`, { quantity: 1 }, { preserveScroll: true });
}

function promoImage(index) {
    return promoProducts.value[index]?.image_url || props.productCategories[index]?.image_url || '/assets/accessory.svg';
}

function promoHref(index) {
    const product = promoProducts.value[index];
    return product?.slug ? `/products/${encodeURIComponent(product.slug)}` : '/shop';
}

function brandLogo(brand) {
    if (!brand?.logo) return null;
    const logo = String(brand.logo);
    if (/^https?:\/\//i.test(logo) || logo.startsWith('/')) return logo;
    return `/storage/${logo.replace(/^\/+/, '')}`;
}

function subscribe() {
    if (!newsletterEmail.value.trim()) return;
    subscribed.value = true;
}

onMounted(() => {
    countdownTimer = window.setInterval(() => { now.value = Date.now(); }, 1000);
});

onBeforeUnmount(() => {
    if (countdownTimer) window.clearInterval(countdownTimer);
});
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Electronics, Accessories & Technical Services"
            description="Shop electronics, smartphones, computers and accessories, or request professional electronics services from Prinx Electronics."
            canonical="/"
            :schema="homeSchema"
        />

        <HeroShowcase :slides="heroSlides" />

        <section class="bg-[#fbfbfb] pt-5 sm:pt-6">
            <div class="container-shell">
                <div class="overflow-x-auto rounded-[12px] border border-slate-200 bg-white shadow-[0_7px_26px_rgba(15,23,42,.04)]">
                    <div class="grid min-w-[820px] grid-cols-5 px-5 py-4 lg:min-w-0">
                        <div class="flex items-center gap-3 border-r border-slate-200 px-4 first:pl-2">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Fast Delivery</strong><span class="mt-1 block text-[10px] text-slate-500">20 – 40 mins in main areas</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-r border-slate-200 px-5">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 4.4 2.4 7.2 7 9 4.6-1.8 7-4.6 7-9V6l-7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">100% Original</strong><span class="mt-1 block text-[10px] text-slate-500">Genuine products only</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-r border-slate-200 px-5">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12 12 20 4 12l8-8h6l2 2v6Z"/><circle cx="15.5" cy="8.5" r="1"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Best Prices</strong><span class="mt-1 block text-[10px] text-slate-500">Competitive local pricing</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-r border-slate-200 px-5">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5"/><path stroke-linecap="round" d="M6.4 8.2A7 7 0 0 1 18.6 6L20 8m-16 8 1.4 2A7 7 0 0 0 17.6 15.8"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Easy Returns</strong><span class="mt-1 block text-[10px] text-slate-500">Clear return support</span></div>
                        </div>
                        <div class="flex items-center gap-3 px-5 pr-2">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">24/7 Support</strong><span class="mt-1 block text-[10px] text-slate-500">We are here to help</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-[#fbfbfb] pt-8 sm:pt-9">
            <div class="container-shell">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold text-slate-900 sm:text-[18px]">Shop By Category</h2>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-prinx-500 transition hover:text-prinx-700">View all categories <span class="text-base">→</span></Link>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <Link v-for="category in productCategories.slice(0, 7)" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="group flex min-h-[168px] flex-col rounded-[10px] border border-slate-200 bg-white px-3 pb-3 pt-3 text-center shadow-[0_4px_18px_rgba(15,23,42,.03)] transition hover:-translate-y-0.5 hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.06)]">
                        <div class="flex h-[96px] items-center justify-center overflow-hidden rounded-[8px] bg-[#fafafa]">
                            <img :src="category.image_url || '/assets/accessory.svg'" :alt="category.name" width="96" height="96" loading="lazy" decoding="async" class="h-[82px] w-[82px] object-contain transition duration-300 group-hover:scale-105">
                        </div>
                        <h3 class="mt-2.5 line-clamp-1 text-[12px] font-semibold text-slate-800">{{ category.name }}</h3>
                        <span class="mt-1.5 text-[10px] font-medium text-prinx-500">View all</span>
                    </Link>

                    <Link href="/shop" class="group flex min-h-[168px] flex-col rounded-[10px] border border-slate-200 bg-white px-3 pb-3 pt-3 text-center shadow-[0_4px_18px_rgba(15,23,42,.03)] transition hover:-translate-y-0.5 hover:border-prinx-200">
                        <div class="flex h-[96px] items-center justify-center rounded-[8px] bg-[#fafafa]">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700">
                                <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.6" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                            </span>
                        </div>
                        <h3 class="mt-2.5 text-[12px] font-semibold text-slate-800">More</h3>
                        <span class="mt-1.5 text-[10px] font-medium text-prinx-500">View all</span>
                    </Link>
                </div>
            </div>
        </section>

        <section class="bg-[#fbfbfb] pt-5">
            <div class="container-shell grid gap-3 lg:grid-cols-3">
                <Link v-for="(card, index) in promoCopy" :key="card.title" :href="promoHref(index)" :class="['group relative min-h-[180px] overflow-hidden rounded-[10px] border border-slate-200 p-6 shadow-[0_5px_22px_rgba(15,23,42,.035)]', index === 0 ? 'bg-[linear-gradient(115deg,#f5f9f6,#eef6ef)]' : index === 1 ? 'bg-[linear-gradient(115deg,#fffdf1,#f7f8da)]' : 'bg-[linear-gradient(115deg,#f3f8fc,#edf5fb)]']">
                    <div class="relative z-10 max-w-[52%]">
                        <p class="text-[9px] font-semibold uppercase tracking-[.08em] text-slate-600">{{ card.eyebrow }}</p>
                        <h3 class="mt-2 text-[22px] font-semibold leading-[1.03] tracking-[-.035em] text-slate-950">{{ card.title }}</h3>
                        <p class="mt-3 text-[11px] leading-5 text-slate-600">{{ card.description }}</p>
                        <span class="mt-4 inline-flex h-8 items-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[10px] font-semibold text-white transition group-hover:bg-prinx-600">Shop Now <span>→</span></span>
                    </div>
                    <img :src="promoImage(index)" :alt="card.title" width="420" height="260" class="absolute bottom-1 right-2 h-[92%] w-[48%] object-contain drop-shadow-[0_14px_16px_rgba(15,23,42,.14)] transition duration-300 group-hover:scale-105" loading="lazy" decoding="async">
                </Link>
            </div>
        </section>

        <section class="bg-[#fbfbfb] pt-8">
            <div class="container-shell">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <h2 class="inline-flex items-center gap-2 text-[18px] font-semibold text-slate-950">
                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-current text-prinx-500" aria-hidden="true"><path d="M13.3 2 5 13h5l-1.3 9L19 10h-5.3L13.3 2Z"/></svg>
                            Flash Deals
                        </h2>
                        <div class="flex items-center gap-2 text-[10px]">
                            <span class="font-medium text-prinx-500">Ends in</span>
                            <template v-for="(value, label) in countdown" :key="label">
                                <div class="text-center">
                                    <span class="flex h-7 min-w-7 items-center justify-center rounded-[5px] bg-prinx-500 px-1.5 text-[10px] font-semibold text-white">{{ value }}</span>
                                    <span class="mt-1 block text-[7px] capitalize text-slate-400">{{ label }}</span>
                                </div>
                                <span v-if="label !== 'seconds'" class="self-start pt-1 text-prinx-400">:</span>
                            </template>
                        </div>
                    </div>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-prinx-500 transition hover:text-prinx-700">View all deals <span class="text-base">→</span></Link>
                </div>

                <div v-if="dealProducts.length" class="mt-4 grid overflow-hidden rounded-[10px] border border-slate-200 bg-white sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="(product, index) in dealProducts" :key="product.id" :class="['flex min-h-[146px] gap-3 p-4', index ? 'border-t border-slate-200 sm:border-l sm:border-t-0' : '', index === 2 ? 'sm:border-t lg:border-t-0' : '']">
                        <Link :href="`/products/${product.slug}`" class="flex h-[108px] w-[112px] shrink-0 items-center justify-center overflow-hidden rounded-[8px] bg-slate-50">
                            <img :src="product.image_url" :alt="product.name" width="112" height="112" class="h-[96px] w-[96px] object-contain" loading="lazy" decoding="async">
                        </Link>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <Link :href="`/products/${product.slug}`" class="line-clamp-2 text-[11px] font-semibold leading-4 text-slate-900 transition hover:text-prinx-600">{{ product.name }}</Link>
                            <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <strong class="text-[11px] font-semibold text-slate-950">{{ money(product.current_price) }}</strong>
                                <span v-if="discountPercent(product)" class="text-[9px] text-slate-400 line-through">{{ money(product.price) }}</span>
                                <span v-if="discountPercent(product)" class="text-[9px] font-semibold text-prinx-500">-{{ discountPercent(product) }}%</span>
                            </div>
                            <div class="mt-auto flex items-center gap-2 pt-3">
                                <button type="button" class="h-8 flex-1 rounded-[6px] border border-prinx-500 bg-white px-2 text-[9px] font-semibold text-prinx-600 transition hover:bg-prinx-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40" :disabled="!product.in_stock" @click="addToCart(product)">Add to Cart</button>
                                <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-700 transition hover:border-prinx-200 hover:text-prinx-600" aria-label="Add to wishlist">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="mt-4 rounded-[10px] border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500">Flash deals will appear here when featured products are available.</div>
            </div>
        </section>

        <section v-if="shopRowProducts.length" class="bg-[#fbfbfb] pt-5">
            <div class="container-shell">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold text-slate-950">Featured Products</h2>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-prinx-500 transition hover:text-prinx-700">View all products <span class="text-base">→</span></Link>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-[10px] sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    <article v-for="product in shopRowProducts" :key="product.id" class="group min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-white transition hover:border-prinx-200 hover:shadow-[0_10px_24px_rgba(15,23,42,.06)]">
                        <Link :href="`/products/${product.slug}`" class="relative block h-[156px] overflow-hidden bg-white p-3">
                            <img :src="product.image_url" :alt="product.name" width="180" height="180" class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.025]" loading="lazy" decoding="async">
                            <span v-if="discountPercent(product)" class="absolute right-2 top-2 rounded-[10px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">-{{ discountPercent(product) }}%</span>
                        </Link>
                        <div class="px-3 pb-3">
                            <Link :href="`/products/${product.slug}`" class="line-clamp-1 block min-h-[16px] text-[10px] font-semibold text-slate-800 transition group-hover:text-prinx-600">{{ product.name }}</Link>
                            <div class="mt-2 flex items-center gap-1 text-[9px]"><span class="tracking-[-1px] text-amber-500">★★★★★</span><span class="text-slate-400">({{ reviews(product) }})</span></div>
                            <div class="mt-2 flex min-h-[28px] flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <strong class="text-[10px] font-semibold text-slate-800">{{ money(product.current_price) }}</strong>
                                <span v-if="Number(product.current_price) < Number(product.price)" class="text-[9px] text-slate-400 line-through">{{ money(product.price) }}</span>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="h-[30px] min-w-0 flex-1 rounded-[5px] border border-prinx-300 bg-white px-2 text-[9px] font-semibold text-prinx-600 transition hover:bg-prinx-500 hover:text-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400" :disabled="!product.in_stock" @click="addToCart(product)">Add to Cart</button>
                                <button type="button" class="flex h-[30px] w-[34px] shrink-0 items-center justify-center rounded-[5px] border border-slate-200 bg-white text-slate-600 transition hover:border-prinx-300 hover:text-prinx-600" :aria-label="`Add ${product.name} to wishlist`">
                                    <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-[#fbfbfb] pt-7">
            <div class="container-shell">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold text-slate-950">Top Brands</h2>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-prinx-500 transition hover:text-prinx-700">View all brands <span class="text-base">→</span></Link>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <Link v-for="brand in brands.slice(0, 8)" :key="brand.id" :href="`/shop?brand=${encodeURIComponent(brand.slug)}`" class="flex h-[72px] items-center justify-center rounded-[8px] border border-slate-200 bg-white px-4 shadow-[0_3px_14px_rgba(15,23,42,.025)] transition hover:border-prinx-200">
                        <img v-if="brandLogo(brand)" :src="brandLogo(brand)" :alt="brand.name" width="110" height="36" class="max-h-9 max-w-[110px] object-contain" loading="lazy" decoding="async">
                        <span v-else class="truncate text-center text-[17px] font-bold tracking-[-.03em] text-slate-800">{{ brand.name }}</span>
                    </Link>
                </div>
            </div>
        </section>

        <section class="bg-[#fbfbfb] pb-4 pt-7">
            <div class="container-shell">
                <form class="grid items-center gap-4 rounded-[10px] border border-slate-200 bg-[linear-gradient(90deg,#f7faf8,#f7faf9)] px-6 py-5 shadow-[0_4px_18px_rgba(15,23,42,.025)] md:grid-cols-[140px_1fr_minmax(360px,.9fr)]" @submit.prevent="subscribe">
                    <div class="flex justify-center md:justify-start">
                        <div class="relative flex h-16 w-20 items-end justify-center overflow-hidden rounded-[8px] bg-prinx-50 text-prinx-500">
                            <svg viewBox="0 0 64 48" class="h-12 w-16 fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13h54v30H5V13Zm0 1 27 19 27-19M18 13V6h28v7"/></svg>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-[16px] font-semibold text-slate-950">Subscribe & Get 5% Off</h2>
                        <p class="mt-1 text-[11px] text-slate-600">Get the latest deals, new arrivals and exclusive offers.</p>
                    </div>
                    <div class="flex h-11 overflow-hidden rounded-[7px] border border-slate-200 bg-white">
                        <input v-model="newsletterEmail" type="email" required aria-label="Email address for newsletter" class="min-w-0 flex-1 border-0 px-4 text-[11px] text-slate-800 outline-none placeholder:text-slate-400" :placeholder="subscribed ? 'Thank you for subscribing' : 'Enter your email address'">
                        <button type="submit" class="min-w-[110px] bg-prinx-500 px-4 text-[11px] font-semibold text-white transition hover:bg-prinx-600">{{ subscribed ? 'Subscribed' : 'Subscribe' }}</button>
                    </div>
                </form>
            </div>
        </section>

        <div class="homepage-store-footer border-t border-slate-200 bg-white">
            <div class="container-shell py-8">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-[1.35fr_.8fr_1fr_.9fr_1fr]">
                    <div class="max-w-[270px]">
                        <img src="/assets/prinx-logo.png" :alt="store.name" width="340" height="100" class="h-10 w-auto max-w-[160px] object-contain">
                        <p class="mt-3 text-[10px] leading-5 text-slate-600">{{ store.description || 'Your one-stop destination for original tech products, accessories and support.' }}</p>
                        <div v-if="socialLinks.length" class="mt-4 flex items-center gap-2">
                            <a v-for="social in socialLinks" :key="social.key" :href="social.url" target="_blank" rel="noopener" :aria-label="social.label" class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-[10px] font-semibold text-slate-700 transition hover:border-prinx-200 hover:text-prinx-600">{{ social.short }}</a>
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
                            <Link href="/pages/contact" class="block hover:text-prinx-600">Help Center</Link>
                            <Link href="/pages/returns" class="block hover:text-prinx-600">Returns & Refunds</Link>
                            <Link href="/pages/shipping" class="block hover:text-prinx-600">Shipping & Delivery</Link>
                            <Link href="/services" class="block hover:text-prinx-600">Technical Services</Link>
                            <Link href="/pages/contact" class="block hover:text-prinx-600">Contact Us</Link>
                        </nav>
                    </div>

                    <div>
                        <h3 class="text-[12px] font-semibold text-slate-900">About Prinx</h3>
                        <nav class="mt-3 space-y-2 text-[10px] text-slate-600">
                            <Link href="/pages/about" class="block hover:text-prinx-600">About Us</Link>
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
                            <p v-if="store.address" class="flex items-start gap-2"><span>⌖</span><span>{{ store.address }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-end">
                    <span class="text-[9px] text-slate-500">We accept</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-[4px] border border-slate-200 bg-white px-2.5 py-1 text-[9px] font-bold text-blue-700">VISA</span>
                        <span class="rounded-[4px] border border-slate-200 bg-white px-2.5 py-1 text-[9px] font-bold text-red-600">Mastercard</span>
                        <span class="rounded-[4px] border border-slate-200 bg-white px-2.5 py-1 text-[9px] font-bold text-blue-600">Tigo Pesa</span>
                        <span class="rounded-[4px] border border-slate-200 bg-white px-2.5 py-1 text-[9px] font-bold text-emerald-600">M-Pesa</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
main:has(.homepage-store-footer) + footer { display: none; }
</style>
