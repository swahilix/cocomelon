<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({ products: { type: Array, default: () => [] } });
const page = usePage();
const currency = computed(() => page.props.store?.currency || 'TZS');
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: currency.value,
    maximumFractionDigits: 0,
}).format(Number(value || 0));
const specs = computed(() => [...new Set(props.products.flatMap((product) => Object.keys(product.specifications || {})))]);
const rating = (product) => Number(product.approved_reviews_count || 0)
    ? `${Number(product.approved_reviews_average || 0).toFixed(1)} / 5 (${product.approved_reviews_count})`
    : 'No reviews yet';
const variantSummary = (product) => (product.variants || []).map((variant) => variant.display_name).join(', ') || 'No variants';
</script>

<template>
    <AppLayout>
        <SeoHead title="Compare Products | Prinx Tech" description="Compare Prinx Tech products side by side by price, stock, warranty, specifications and available variants." canonical="/compare" robots="noindex,follow" />

        <main class="bg-[#fbfbfb]">
            <section class="container-shell py-6 sm:py-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="section-kicker">Make a better choice</p><h1 class="section-title">Compare Products</h1><p class="mt-2 text-[11px] leading-5 text-slate-500">Compare up to four products using current catalogue data.</p></div>
                    <Link href="/shop" class="btn-light">Add or change products</Link>
                </div>

                <div v-if="products.length >= 2" class="mt-6 overflow-x-auto rounded-[8px] border border-slate-200 bg-white">
                    <table class="min-w-[760px] w-full border-collapse text-left text-[11px]">
                        <thead>
                            <tr>
                                <th class="w-[170px] border-b border-r border-slate-200 bg-slate-50 p-4 text-slate-500">Product</th>
                                <th v-for="product in products" :key="product.id" class="min-w-[220px] border-b border-r border-slate-200 p-4 last:border-r-0">
                                    <Link :href="`/products/${product.slug}`" class="block">
                                        <img :src="product.image_url" :alt="product.name" class="h-32 w-full object-contain">
                                        <strong class="mt-3 block text-[13px] leading-5 text-slate-900">{{ product.name }}</strong>
                                    </Link>
                                    <strong class="mt-2 block text-[12px] text-prinx-600">{{ money(product.current_price) }}</strong>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in [
                                ['Brand', (p) => p.brand?.name || '—'],
                                ['Category', (p) => p.category?.name || '—'],
                                ['Condition', (p) => p.condition || '—'],
                                ['Availability', (p) => p.stock_message || (p.in_stock ? 'In Stock' : 'Out of Stock')],
                                ['Warranty', (p) => p.warranty || 'Not specified'],
                                ['Rating', rating],
                                ['Variants', variantSummary],
                            ]" :key="row[0]">
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">{{ row[0] }}</th>
                                <td v-for="product in products" :key="`${row[0]}-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ row[1](product) }}</td>
                            </tr>
                            <tr v-for="spec in specs" :key="spec">
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">{{ spec }}</th>
                                <td v-for="product in products" :key="`${spec}-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ product.specifications?.[spec] || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="border-r border-slate-200 bg-slate-50 p-4"></th>
                                <td v-for="product in products" :key="`cta-${product.id}`" class="border-r border-slate-200 p-4 last:border-r-0"><Link :href="`/products/${product.slug}`" class="flex h-10 items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-[10px] font-semibold text-white">View product</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="mt-6 rounded-[8px] border border-dashed border-slate-300 bg-white px-5 py-14 text-center">
                    <h2 class="text-[16px] font-semibold text-slate-900">Choose at least two products</h2>
                    <p class="mx-auto mt-2 max-w-lg text-[11px] leading-5 text-slate-500">Use the Compare buttons in the Shop catalogue to select two to four products.</p>
                    <Link href="/shop" class="mt-4 inline-flex h-10 items-center rounded-[6px] bg-prinx-500 px-5 text-[10px] font-semibold text-white">Browse products</Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
