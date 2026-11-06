<script setup>
import { Link } from '@inertiajs/vue3';
import ProductCard from './ProductCard.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    products: { type: Array, default: () => [] },
    viewAllHref: { type: String, default: '/shop' },
    badge: { type: String, default: '' },
    metricKey: { type: String, default: '' },
    metricLabel: { type: String, default: '' },
});

const metricText = (product) => {
    const value = Number(props.metricKey ? product?.[props.metricKey] || 0 : 0);
    return value > 0 ? `${value.toLocaleString()} ${props.metricLabel}`.trim() : '';
};
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

            <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-6 xl:gap-4">
                <ProductCard
                    v-for="product in products.slice(0, 6)"
                    :key="product.id"
                    :product="product"
                    :metric="metricText(product)"
                    sizes="(max-width: 639px) 48vw, (max-width: 1023px) 32vw, (max-width: 1279px) 24vw, 200px"
                />
            </div>
        </div>
    </section>
</template>
