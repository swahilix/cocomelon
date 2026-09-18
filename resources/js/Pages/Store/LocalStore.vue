<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import ProductRating from '../../Components/ProductRating.vue';

defineProps({
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
});

const page = usePage();
const store = computed(() => page.props.store || {});
const city = computed(() => store.value.city || store.value.region || 'Dodoma');
const title = computed(() => `Electronics Shop in ${city.value} | Prinx Tech`);
const description = computed(() => `Shop electronics, phones, computers and accessories in ${city.value} from Prinx Tech. Check current products, delivery, store pickup and buying guides.`);
const schema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'ElectronicsStore',
    '@id': `${store.value.url}/electronics-shop-dodoma#store`,
    name: store.value.name,
    legalName: store.value.legal_name || store.value.name,
    url: `${store.value.url}/electronics-shop-dodoma`,
    telephone: store.value.phone,
    email: store.value.email,
    image: store.value.logo,
    description: store.value.description,
    address: {
        '@type': 'PostalAddress',
        streetAddress: store.value.address || undefined,
        addressLocality: city.value,
        addressRegion: store.value.region || undefined,
        addressCountry: store.value.country,
    },
    sameAs: store.value.same_as?.length ? store.value.same_as : undefined,
}));
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: store.value.currency || 'TZS',
    maximumFractionDigits: 0,
}).format(Number(value || 0));
</script>

<template>
    <AppLayout>
        <SeoHead :title="title" :description="description" canonical="/electronics-shop-dodoma" :schema="schema" />

        <main class="bg-[#fbfbfb]">
            <section class="border-b border-slate-200 bg-white">
                <div class="container-shell py-9 sm:py-12">
                    <p class="section-kicker">Local electronics store</p>
                    <h1 class="mt-2 max-w-4xl text-[32px] font-semibold leading-[1.1] tracking-[-.03em] text-slate-950 sm:text-[42px]">Electronics Shop in {{ city }}</h1>
                    <p class="mt-4 max-w-3xl text-[13px] leading-7 text-slate-600">Prinx Tech supplies electronics, smartphones, computers, accessories and technical support. Browse live product availability online, ask us on WhatsApp, choose local delivery where available, or collect confirmed orders from the store.</p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link href="/shop" class="flex h-11 items-center rounded-[6px] bg-prinx-500 px-5 text-[11px] font-semibold text-white">Shop products</Link>
                        <Link href="/guides" class="flex h-11 items-center rounded-[6px] border border-slate-200 bg-white px-5 text-[11px] font-semibold text-slate-700">Read buying guides</Link>
                        <a v-if="store.phone" :href="`tel:${store.phone}`" class="flex h-11 items-center rounded-[6px] border border-slate-200 bg-white px-5 text-[11px] font-semibold text-slate-700">Call {{ store.phone }}</a>
                    </div>
                </div>
            </section>

            <section class="container-shell py-7">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <strong class="text-[12px] font-semibold text-slate-900">Visit Prinx Tech</strong>
                        <p class="mt-2 text-[10px] leading-5 text-slate-600">{{ store.address || city }}<template v-if="store.working_hours"><br>{{ store.working_hours }}</template></p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <strong class="text-[12px] font-semibold text-slate-900">Delivery</strong>
                        <p class="mt-2 text-[10px] leading-5 text-slate-600">{{ store.delivery_enabled ? (store.delivery_eta || 'Delivery available for confirmed orders') : 'Contact us for current delivery options.' }}<template v-if="store.delivery_area"><br>{{ store.delivery_area }}</template></p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <strong class="text-[12px] font-semibold text-slate-900">Store pickup</strong>
                        <p class="mt-2 text-[10px] leading-5 text-slate-600">{{ store.pickup_enabled ? (store.pickup_location || store.address || city) : 'Contact us to confirm collection options.' }}</p>
                    </div>
                </div>
            </section>

            <section v-if="categories.length" class="container-shell pb-7">
                <div class="flex items-end justify-between"><div><h2 class="text-[20px] font-semibold text-slate-950">Shop by category in {{ city }}</h2><p class="mt-1 text-[10px] text-slate-500">Browse current catalogue categories and availability.</p></div><Link href="/shop" class="text-[10px] font-semibold text-prinx-600">All products →</Link></div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <Link v-for="category in categories" :key="category.id" :href="`/shop/category/${category.slug}`" class="rounded-[8px] border border-slate-200 bg-white p-4 hover:border-prinx-200">
                        <img :src="category.image_url" :alt="category.name" class="h-28 w-full object-contain">
                        <strong class="mt-3 block text-[11px] text-slate-900">{{ category.name }}</strong>
                        <span class="mt-1 block text-[9px] text-slate-500">{{ category.products_count }} products</span>
                    </Link>
                </div>
            </section>

            <section v-if="products.length" class="container-shell pb-7">
                <h2 class="text-[20px] font-semibold text-slate-950">Latest products available from Prinx Tech</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <Link v-for="product in products" :key="product.id" :href="`/products/${product.slug}`" class="rounded-[8px] border border-slate-200 bg-white p-3 hover:border-prinx-200">
                        <img :src="product.image_url" :alt="product.name" class="h-32 w-full object-contain">
                        <div class="mt-2 flex min-h-[18px] items-center justify-between gap-2">
                            <span v-if="product.brand?.name" class="min-w-0 flex-1 truncate text-[8px] font-medium uppercase tracking-[.08em] text-slate-400">{{ product.brand.name }}</span>
                            <ProductRating class="shrink-0 text-[9px]" :rating="product.approved_reviews_average || 0" :count="product.approved_reviews_count || 0" />
                        </div>
                        <strong class="mt-1 line-clamp-2 block text-[10px] leading-4 text-slate-800">{{ product.name }}</strong>
                        <span class="mt-2 block text-[10px] font-semibold text-prinx-600">{{ money(product.current_price) }}</span>
                    </Link>
                </div>
            </section>

            <section v-if="guides.length" class="container-shell pb-10">
                <div class="flex items-end justify-between"><div><h2 class="text-[20px] font-semibold text-slate-950">Buying guides</h2><p class="mt-1 text-[10px] text-slate-500">Research before you buy.</p></div><Link href="/guides" class="text-[10px] font-semibold text-prinx-600">All guides →</Link></div>
                <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <Link v-for="guide in guides" :key="guide.id" :href="`/guides/${guide.slug}`" class="rounded-[8px] border border-slate-200 bg-white p-4 hover:border-prinx-200">
                        <span v-if="guide.category" class="text-[9px] font-semibold text-prinx-600">{{ guide.category.name }}</span>
                        <strong class="mt-1 line-clamp-2 block text-[12px] leading-5 text-slate-900">{{ guide.title }}</strong>
                        <p class="mt-2 line-clamp-2 text-[10px] leading-4 text-slate-500">{{ guide.excerpt }}</p>
                    </Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
