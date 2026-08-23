<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProductDescription from '../../Components/ProductDescription.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({ product: Object, related: Array });
const page = usePage();
const qty = ref(1);
const selected = ref(props.product.image_url);
const activeTab = ref('description');
const wished = ref(false);

const baseUrl = String(page.props.store.url || '').replace(/\/$/, '');
const absoluteUrl = (value) => /^https?:\/\//i.test(value || '') ? value : `${baseUrl}/${String(value || '').replace(/^\//, '')}`;
const canonical = computed(() => absoluteUrl(`/products/${props.product.slug}`));
const images = computed(() => [...new Set([props.product.image_url, ...(props.product.gallery_urls || [])].filter(Boolean))]);
const imageIndex = computed(() => Math.max(0, images.value.indexOf(selected.value)));
const money = (value) => `TZS ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const discount = computed(() => {
    const regular = Number(props.product.price || 0);
    const current = Number(props.product.current_price || 0);
    return regular > current && regular > 0 ? Math.max(1, Math.round(((regular - current) / regular) * 100)) : 0;
});
const maxQty = computed(() => props.product.track_stock ? Math.max(1, Number(props.product.stock_quantity || 1)) : 99);

const specificationRows = computed(() => [
    props.product.brand?.name ? ['Brand', props.product.brand.name] : null,
    props.product.category?.name ? ['Category', props.product.category.name] : null,
    props.product.sku ? ['SKU', props.product.sku] : null,
    props.product.condition ? ['Condition', props.product.condition] : null,
    props.product.size ? ['Size', props.product.size] : null,
    props.product.color ? ['Color', props.product.color] : null,
    props.product.mpn ? ['MPN', props.product.mpn] : null,
    props.product.gtin ? ['GTIN', props.product.gtin] : null,
    props.product.warranty ? ['Warranty', props.product.warranty] : null,
    props.product.shipping_weight ? ['Weight', `${props.product.shipping_weight} kg`] : null,
].filter(Boolean));

const schemaImages = computed(() => [props.product.image_metadata, ...(props.product.gallery_metadata || [])]
    .filter(Boolean)
    .map((image) => ({ ...image, contentUrl: absoluteUrl(image.contentUrl), url: absoluteUrl(image.url || image.contentUrl) })));
const itemCondition = computed(() => ({ new: 'https://schema.org/NewCondition', used: 'https://schema.org/UsedCondition', refurbished: 'https://schema.org/RefurbishedCondition' }[props.product.condition] || 'https://schema.org/NewCondition'));
const schema = computed(() => ({
    '@context': 'https://schema.org',
    '@graph': [
        {
            '@type': 'Product',
            '@id': `${canonical.value}#product`,
            mainEntityOfPage: { '@id': `${canonical.value}#webpage` },
            name: props.product.name,
            image: schemaImages.value,
            description: props.product.plain_description || props.product.short_description,
            sku: props.product.sku,
            gtin: props.product.gtin || undefined,
            mpn: props.product.mpn || undefined,
            color: props.product.color || undefined,
            size: props.product.size || undefined,
            brand: props.product.brand ? { '@type': 'Brand', name: props.product.brand.name } : undefined,
            offers: {
                '@type': 'Offer',
                url: canonical.value,
                priceCurrency: page.props.store.currency,
                price: props.product.current_price,
                availability: props.product.in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                itemCondition: itemCondition.value,
                seller: { '@id': `${page.props.store.url}/#store` },
            },
        },
        {
            '@type': 'BreadcrumbList',
            itemListElement: [
                { '@type': 'ListItem', position: 1, name: 'Home', item: page.props.store.url },
                { '@type': 'ListItem', position: 2, name: 'Shop', item: absoluteUrl('/shop') },
                { '@type': 'ListItem', position: 3, name: props.product.name, item: canonical.value },
            ],
        },
    ],
}));

function add() {
    if (!props.product.in_stock) return;
    router.post(`/cart/${props.product.slug}`, { quantity: qty.value }, { preserveScroll: true });
}

function buyNow() {
    if (!props.product.in_stock) return;
    router.post(`/cart/${props.product.slug}`, { quantity: qty.value }, {
        preserveScroll: false,
        onSuccess: () => router.visit('/checkout'),
    });
}

function decreaseQty() {
    qty.value = Math.max(1, Number(qty.value || 1) - 1);
}

function increaseQty() {
    qty.value = Math.min(maxQty.value, Number(qty.value || 1) + 1);
}

function previousImage() {
    if (images.value.length < 2) return;
    const index = imageIndex.value <= 0 ? images.value.length - 1 : imageIndex.value - 1;
    selected.value = images.value[index];
}

