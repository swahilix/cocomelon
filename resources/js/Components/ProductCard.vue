<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    product: Object,
    compactMobile: { type: Boolean, default: false },
    showDescription: { type: Boolean, default: true },
});
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(value || 0);
const add = () => router.post(`/cart/${props.product.slug}`, { quantity: 1 }, { preserveScroll: true });
</script>

<template>
    <article class="group category-card flex h-full flex-col">
        <Link :href="`/products/${product.slug}`" class="relative block aspect-square overflow-hidden bg-gradient-to-br from-slate-50 to-slate-100">
            <img :src="product.image_url" :alt="`${product.name}${product.brand?.name ? ` by ${product.brand.name}` : ``}`" :title="product.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" decoding="async">
            <div :class="['absolute inset-x-0 top-0 flex items-start justify-between', compactMobile ? 'p-2 sm:p-3' : 'p-3']">
                <span v-if="product.featured" class="rounded-[10px] bg-slate-950/90 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-white backdrop-blur">Featured</span>
                <span v-else class="inline-block"></span>
                <span v-if="Number(product.sale_price) > 0 && Number(product.current_price) < Number(product.price)" class="rounded-[10px] bg-prinx-500 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-white">Sale</span>
            </div>
            <span :class="['absolute bottom-3 left-3 rounded-[10px] px-2.5 py-1 text-[10px] font-black', product.in_stock ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">
                {{ product.in_stock ? 'In stock' : 'Out of stock' }}
            </span>
        </Link>
        <div :class="['flex flex-1 flex-col', compactMobile ? 'p-3 sm:p-5' : 'p-4 sm:p-5']">
            <div class="flex items-center justify-between gap-3">
                <p class="truncate text-[10px] font-black uppercase tracking-[.14em] text-prinx-600">{{ product.category?.name || 'Electronics' }}</p>
                <span :class="['truncate text-[11px] font-bold text-slate-400', compactMobile ? 'hidden sm:block' : '']">{{ product.brand?.name || 'Prinx' }}</span>
            </div>
            <Link :href="`/products/${product.slug}`" :class="['mt-2 line-clamp-2 block font-black text-slate-950 transition group-hover:text-prinx-600', compactMobile ? 'min-h-10 text-sm leading-5 sm:min-h-12 sm:text-base sm:leading-6' : 'min-h-12 text-base leading-6']">{{ product.name }}</Link>
            <p v-if="showDescription && product.short_description" :class="['mt-2 line-clamp-2 text-sm leading-6 text-slate-500', compactMobile ? 'hidden sm:block' : '']">{{ product.short_description }}</p>
            <div :class="['mt-auto flex items-end justify-between', compactMobile ? 'gap-2 pt-3 sm:gap-3 sm:pt-5' : 'gap-3 pt-5']">
                <div>
                    <div :class="['font-black text-slate-950', compactMobile ? 'text-sm sm:text-lg' : 'text-lg']">{{ money(product.current_price) }}</div>
                    <div v-if="Number(product.current_price) < Number(product.price)" class="text-xs font-semibold text-slate-400 line-through">{{ money(product.price) }}</div>
                </div>
                <button type="button" :class="['inline-flex shrink-0 items-center justify-center rounded-[10px] bg-slate-950 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-prinx-500 disabled:cursor-not-allowed disabled:opacity-40', compactMobile ? 'h-9 w-9 sm:h-11 sm:w-11' : 'h-11 w-11']" :disabled="!product.in_stock" @click="add" :aria-label="`Add ${product.name} to cart`">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2"><path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/><path d="M12 9v4M10 11h4"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                </button>
            </div>
        </div>
    </article>
</template>
