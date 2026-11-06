<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import ProductCard from '../../../Components/ProductCard.vue';

const props = defineProps({
    available: Boolean,
    productCardStyle: { type: String, default: 'clean' },
    styles: { type: Array, default: () => [] },
    sampleProducts: { type: Array, default: () => [] },
});

const form = useForm({
    _method: 'put',
    product_card_style: props.productCardStyle,
});

// Stand-ins so every design can be previewed even before products are added.
const fallbackSamples = [
    {
        id: -1,
        slug: 'sample-charger',
        name: 'Wireless Charging Pad 15W with USB-C Cable',
        price: 42000,
        current_price: 35000,
        in_stock: true,
        stock_quantity: 12,
        warranty: '6 months',
        image_url: '/assets/product-placeholder.svg',
        brand: { name: 'Sample brand' },
        category: { name: 'Chargers' },
    },
    {
        id: -2,
        slug: 'sample-phone',
        name: 'Smartphone 8GB RAM 256GB Dual SIM',
        price: 485000,
        current_price: 485000,
        in_stock: true,
        low_stock: true,
        stock_quantity: 3,
        low_stock_threshold: 5,
        approved_reviews_count: 23,
        approved_reviews_average: 4.6,
        active_variants_count: 2,
        warranty: '1 year',
        image_url: '/assets/product-placeholder.svg',
        brand: { name: 'Sample brand' },
        category: { name: 'Smartphones' },
    },
];

const previewProducts = computed(() => [...props.sampleProducts, ...fallbackSamples].slice(0, 2));
const changed = computed(() => form.product_card_style !== props.productCardStyle);

const save = () => form.post('/admin/business-settings/storefront', { preserveScroll: true });
</script>

<template>
    <AdminLayout>
        <Head title="Storefront appearance" />

        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Settings</p>
                <h1 class="section-title">Storefront appearance</h1>
                <p class="mt-1 text-[12px] text-slate-500">Pick the product card design for the shop page, homepage sections and wishlist. You can switch at any time.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="/shop" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">View shop</a>
                <Link href="/admin/settings" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">All settings</Link>
                <button type="button" class="btn-primary" :disabled="form.processing || !available || !changed" @click="save">
                    <Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" />
                    {{ form.processing ? 'Saving...' : 'Save design' }}
                </button>
            </div>
        </div>

        <div v-if="!available" class="mt-4 max-w-3xl rounded-[7px] border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
            Run the latest database migrations before changing the card design.
        </div>

        <form v-else class="mt-4" @submit.prevent="save">
            <p v-if="form.errors.product_card_style" class="mb-3 text-xs font-medium text-red-600">{{ form.errors.product_card_style }}</p>

            <fieldset>
                <legend class="sr-only">Product card design</legend>
                <div class="grid gap-4 xl:grid-cols-2">
                    <label
                        v-for="(style, index) in styles"
                        :key="style.value"
                        class="group relative flex cursor-pointer flex-col rounded-[14px] border bg-white p-4 transition"
                        :class="form.product_card_style === style.value
                            ? 'border-prinx-500 ring-2 ring-prinx-500/20'
                            : 'border-slate-200 hover:border-slate-300'"
                    >
                        <input v-model="form.product_card_style" type="radio" name="product_card_style" :value="style.value" class="sr-only" data-native-select>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-600">{{ String.fromCharCode(65 + index) }}</span>
                                    <h2 class="text-[14px] font-semibold text-slate-900">{{ style.name }}</h2>
                                    <span v-if="style.value === productCardStyle" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">In use</span>
                                </div>
                                <p class="mt-1 text-[12px] leading-5 text-slate-500">{{ style.description }}</p>
                            </div>
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition"
                                :class="form.product_card_style === style.value ? 'border-prinx-500 bg-prinx-500' : 'border-slate-300 bg-white'"
                                aria-hidden="true"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                            </span>
                        </div>

                        <div class="pointer-events-none mt-4 rounded-[12px] bg-[#f6f7f9] p-3 sm:p-4" inert aria-hidden="true">
                            <div class="mx-auto grid max-w-[460px] grid-cols-2 gap-3">
                                <ProductCard v-for="product in previewProducts" :key="product.id" :product="product" :variant="style.value" sizes="220px" />
                            </div>
                        </div>
                    </label>
                </div>
            </fieldset>

            <p class="mt-4 text-[12px] text-slate-500">The preview uses your newest products. Hearts, buttons and links work on the live store, not here.</p>
        </form>
    </AdminLayout>
</template>
