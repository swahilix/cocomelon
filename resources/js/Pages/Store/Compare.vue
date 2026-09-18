<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProductRating from '../../Components/ProductRating.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    availableProducts: { type: Array, default: () => [] },
});

const page = usePage();
const currency = computed(() => page.props.store?.currency || 'TZS');
const search = ref('');
const category = ref('');
const selectedSlugs = ref(props.products.map((product) => product.slug));

const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: currency.value,
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const categories = computed(() => {
    const values = new Map();
    props.availableProducts.forEach((product) => {
        if (product.category?.slug) values.set(product.category.slug, product.category.name);
    });
    return [...values.entries()].map(([slug, name]) => ({ slug, name }));
});

const filteredProducts = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return props.availableProducts.filter((product) => {
        const matchesCategory = !category.value || product.category?.slug === category.value;
        const matchesSearch = !needle || [
            product.name,
            product.brand?.name,
            product.category?.name,
        ].filter(Boolean).some((value) => String(value).toLowerCase().includes(needle));

        return matchesCategory && matchesSearch;
    });
});

const selectedProducts = computed(() => selectedSlugs.value
    .map((slug) => props.availableProducts.find((product) => product.slug === slug))
    .filter(Boolean));

const specs = computed(() => [...new Set(props.products.flatMap((product) => Object.keys(product.specifications || {})))]);

const variantSummary = (product) => (product.variants || []).map((variant) => variant.display_name).join(', ') || 'No variants';

const isSelected = (product) => selectedSlugs.value.includes(product.slug);

const toggleProduct = (product) => {
    if (isSelected(product)) {
        selectedSlugs.value = selectedSlugs.value.filter((slug) => slug !== product.slug);
        return;
    }

    if (selectedSlugs.value.length >= 4) return;
    selectedSlugs.value = [...selectedSlugs.value, product.slug];
};

const removeSelected = (slug) => {
    selectedSlugs.value = selectedSlugs.value.filter((value) => value !== slug);
};

const compareSelected = () => {
    if (selectedSlugs.value.length < 2) return;
    router.get('/compare', { products: selectedSlugs.value.join(',') }, {
        preserveState: false,
        preserveScroll: false,
    });
};