function nextImage() {
    if (images.value.length < 2) return;
    const index = imageIndex.value >= images.value.length - 1 ? 0 : imageIndex.value + 1;
    selected.value = images.value[index];
}

function relatedDiscount(item) {
    const regular = Number(item.price || 0);
    const current = Number(item.current_price || 0);
    return regular > current && regular > 0 ? Math.max(1, Math.round(((regular - current) / regular) * 100)) : 0;
}

function addRelated(item) {
    if (!item.in_stock) return;
    router.post(`/cart/${item.slug}`, { quantity: 1 }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="product.meta_title || product.name"
            :description="product.meta_description || product.short_description"
            :canonical="canonical"
            :image="product.image_url"
            :image-alt="`${product.name} product image from Prinx Electronics`"
            type="product"
            :schema="schema"
        />

        <div class="container-shell pb-8 pt-4 sm:pt-5">
            <nav class="flex flex-wrap items-center gap-2 text-[10px] font-medium text-slate-500" aria-label="Breadcrumb">
                <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                <span>/</span>
                <Link href="/shop" class="transition hover:text-prinx-600">Shop</Link>
                <span>/</span>
                <span class="text-prinx-600">Product Details</span>
            </nav>

            <section class="mt-3 grid items-start gap-7 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.15fr)] lg:gap-8">
                <div class="min-w-0">
                    <div class="relative flex min-h-[390px] items-center justify-center overflow-hidden rounded-[8px] border border-slate-200 bg-white p-5 sm:min-h-[470px] lg:min-h-[520px]">
                        <span v-if="discount" class="absolute left-3 top-3 z-10 rounded-[6px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">-{{ discount }}%</span>

                        <button type="button" class="absolute right-3 top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-prinx-300 hover:text-prinx-600" :aria-label="wished ? 'Remove from wishlist' : 'Add to wishlist'" @click="wished = !wished">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] stroke-current" :class="wished ? 'fill-prinx-500 text-prinx-500' : 'fill-none'" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                        </button>

                        <button v-if="images.length > 1" type="button" class="absolute left-3 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm hover:text-prinx-600" aria-label="Previous image" @click="previousImage">‹</button>
                        <img :src="selected" :alt="product.name" :title="product.name" fetchpriority="high" decoding="async" class="max-h-[430px] w-full object-contain sm:max-h-[470px]">
                        <button v-if="images.length > 1" type="button" class="absolute right-3 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm hover:text-prinx-600" aria-label="Next image" @click="nextImage">›</button>
                    </div>

                    <div v-if="images.length > 1" class="mt-3 flex justify-center gap-3 overflow-x-auto pb-1 scrollbar-hidden sm:justify-start">
                        <button v-for="image in images" :key="image" type="button" :class="['flex h-[74px] w-[82px] shrink-0 items-center justify-center overflow-hidden rounded-[6px] border bg-white p-2 transition', selected === image ? 'border-prinx-500 ring-1 ring-prinx-100' : 'border-slate-200 hover:border-prinx-300']" @click="selected = image">
                            <img :src="image" :alt="product.name" loading="lazy" decoding="async" class="h-full w-full object-contain">
                        </button>
                    </div>
                </div>

                <div class="min-w-0 pt-1">
                    <h1 class="text-[28px] font-semibold leading-[1.1] tracking-[-.025em] text-slate-950 sm:text-[34px]">{{ product.name }}</h1>

                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-[10px] text-slate-500">
                        <span class="tracking-[-1px] text-amber-500">★★★★★</span>
                        <span>(0 reviews)</span>
                        <span class="text-slate-300">•</span>
                        <span>Brand: <strong class="font-medium text-slate-700">{{ product.brand?.name || 'Prinx' }}</strong></span>
                        <span class="text-slate-300">•</span>
                        <span>SKU: <strong class="font-medium text-slate-700">{{ product.sku }}</strong></span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-end gap-x-4 gap-y-2">
                        <strong class="text-[24px] font-semibold tracking-[-.02em] text-slate-950 sm:text-[28px]">{{ money(product.current_price) }}</strong>
                        <span v-if="Number(product.current_price) < Number(product.price)" class="pb-1 text-[12px] text-slate-400 line-through">{{ money(product.price) }}</span>
                        <span v-if="discount" class="mb-0.5 rounded-[5px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">-{{ discount }}%</span>
                    </div>

                    <div class="mt-2 flex items-center gap-2 text-[11px] font-medium" :class="product.in_stock ? 'text-emerald-600' : 'text-red-600'">
                        <span class="h-2 w-2 rounded-full" :class="product.in_stock ? 'bg-emerald-500' : 'bg-red-500'"></span>
                        {{ product.in_stock ? 'In Stock' : 'Out of Stock' }}
                    </div>

                    <p v-if="product.short_description" class="mt-4 max-w-3xl text-[11px] leading-5 text-slate-600">{{ product.short_description }}</p>

                    <div v-if="product.size || product.color" class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div v-if="product.size">
                            <p class="text-[10px] text-slate-500"><strong class="font-semibold text-slate-800">Size:</strong> {{ product.size }}</p>
                            <button type="button" class="mt-2 min-w-[58px] rounded-[6px] border border-prinx-500 bg-prinx-50 px-3 py-2 text-[10px] font-semibold text-prinx-700">{{ product.size }}</button>
                        </div>
                        <div v-if="product.color">
                            <p class="text-[10px] text-slate-500"><strong class="font-semibold text-slate-800">Color:</strong> {{ product.color }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-prinx-500 bg-white"><span class="h-4 w-4 rounded-full bg-slate-700"></span></span>
                                <span class="h-6 w-6 rounded-full bg-slate-900"></span>
                                <span class="h-6 w-6 rounded-full border border-slate-200 bg-slate-100"></span>
                                <span class="h-6 w-6 rounded-full bg-slate-700"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="mb-2 text-[10px] font-semibold text-slate-800">Quantity:</p>
                        <div class="inline-flex h-9 overflow-hidden rounded-[6px] border border-slate-200 bg-white">
                            <button type="button" class="w-9 border-r border-slate-200 text-lg text-slate-600 hover:bg-slate-50" @click="decreaseQty">−</button>
                            <input v-model.number="qty" type="number" min="1" :max="maxQty" class="w-12 border-0 bg-white p-0 text-center text-[11px] font-medium outline-none">
                            <button type="button" class="w-9 border-l border-slate-200 text-lg text-slate-600 hover:bg-slate-50" @click="increaseQty">+</button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_.74fr]">
                        <button type="button" class="flex h-[44px] items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[11px] font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!product.in_stock" @click="add">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2 10h10l2-7H7M9 19.2a1.2 1.2 0 1 0 0 .1Zm7 0a1.2 1.2 0 1 0 0 .1Z"/></svg>
                            Add to Cart
                        </button>
                        <button type="button" class="flex h-[44px] items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[11px] font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!product.in_stock" @click="buyNow">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-current"><path d="m13 2-7 12h5l-1 8 8-13h-5V2Z"/></svg>
                            Buy Now
                        </button>
                        <button type="button" class="flex h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 text-[10px] font-semibold text-slate-700 transition hover:border-prinx-300 hover:text-prinx-600" @click="wished = !wished">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] stroke-current" :class="wished ? 'fill-prinx-500 text-prinx-500' : 'fill-none'" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                            Add to Wishlist
                        </button>
                    </div>

                    <div class="mt-5 grid overflow-hidden rounded-[7px] border border-slate-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-4 sm:border-r xl:border-b-0">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                            <div><strong class="block text-[9px] font-semibold text-slate-800">Delivery</strong><span class="mt-1 block text-[8px] text-slate-500">Get it in 20 – 40 mins</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-4 xl:border-b-0 xl:border-r">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg>
                            <div><strong class="block text-[9px] font-semibold text-slate-800">Warranty</strong><span class="mt-1 block text-[8px] text-slate-500">{{ product.warranty || 'Warranty support' }}</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-4 sm:border-r sm:border-b-0">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2L7.8 16l.8-4.7L5.2 8l4.7-.7L12 3Z"/><path stroke-linecap="round" d="m9.5 12 1.5 1.5 3.5-3.5"/></svg>
                            <div><strong class="block text-[9px] font-semibold text-slate-800">100% Original</strong><span class="mt-1 block text-[8px] text-slate-500">Genuine product</span></div>
                        </div>
                        <div class="flex items-center gap-3 px-4 py-4">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.6"><rect x="5" y="9" width="14" height="11" rx="2"/><path stroke-linecap="round" d="M8 9V7a4 4 0 0 1 8 0v2M12 13v3"/></svg>
                            <div><strong class="block text-[9px] font-semibold text-slate-800">Secure Payment</strong><span class="mt-1 block text-[8px] text-slate-500">Safe & encrypted</span></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-5 grid gap-3 lg:grid-cols-[2fr_1fr]">
                <article class="overflow-hidden rounded-[7px] border border-slate-200 bg-white">
                    <div class="flex border-b border-slate-200 px-2">
                        <button type="button" class="relative px-3 py-3 text-[9px] font-semibold" :class="activeTab === 'description' ? 'text-prinx-600' : 'text-slate-500'" @click="activeTab = 'description'">Description<span v-if="activeTab === 'description'" class="absolute inset-x-2 bottom-0 h-[2px] bg-prinx-500"></span></button>
                        <button type="button" class="relative px-3 py-3 text-[9px] font-medium" :class="activeTab === 'specs' ? 'text-prinx-600' : 'text-slate-500'" @click="activeTab = 'specs'">Specifications<span v-if="activeTab === 'specs'" class="absolute inset-x-2 bottom-0 h-[2px] bg-prinx-500"></span></button>
                        <button type="button" class="relative px-3 py-3 text-[9px] font-medium" :class="activeTab === 'reviews' ? 'text-prinx-600' : 'text-slate-500'" @click="activeTab = 'reviews'">Reviews (0)<span v-if="activeTab === 'reviews'" class="absolute inset-x-2 bottom-0 h-[2px] bg-prinx-500"></span></button>
                    </div>
                    <div class="min-h-[190px] px-4 py-4 text-[10px] leading-5 text-slate-600">
                        <ProductDescription v-if="activeTab === 'description'" :content="product.description" />
                        <div v-else-if="activeTab === 'specs'" class="divide-y divide-slate-100">
                            <div v-for="row in specificationRows" :key="row[0]" class="grid grid-cols-[140px_1fr] gap-4 py-2"><strong class="font-semibold text-slate-700">{{ row[0] }}</strong><span>{{ row[1] }}</span></div>
                            <p v-if="!specificationRows.length" class="py-8 text-center text-slate-400">No additional specifications available.</p>
                        </div>
                        <div v-else class="flex min-h-[150px] items-center justify-center text-center text-slate-400">No customer reviews have been submitted yet.</div>
                    </div>
                </article>

                <article class="overflow-hidden rounded-[7px] border border-slate-200 bg-white">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><span class="text-[10px] font-semibold text-slate-800">Reviews (0)</span><span class="text-[8px] text-slate-400">View all reviews ›</span></header>
                    <div class="flex min-h-[190px] flex-col items-center justify-center px-6 text-center">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-prinx-50 text-prinx-600">
                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5h14v11H9l-4 3V5Z"/><path stroke-linecap="round" d="M8 9h8M8 12h5"/></svg>
                        </div>
                        <strong class="mt-3 text-[10px] font-semibold text-slate-700">No reviews yet</strong>
                        <p class="mt-1 max-w-[230px] text-[9px] leading-4 text-slate-400">Customer reviews will appear here once they are available.</p>
                    </div>
                </article>
            </section>

            <section v-if="related.length" class="mt-5">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold tracking-[-.015em] text-slate-900">Related Products</h2>
                    <Link href="/shop" class="text-[9px] font-medium text-prinx-600 hover:text-prinx-700">View all</Link>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="item in related.slice(0, 4)" :key="item.id" class="group grid min-h-[118px] grid-cols-[118px_1fr] overflow-hidden rounded-[7px] border border-slate-200 bg-white">
                        <Link :href="`/products/${item.slug}`" class="relative flex items-center justify-center bg-white p-3">
                            <img :src="item.image_url" :alt="item.name" loading="lazy" decoding="async" class="h-[88px] w-[88px] object-contain transition group-hover:scale-[1.03]">
                            <span v-if="relatedDiscount(item)" class="absolute right-2 top-2 rounded-[5px] bg-prinx-50 px-1.5 py-1 text-[8px] font-semibold text-prinx-700">-{{ relatedDiscount(item) }}%</span>
                        </Link>
                        <div class="flex min-w-0 flex-col justify-center border-l border-slate-100 p-3">
                            <Link :href="`/products/${item.slug}`" class="line-clamp-2 text-[9px] font-semibold leading-4 text-slate-800 transition group-hover:text-prinx-600">{{ item.name }}</Link>
                            <div class="mt-1 text-[8px] tracking-[-1px] text-amber-500">★★★★★</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2"><strong class="text-[9px] font-semibold text-slate-800">{{ money(item.current_price) }}</strong><span v-if="Number(item.current_price) < Number(item.price)" class="text-[8px] text-slate-400 line-through">{{ money(item.price) }}</span></div>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" class="h-7 flex-1 rounded-[5px] border border-prinx-300 bg-white px-2 text-[8px] font-semibold text-prinx-600 hover:bg-prinx-500 hover:text-white disabled:border-slate-200 disabled:text-slate-400" :disabled="!item.in_stock" @click="addRelated(item)">Add to Cart</button>
                                <button type="button" class="flex h-7 w-8 items-center justify-center rounded-[5px] border border-slate-200 text-slate-500 hover:border-prinx-300 hover:text-prinx-600" aria-label="Add to wishlist"><svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg></button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>