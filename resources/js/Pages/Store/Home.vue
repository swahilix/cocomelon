<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import HeroShowcase from '../../Components/HeroShowcase.vue';
import HomeProductSection from '../../Components/HomeProductSection.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    heroSlides: { type: Array, default: () => [] },
    newArrivals: { type: Array, default: () => [] },
    bestSellers: { type: Array, default: () => [] },
    trendingProducts: { type: Array, default: () => [] },
    dailyDeals: { type: Array, default: () => [] },
    productCategories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
});

const page = usePage();
const store = computed(() => page.props.store || {});
const newsletterEmail = ref('');
const subscribed = ref(false);

const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: store.value.currency || 'TZS',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const promoProducts = computed(() => {
    const seen = new Set();
    return [...(props.dailyDeals || []), ...(props.newArrivals || [])]
        .filter((product) => {
            if (!product?.id || seen.has(product.id)) return false;
            seen.add(product.id);
            return true;
        })
        .slice(0, 3);
});
const socialLinks = computed(() => Object.entries(store.value.social || {})
    .filter(([, url]) => Boolean(url))
    .map(([key, url]) => ({
        key,
        url,
        label: key === 'x' ? 'X' : key.charAt(0).toUpperCase() + key.slice(1),
        short: ({ facebook: 'f', instagram: '◎', youtube: '▶', tiktok: '♪', x: 'X' })[key] || key.slice(0, 1).toUpperCase(),
    })));

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
            address: {
                '@type': 'PostalAddress',
                streetAddress: store.value.address || undefined,
                addressLocality: store.value.city || store.value.region || 'Dodoma',
                addressRegion: store.value.region || undefined,
                addressCountry: store.value.country,
            },
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

function promoImage(index) {
    return promoProducts.value[index]?.image_url || props.productCategories[index]?.image_url || '/assets/accessory.svg';
}

function promoSrcset(index) {
    return promoProducts.value[index]?.image_srcset || props.productCategories[index]?.image_srcset || null;
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

</script>

<template>
    <AppLayout>
        <SeoHead
            title="Electronics, Accessories & Technical Services"
            description="Shop electronics, smartphones, computers and accessories, or request professional electronics services from Prinx Tech."
            canonical="/"
            :schema="homeSchema"
        />

        <HeroShowcase :slides="heroSlides" />

        <section class="bg-[#fbfbfb] pt-8 sm:pt-9">
            <div class="container-shell">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold text-slate-900 sm:text-[18px]">Shop By Category</h2>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-prinx-500 transition hover:text-prinx-700">View all categories <span class="text-base">→</span></Link>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <Link v-for="category in productCategories.slice(0, 7)" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="group flex min-h-[168px] flex-col rounded-[10px] border border-slate-200 bg-white px-3 pb-3 pt-3 text-center shadow-[0_4px_18px_rgba(15,23,42,.03)] transition hover:-translate-y-0.5 hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.06)]">
                        <div class="flex h-[96px] items-center justify-center overflow-hidden rounded-[8px] bg-[#fafafa]">
                            <img :src="category.image_url || '/assets/accessory.svg'" :srcset="category.image_srcset || undefined" sizes="82px" :alt="category.name" width="96" height="96" loading="lazy" decoding="async" class="h-[82px] w-[82px] object-contain transition duration-300 group-hover:scale-105">
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
                    <img :src="promoImage(index)" :srcset="promoSrcset(index) || undefined" sizes="(max-width: 1023px) 48vw, 420px" :alt="card.title" width="420" height="260" class="absolute bottom-1 right-2 h-[92%] w-[48%] object-contain drop-shadow-[0_14px_16px_rgba(15,23,42,.14)] transition duration-300 group-hover:scale-105" loading="lazy" decoding="async">
                </Link>
            </div>
        </section>

        <HomeProductSection
            title="Daily Deals"
            subtitle="Only products with a real active sale price appear here."
            :products="dailyDeals"
            badge="Live offers"
        />

        <HomeProductSection
            title="New Arrivals"
            subtitle="The newest products added to Prinx Tech."
            :products="newArrivals"
            view-all-href="/shop?sort=newest"
        />

        <HomeProductSection
            title="Best Sellers"
            subtitle="Ranked by quantity sold in completed customer orders."
            :products="bestSellers"
            metric-key="units_sold"
            metric-label="sold"
        />

        <HomeProductSection
            title="Trending Now"
            subtitle="Based on recent product views, cart interest and WhatsApp enquiries."
            :products="trendingProducts"
            metric-key="trend_score"
            metric-label="interest points"
        />

        <section class="bg-[#fbfbfb] pt-7">
            <div class="container-shell">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold text-slate-950">Top Brands</h2>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-prinx-500 transition hover:text-prinx-700">View all brands <span class="text-base">→</span></Link>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <Link v-for="brand in brands.slice(0, 8)" :key="brand.id" :href="`/shop?brand=${encodeURIComponent(brand.slug)}`" class="flex h-[72px] items-center justify-center rounded-[8px] border border-slate-200 bg-white px-4 shadow-[0_3px_14px_rgba(15,23,42,.025)] transition hover:border-prinx-200">
                        <img v-if="brandLogo(brand)" :src="brandLogo(brand)" :srcset="brand.logo_srcset || undefined" sizes="110px" :alt="brand.name" width="110" height="36" class="max-h-9 max-w-[110px] object-contain" loading="lazy" decoding="async">
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
                            <p v-if="store.address" class="flex items-start gap-2"><span>⌖</span><span>{{ store.address }}</span></p>
                        </div>
                    </div>
                </div>

                <div v-if="store.payment_methods?.length" class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-end">
                    <div class="flex flex-wrap items-center gap-2 text-[9px] text-slate-600">
                        <span class="font-semibold text-slate-800">Payment options:</span>
                        <span v-for="method in store.payment_methods" :key="method.value" class="rounded-[4px] border border-slate-200 bg-white px-2.5 py-1">{{ method.title }}</span>
                    </div>
                    <img v-if="store.payment_strip_url" :src="store.payment_strip_url" alt="Configured payment method logos" class="h-auto max-h-[32px] w-auto max-w-full object-contain">
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
main:has(.homepage-store-footer) + footer { display: none; }
</style>