const clearSelection = () => {
    selectedSlugs.value = [];
    if (props.products.length) {
        router.get('/compare', {}, { preserveState: false, replace: true });
    }
};
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Compare Products | Prinx Tech"
            description="Choose products and compare their prices, stock, warranty, ratings, variants and specifications side by side."
            canonical="/compare"
            robots="noindex,follow"
        />

        <main class="bg-[#fbfbfb]">
            <section class="border-b border-slate-200 bg-white">
                <div class="container-shell py-7 sm:py-9">
                    <p class="section-kicker">Product comparison</p>
                    <h1 class="section-title">Compare Products</h1>
                    <p class="mt-2 max-w-2xl text-[11px] leading-5 text-slate-500">Choose two to four products here, then compare them side by side. Comparison is managed only on this page.</p>
                </div>
            </section>

            <section class="container-shell py-6 sm:py-8">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_310px]">
                    <div class="min-w-0">
                        <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <div class="relative flex-1">
                                    <svg viewBox="0 0 24 24" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.3"/><path d="m16 16 4 4"/></svg>
                                    <input v-model="search" class="input pl-9" placeholder="Search products, brands or categories">
                                </div>
                                <select v-model="category" class="input sm:w-[220px]">
                                    <option value="">All categories</option>
                                    <option v-for="item in categories" :key="item.slug" :value="item.slug">{{ item.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="filteredProducts.length" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            <article
                                v-for="product in filteredProducts"
                                :key="product.id"
                                :class="['group flex min-w-0 flex-col overflow-hidden rounded-[8px] border bg-white transition', isSelected(product) ? 'border-prinx-500 ring-1 ring-prinx-100' : 'border-slate-200 hover:border-prinx-200']"
                            >
                                <Link :href="`/products/${product.slug}`" class="flex h-[150px] items-center justify-center bg-white p-3">
                                    <img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain" loading="lazy" decoding="async">
                                </Link>

                                <div class="flex flex-1 flex-col p-3">
                                    <Link :href="`/products/${product.slug}`" class="line-clamp-2 min-h-[32px] text-[10px] font-semibold leading-4 text-slate-800 hover:text-prinx-600">{{ product.name }}</Link>
                                    <ProductRating class="mt-1 text-[9px]" :rating="product.approved_reviews_average || 0" :count="product.approved_reviews_count || 0" />
                                    <strong class="mt-2 text-[10px] text-slate-900">{{ money(product.current_price) }}</strong>
                                    <p class="mt-1 text-[9px]" :class="product.in_stock ? 'text-emerald-600' : 'text-red-500'">{{ product.stock_message || (product.in_stock ? 'In Stock' : 'Out of Stock') }}</p>

                                    <button
                                        type="button"
                                        :class="['mt-3 h-9 rounded-[6px] border px-3 text-[10px] font-semibold transition', isSelected(product) ? 'border-prinx-500 bg-prinx-50 text-prinx-700' : 'border-slate-200 bg-white text-slate-700 hover:border-prinx-300 hover:text-prinx-600']"
                                        :disabled="!isSelected(product) && selectedSlugs.length >= 4"
                                        @click="toggleProduct(product)"
                                    >
                                        {{ isSelected(product) ? 'Selected' : 'Select to compare' }}
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div v-else class="mt-4 rounded-[8px] border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                            <strong class="text-[14px] text-slate-800">No matching products</strong>
                            <p class="mt-1 text-[10px] text-slate-500">Try another search phrase or category.</p>
                        </div>
                    </div>

                    <aside class="lg:sticky lg:top-4 lg:self-start">
                        <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-[13px] font-semibold text-slate-900">Selected products</h2>
                                    <p class="mt-1 text-[9px] text-slate-500">{{ selectedSlugs.length }}/4 selected · minimum 2</p>
                                </div>
                                <button v-if="selectedSlugs.length" type="button" class="text-[9px] font-semibold text-slate-500 hover:text-red-600" @click="clearSelection">Clear</button>
                            </div>

                            <div v-if="selectedProducts.length" class="mt-4 space-y-2">
                                <div v-for="product in selectedProducts" :key="product.slug" class="flex items-center gap-3 rounded-[7px] border border-slate-200 p-2">
                                    <img :src="product.image_url" :alt="product.name" class="h-11 w-11 shrink-0 object-contain">
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-[9px] font-semibold leading-4 text-slate-800">{{ product.name }}</p>
                                        <p class="mt-0.5 text-[9px] font-medium text-prinx-600">{{ money(product.current_price) }}</p>
                                    </div>
                                    <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Remove ${product.name}`" @click="removeSelected(product.slug)">×</button>
                                </div>
                            </div>

                            <div v-else class="mt-4 rounded-[7px] border border-dashed border-slate-300 px-3 py-7 text-center text-[10px] leading-5 text-slate-500">
                                Select products from the list to start a comparison.
                            </div>

                            <button type="button" class="mt-4 h-11 w-full rounded-[6px] bg-prinx-500 px-4 text-[10px] font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="selectedSlugs.length < 2" @click="compareSelected">
                                Compare selected products
                            </button>
                        </div>
                    </aside>
                </div>
            </section>

            <section v-if="products.length >= 2" class="container-shell pb-10">
                <div class="mb-4">
                    <p class="section-kicker">Side-by-side results</p>
                    <h2 class="mt-1 text-[20px] font-semibold text-slate-950">Comparison</h2>
                </div>

                <div class="overflow-x-auto rounded-[8px] border border-slate-200 bg-white">
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
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Brand</th>
                                <td v-for="product in products" :key="`brand-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ product.brand?.name || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Category</th>
                                <td v-for="product in products" :key="`category-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ product.category?.name || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Condition</th>
                                <td v-for="product in products" :key="`condition-${product.id}`" class="border-b border-r border-slate-200 p-4 capitalize text-slate-600 last:border-r-0">{{ product.condition || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Availability</th>
                                <td v-for="product in products" :key="`availability-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ product.stock_message || (product.in_stock ? 'In Stock' : 'Out of Stock') }}</td>
                            </tr>
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Warranty</th>
                                <td v-for="product in products" :key="`warranty-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ product.warranty || 'Not specified' }}</td>
                            </tr>
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Rating</th>
                                <td v-for="product in products" :key="`rating-${product.id}`" class="border-b border-r border-slate-200 p-4 last:border-r-0">
                                    <ProductRating class="text-[10px]" :rating="product.approved_reviews_average || 0" :count="product.approved_reviews_count || 0" />
                                </td>
                            </tr>
                            <tr>
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">Variants</th>
                                <td v-for="product in products" :key="`variants-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ variantSummary(product) }}</td>
                            </tr>
                            <tr v-for="spec in specs" :key="spec">
                                <th class="border-b border-r border-slate-200 bg-slate-50 p-4 font-semibold text-slate-700">{{ spec }}</th>
                                <td v-for="product in products" :key="`${spec}-${product.id}`" class="border-b border-r border-slate-200 p-4 text-slate-600 last:border-r-0">{{ product.specifications?.[spec] || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="border-r border-slate-200 bg-slate-50 p-4"></th>
                                <td v-for="product in products" :key="`cta-${product.id}`" class="border-r border-slate-200 p-4 last:border-r-0">
                                    <Link :href="`/products/${product.slug}`" class="flex h-10 items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-[10px] font-semibold text-white">View product</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
