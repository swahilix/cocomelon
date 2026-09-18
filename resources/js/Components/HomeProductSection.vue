<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProductRating from './ProductRating.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    products: { type: Array, default: () => [] },
    viewAllHref: { type: String, default: '/shop' },
    badge: { type: String, default: '' },
    metricKey: { type: String, default: '' },
    metricLabel: { type: String, default: '' },
});

const page = usePage();
const currency = computed(() => page.props.store?.currency || 'TZS');

const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: currency.value,
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const discountPercent = (product) => {
    const regular = Number(product?.price || 0);
    const current = Number(product?.current_price || regular);
    return regular > 0 && current < regular ? Math.round(((regular - current) / regular) * 100) : 0;
};

function addToCart(product) {
    if (!product?.slug || !product?.in_stock) return;
    if (Number(product.active_variants_count || 0) > 0) {
        router.visit(`/products/${product.slug}`);
        return;
    }
    router.post(`/cart/${product.slug}`, { quantity: 1 }, { preserveScroll: true });
}
</script>

<template>
    <section v-if="products.length" class="bg-[#fbfbfb] pt-7">
        <div class="container-shell">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-[18px] font-semibold tracking-[-.015em] text-slate-950">{{ title }}</h2>
                        <span v-if="badge" class="rounded-[5px] bg-prinx-50 px-2 py-1 text-[8px] font-semibold uppercase tracking-[.08em] text-prinx-700">{{ badge }}</span>
                    </div>
                    <p v-if="subtitle" class="mt-1 text-[10px] text-slate-500">{{ subtitle }}</p>
                </div>
                <Link :href="viewAllHref" class="shrink-0 text-[11px] font-medium text-prinx-600 transition hover:text-prinx-700">View all <span aria-hidden="true">→</span></Link>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-[10px] sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <article v-for="product in products.slice(0, 6)" :key="product.id" class="group min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-white transition hover:border-prinx-200 hover:shadow-[0_10px_24px_rgba(15,23,42,.06)]">
                    <Link :href="`/products/${product.slug}`" class="relative block h-[156px] overflow-hidden bg-white p-3">
                        <img
                            :src="product.image_url"
                            :srcset="product.image_srcset || undefined"
                            sizes="(max-width: 639px) 100px, 180px"
                            :alt="product.name"
                            width="180"
                            height="180"
                            class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.025]"
                            loading="lazy"
                            decoding="async"
                        >
                        <span v-if="discountPercent(product)" class="absolute right-2 top-2 rounded-[5px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">-{{ discountPercent(product) }}%</span>
                    </Link>

                    <div class="px-3 pb-3">
                        <div class="flex min-h-[18px] items-center justify-between gap-2">
                            <p v-if="product.brand?.name" class="min-w-0 flex-1 truncate text-[8px] font-medium uppercase tracking-[.08em] text-slate-400">{{ product.brand.name }}</p>
                            <ProductRating class="shrink-0 text-[9px]" :rating="product.approved_reviews_average || 0" :count="product.approved_reviews_count || 0" />
                        </div>
                        <Link :href="`/products/${product.slug}`" class="mt-1 line-clamp-2 block min-h-[32px] text-[10px] font-semibold leading-4 text-slate-800 transition group-hover:text-prinx-600">{{ product.name }}</Link>

                        <p v-if="metricKey && Number(product[metricKey] || 0) > 0" class="mt-1 text-[9px] font-medium text-slate-500">
                            {{ Number(product[metricKey]).toLocaleString() }} {{ metricLabel }}
                        </p>
                        <p v-if="product.low_stock" class="mt-1 text-[9px] font-semibold text-amber-700">{{ product.stock_message }}</p>

                        <div class="mt-2 flex min-h-[30px] flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <strong class="text-[10px] font-semibold text-slate-900">{{ money(product.current_price) }}</strong>
                            <span v-if="Number(product.current_price) < Number(product.price)" class="text-[9px] text-slate-400 line-through">{{ money(product.price) }}</span>
                        </div>

                        <div class="mt-2 flex gap-2">
                            <button type="button" class="h-8 max-h-8 min-w-0 flex-1 rounded-[5px] border border-prinx-300 bg-white px-2 text-[10px] font-semibold leading-none text-prinx-600 transition hover:bg-prinx-500 hover:text-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-500" :disabled="!product.in_stock" @click="addToCart(product)">{{ Number(product.active_variants_count || 0) > 0 ? 'Select options' : 'Add to Cart' }}</button>
                            <button type="button" class="flex h-8 w-8 min-h-8 min-w-8 shrink-0 items-center justify-center rounded-[5px] border border-slate-200 bg-white text-slate-700 transition hover:border-prinx-300 hover:text-prinx-700" :aria-label="`Add ${product.name} to wishlist`">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
