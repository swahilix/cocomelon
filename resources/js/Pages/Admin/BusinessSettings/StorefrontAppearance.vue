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
    fontsAvailable: Boolean,
    storefrontFont: { type: String, default: 'inter' },
    fonts: { type: Array, default: () => [] },
    fontsStylesheet: { type: String, default: '' },
});

const form = useForm({
    _method: 'put',
    product_card_style: props.productCardStyle,
    storefront_font: props.storefrontFont,
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
const changed = computed(() => form.product_card_style !== props.productCardStyle
    || (props.fontsAvailable && form.storefront_font !== props.storefrontFont));
const selectedFont = computed(() => props.fonts.find((font) => font.value === form.storefront_font) || props.fonts[0] || null);
const previewFontStyle = computed(() => (selectedFont.value ? { fontFamily: selectedFont.value.stack } : {}));

const save = () => form
    .transform((data) => (props.fontsAvailable ? data : { _method: data._method, product_card_style: data.product_card_style }))
    .post('/admin/business-settings/storefront', { preserveScroll: true });
</script>

<template>
    <AdminLayout>
        <Head title="Storefront appearance">
            <link v-if="fontsStylesheet" rel="stylesheet" :href="fontsStylesheet">
        </Head>

        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Settings</p>
                <h1 class="section-title">Storefront appearance</h1>
                <p class="mt-1 text-[12px] text-slate-500">Pick the store font and the product card design for the shop page, homepage sections and wishlist. You can switch at any time.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="/shop" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">View shop</a>
                <Link href="/admin/settings" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">All settings</Link>
                <button type="button" class="btn-primary" :disabled="form.processing || !available || !changed" @click="save">
                    <Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" />
                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </div>

        <div v-if="!available" class="mt-4 max-w-3xl rounded-[7px] border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
            Run the latest database migrations before changing the card design.
        </div>

        <form v-else class="mt-4" @submit.prevent="save">
            <p v-if="form.errors.product_card_style" class="mb-3 text-xs font-medium text-red-600">{{ form.errors.product_card_style }}</p>
            <p v-if="form.errors.storefront_font" class="mb-3 text-xs font-medium text-red-600">{{ form.errors.storefront_font }}</p>

            <fieldset v-if="fontsAvailable && fonts.length" class="mb-6">
                <legend class="mb-1 text-[14px] font-semibold text-slate-900">Font</legend>
                <p class="mb-3 text-[12px] text-slate-500">Used for all text on the store. The card previews below switch to the font you pick.</p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label
                        v-for="font in fonts"
                        :key="font.value"
                        class="relative flex cursor-pointer flex-col rounded-[14px] border bg-white p-4 transition"
                        :class="form.storefront_font === font.value
                            ? 'border-prinx-500 ring-2 ring-prinx-500/20'
                            : 'border-slate-200 hover:border-slate-300'"
                    >
                        <input v-model="form.storefront_font" type="radio" name="storefront_font" :value="font.value" class="sr-only" data-native-select>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                <span class="text-[13px] font-semibold text-slate-900">{{ font.name }}</span>
                                <span v-if="font.recommended" class="rounded-full bg-prinx-50 px-2 py-0.5 text-[10px] font-semibold text-prinx-700">Recommended</span>
                                <span v-if="font.value === storefrontFont" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">In use</span>
                            </div>
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition"
                                :class="form.storefront_font === font.value ? 'border-prinx-500 bg-prinx-500' : 'border-slate-300 bg-white'"
                                aria-hidden="true"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                            </span>
                        </div>
                        <div class="mt-3 rounded-[10px] bg-[#f6f7f9] px-3 py-3 text-slate-900" :style="{ fontFamily: font.stack }">
                            <p class="text-[22px] font-semibold leading-none">Aa 123</p>
                            <p class="mt-2 line-clamp-1 text-[13px] font-semibold">Tecno Spark 20 Pro 8GB</p>
                            <p class="mt-1 text-[15px] font-semibold text-prinx-600">TZS 250,000</p>
                            <p class="mt-1 text-[11px] text-slate-500">Karibu! Bei nzuri kila siku.</p>
                        </div>
                        <p class="mt-2 text-[11px] leading-4 text-slate-500">{{ font.description }}</p>
                    </label>
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-3 text-[14px] font-semibold text-slate-900">Product card design</legend>
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

                        <div class="pointer-events-none mt-4 rounded-[12px] bg-[#f6f7f9] p-3 sm:p-4" :style="previewFontStyle" inert aria-hidden="true">
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
