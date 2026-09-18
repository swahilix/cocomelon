<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProductDescription from '../../Components/ProductDescription.vue';
import ProductRating from '../../Components/ProductRating.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    product: Object,
    related: { type: Array, default: () => [] },
    variants: { type: Array, default: () => [] },
    reviews: { type: Array, default: () => [] },
    reviewSummary: { type: Object, default: () => ({ count: 0, average: 0 }) },
    reviewEligibility: { type: Object, default: () => ({ can_review: false, reason: null, review: null }) },
});
const page = usePage();
const qty = ref(1);
const selected = ref(props.product.image_url);
const activeTab = ref('description');
const wished = ref(false);
const reviewRating = ref(Number(props.reviewEligibility?.review?.rating || 5));
const reviewTitle = ref(props.reviewEligibility?.review?.title || '');
const reviewBody = ref(props.reviewEligibility?.review?.body || '');
const reviewSubmitting = ref(false);
const selectedVariant = ref((props.variants || []).find((variant) => variant.in_stock) || props.variants?.[0] || null);
if (selectedVariant.value?.image_url) selected.value = selectedVariant.value.image_url;
const reviewAverage = computed(() => Number(props.reviewSummary?.average || 0));
const reviewCount = computed(() => Number(props.reviewSummary?.count || 0));
const whatsappDigits = computed(() => {
    let digits = String(page.props.store?.whatsapp || '').replace(/\D/g, '');
    if (digits.length === 10 && digits.startsWith('0')) digits = `255${digits.slice(1)}`;
    if (digits.length === 9) digits = `255${digits}`;
    return digits;
});
const whatsappMessage = computed(() => [
    `Hello ${page.props.store?.name || 'Prinx Tech'}, I'm interested in this product:`,
    selectedVariant.value ? `${props.product.name} — ${selectedVariant.value.display_name}` : props.product.name,
    `Price: ${money(displayPrice.value)}`,
    canonical.value,
    '',
    'Is it available?',
].join('\n'));
const whatsappHref = computed(() => whatsappDigits.value
    ? `https://wa.me/${whatsappDigits.value}?text=${encodeURIComponent(whatsappMessage.value)}`
    : null);

const baseUrl = String(page.props.store.url || '').replace(/\/$/, '');
const absoluteUrl = (value) => /^https?:\/\//i.test(value || '') ? value : `${baseUrl}/${String(value || '').replace(/^\//, '')}`;
const canonical = computed(() => absoluteUrl(`/products/${props.product.slug}`));
const variants = computed(() => props.variants || []);
const images = computed(() => [...new Set([
    selectedVariant.value?.image_url,
    props.product.image_url,
    ...(props.product.gallery_urls || []),
    ...variants.value.map((variant) => variant.image_url),
].filter(Boolean))]);
const displayPrice = computed(() => Number(selectedVariant.value?.current_price ?? props.product.current_price ?? 0));
const regularPrice = computed(() => Number(selectedVariant.value?.price ?? props.product.price ?? displayPrice.value));
const displaySku = computed(() => selectedVariant.value?.sku || props.product.sku);
const displayInStock = computed(() => selectedVariant.value ? Boolean(selectedVariant.value.in_stock) : Boolean(props.product.in_stock));
const displayStockQuantity = computed(() => selectedVariant.value ? Number(selectedVariant.value.stock_quantity || 0) : Number(props.product.stock_quantity || 0));
const displayStockMessage = computed(() => {
    if (!displayInStock.value) return 'Out of Stock';
    if (selectedVariant.value) {
        const threshold = Number(props.product.low_stock_threshold || 0);
        if (threshold > 0 && displayStockQuantity.value <= threshold) return `Only ${displayStockQuantity.value} left in stock`;
        return 'In Stock';
    }
    return props.product.stock_message || 'In Stock';
});
const variantDimensions = computed(() => [
    { key: 'color', label: 'Color' },
    { key: 'storage', label: 'Storage' },
    { key: 'size', label: 'Size' },
].map((dimension) => ({
    ...dimension,
    values: [...new Set(variants.value.map((variant) => variant[dimension.key]).filter(Boolean))],
})).filter((dimension) => dimension.values.length));
function chooseVariantOption(key, value) {
    const current = selectedVariant.value;
    const dimensionKeys = ['color', 'storage', 'size'];
    const exact = variants.value.find((variant) =>
        variant[key] === value
        && dimensionKeys.every((other) => other === key || !current?.[other] || variant[other] === current[other])
        && variant.in_stock
    );
    const fallback = variants.value.find((variant) => variant[key] === value && variant.in_stock)
        || variants.value.find((variant) => variant[key] === value);
    const next = exact || fallback;
    if (!next) return;
    selectedVariant.value = next;
    selected.value = next.image_url || props.product.image_url;
    qty.value = 1;
}

const imageIndex = computed(() => Math.max(0, images.value.indexOf(selected.value)));
const money = (value) => `TZS ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const discount = computed(() => {
    const regular = regularPrice.value;
    const current = displayPrice.value;
    return regular > current && regular > 0 ? Math.max(1, Math.round(((regular - current) / regular) * 100)) : 0;
});
const maxQty = computed(() => selectedVariant.value
    ? Math.max(1, Number(selectedVariant.value.stock_quantity || 1))
    : (props.product.track_stock ? Math.max(1, Number(props.product.stock_quantity || 1)) : 99));
const lowStock = computed(() => {
    const threshold = Number(props.product.low_stock_threshold || 0);
    return displayInStock.value && threshold > 0 && displayStockQuantity.value <= threshold;
});
const stockTone = computed(() => displayInStock.value
    ? (lowStock.value ? 'text-amber-700' : 'text-emerald-600')
    : 'text-red-600');
const stockDot = computed(() => displayInStock.value
    ? (lowStock.value ? 'bg-amber-500' : 'bg-emerald-500')
    : 'bg-red-500');
const hasFulfillment = computed(() => Boolean(page.props.store?.delivery_enabled || page.props.store?.pickup_enabled));
const paymentMethods = computed(() => page.props.store?.payment_methods || []);
const seoTitle = computed(() => String(props.product.meta_title || `${props.product.name} | Prinx Tech`).replaceAll('Prinx Electronics', 'Prinx Tech'));
const seoDescription = computed(() => String(props.product.meta_description || props.product.short_description || props.product.plain_description || '').replaceAll('Prinx Electronics', 'Prinx Tech'));

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
            aggregateRating: reviewCount.value ? {
                '@type': 'AggregateRating',
                ratingValue: reviewAverage.value,
                reviewCount: reviewCount.value,
            } : undefined,
            review: reviewCount.value ? props.reviews.map((review) => ({
                '@type': 'Review',
                author: { '@type': 'Person', name: review.reviewer_name },
                datePublished: review.created_at,
                name: review.title || undefined,
                reviewBody: review.body,
                reviewRating: { '@type': 'Rating', ratingValue: review.rating, bestRating: 5, worstRating: 1 },
            })) : undefined,
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

function trackWhatsApp(placement) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!token) return;

    fetch(`/products/${encodeURIComponent(props.product.slug)}/contact-events`, {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body: JSON.stringify({ channel: 'whatsapp', placement }),
    }).catch(() => {});
}

function add() {
    if (!displayInStock.value || (variants.value.length && !selectedVariant.value)) return;
    router.post(`/cart/${props.product.slug}`, { quantity: qty.value, variant_id: selectedVariant.value?.id || null, intent: 'add_to_cart' }, { preserveScroll: true });
}

function buyNow() {
    if (!displayInStock.value || (variants.value.length && !selectedVariant.value)) return;
    router.post(`/cart/${props.product.slug}`, { quantity: qty.value, variant_id: selectedVariant.value?.id || null, intent: 'buy_now' }, {
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
    if (Number(item.active_variants_count || 0) > 0) {
        router.visit(`/products/${item.slug}`);
        return;
    }
    router.post(`/cart/${item.slug}`, { quantity: 1 }, { preserveScroll: true });
}

function starsFor(value) {
    const rounded = Math.max(0, Math.min(5, Math.round(Number(value || 0))));
    return '★'.repeat(rounded) + '☆'.repeat(5 - rounded);
}

function submitReview() {
    if (!props.reviewEligibility?.can_review || reviewSubmitting.value) return;
    reviewSubmitting.value = true;

    router.post(`/products/${props.product.slug}/reviews`, {
        rating: reviewRating.value,
        title: reviewTitle.value,
        body: reviewBody.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { activeTab.value = 'reviews'; },
        onFinish: () => { reviewSubmitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="seoTitle"
            :description="seoDescription"
            :canonical="canonical"
            :image="product.image_url"
            :image-alt="`${product.name} product image from Prinx Tech`"
            type="product"
            :schema="schema"
        />

        <div class="container-shell pb-24 pt-4 sm:pt-5 lg:pb-8">
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
                        <button type="button" class="inline-flex items-center" @click="activeTab = 'reviews'">
                            <ProductRating class="text-[10px]" :rating="reviewAverage" :count="reviewCount" />
                        </button>
                        <span class="text-slate-300">•</span>
                        <span>Brand: <strong class="font-medium text-slate-700">{{ product.brand?.name || 'Prinx' }}</strong></span>
                        <span class="text-slate-300">•</span>
                        <span>SKU: <strong class="font-medium text-slate-700">{{ displaySku }}</strong></span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-end gap-x-4 gap-y-2">
                        <strong class="text-[24px] font-semibold tracking-[-.02em] text-slate-950 sm:text-[28px]">{{ money(displayPrice) }}</strong>
                        <span v-if="displayPrice < regularPrice" class="pb-1 text-[12px] text-slate-400 line-through">{{ money(regularPrice) }}</span>
                        <span v-if="discount" class="mb-0.5 rounded-[5px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">-{{ discount }}%</span>
                    </div>

                    <div class="mt-2 flex items-center gap-2 text-[11px] font-semibold" :class="stockTone">
                        <span class="h-2 w-2 rounded-full" :class="stockDot"></span>
                        {{ displayStockMessage }}
                    </div>

                    <div v-if="variants.length" class="mt-4 space-y-3 rounded-[8px] border border-slate-200 bg-slate-50 p-3">
                        <div v-for="dimension in variantDimensions" :key="dimension.key">
                            <div class="flex items-center justify-between gap-3"><strong class="text-[10px] font-semibold text-slate-800">{{ dimension.label }}</strong><span class="text-[9px] text-slate-400">{{ selectedVariant?.[dimension.key] || 'Choose' }}</span></div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button v-for="value in dimension.values" :key="value" type="button" :class="['min-h-9 rounded-[6px] border px-3 text-[10px] font-semibold transition', selectedVariant?.[dimension.key] === value ? 'border-prinx-500 bg-prinx-50 text-prinx-700' : 'border-slate-200 bg-white text-slate-600 hover:border-prinx-300']" @click="chooseVariantOption(dimension.key, value)">{{ value }}</button>
                            </div>
                        </div>
                        <div v-if="selectedVariant" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-[9px]">
                            <span class="font-medium text-slate-600">Selected: {{ selectedVariant.display_name }}</span>
                            <span :class="selectedVariant.in_stock ? 'text-emerald-600' : 'text-red-600'">{{ selectedVariant.in_stock ? `${selectedVariant.stock_quantity} available` : 'Out of stock' }}</span>
                        </div>
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
                        <button type="button" class="flex h-[44px] items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[11px] font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!displayInStock" @click="add">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2 10h10l2-7H7M9 19.2a1.2 1.2 0 1 0 0 .1Zm7 0a1.2 1.2 0 1 0 0 .1Z"/></svg>
                            Add to Cart
                        </button>
                        <button type="button" class="flex h-[44px] items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[11px] font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!displayInStock" @click="buyNow">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-current"><path d="m13 2-7 12h5l-1 8 8-13h-5V2Z"/></svg>
                            Buy Now
                        </button>
                        <button type="button" class="flex h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 text-[10px] font-semibold text-slate-700 transition hover:border-prinx-300 hover:text-prinx-600" @click="wished = !wished">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] stroke-current" :class="wished ? 'fill-prinx-500 text-prinx-500' : 'fill-none'" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                            Add to Wishlist
                        </button>
                    </div>

                    <div v-if="hasFulfillment" class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div v-if="page.props.store.delivery_enabled" class="rounded-[7px] border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex items-start gap-2">
                                <svg viewBox="0 0 24 24" class="mt-0.5 h-5 w-5 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                <div><strong class="block text-[10px] font-semibold text-slate-800">Delivery available</strong><span class="mt-1 block text-[9px] leading-4 text-slate-500">{{ page.props.store.delivery_eta || 'Delivery time confirmed with your order' }}</span><span v-if="page.props.store.delivery_area" class="mt-0.5 block text-[9px] text-slate-400">{{ page.props.store.delivery_area }}</span></div>
                            </div>
                        </div>
                        <div v-if="page.props.store.pickup_enabled" class="rounded-[7px] border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex items-start gap-2">
                                <svg viewBox="0 0 24 24" class="mt-0.5 h-5 w-5 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16l-1 11H5L4 9Zm1-5h14l2 5H3l2-5Z"/></svg>
                                <div><strong class="block text-[10px] font-semibold text-slate-800">Store pickup available</strong><span class="mt-1 block text-[9px] leading-4 text-slate-500">{{ page.props.store.pickup_location || page.props.store.address || 'Prinx Tech store' }}</span><span v-if="page.props.store.pickup_instructions" class="mt-0.5 block text-[9px] text-slate-400">{{ page.props.store.pickup_instructions }}</span></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="paymentMethods.length" class="mt-3 rounded-[7px] border border-slate-200 bg-white px-3 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="mr-1 text-[10px] font-semibold text-slate-800">Payment options:</strong>
                            <span v-for="method in paymentMethods" :key="method.value" class="rounded-[5px] bg-slate-100 px-2 py-1 text-[9px] font-medium text-slate-700">{{ method.title }}</span>
                        </div>
                        <p v-if="page.props.store.payment_safety_note" class="mt-2 text-[9px] leading-4 text-emerald-700">{{ page.props.store.payment_safety_note }}</p>
                    </div>

                    <a
                        v-if="whatsappHref"
                        :href="whatsappHref"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 flex h-11 w-full items-center justify-center gap-2 rounded-[6px] border border-emerald-600 bg-white px-4 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50"
                        @click="trackWhatsApp('primary')"
                    >
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.1A8 8 0 1 1 20 11.5Z"/><path stroke-linecap="round" d="M8.5 8.5c.8 3 2.4 4.6 5.4 5.5"/></svg>
                        Ask about this product on WhatsApp
                    </a>

                    <div class="mt-5 grid overflow-hidden rounded-[7px] border border-slate-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-4 sm:border-r xl:border-b-0">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                            <div><strong class="block text-[9px] font-semibold text-slate-800">Delivery</strong><span class="mt-1 block text-[8px] text-slate-500">{{ page.props.store.delivery_enabled ? (page.props.store.delivery_eta || 'Available') : 'Contact us for options' }}</span></div>
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
                            <div><strong class="block text-[9px] font-semibold text-slate-800">Payment Options</strong><span class="mt-1 block text-[8px] text-slate-500">{{ paymentMethods.map((method) => method.title).join(' · ') }}</span></div>
                        </div>
                    </div>

                    <div v-if="related.length" class="mt-3">
                        <Link :href="`/compare?products=${product.slug},${related[0].slug}`" class="inline-flex h-9 items-center rounded-[6px] border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-700 hover:border-prinx-300 hover:text-prinx-600">Compare with similar products</Link>
                    </div>

                    <nav class="mt-3 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-500" aria-label="Purchase information">
                        <Link href="/pages/warranty" class="hover:text-prinx-600">Warranty information</Link>
                        <Link href="/pages/returns" class="hover:text-prinx-600">Returns & refunds</Link>
                        <Link href="/pages/shipping" class="hover:text-prinx-600">Delivery & pickup</Link>
                        <Link href="/pages/faq" class="hover:text-prinx-600">FAQ</Link>
                    </nav>
                </div>
            </section>

            <section class="mt-5 grid gap-3 lg:grid-cols-[2fr_1fr]">
                <article class="overflow-hidden rounded-[7px] border border-slate-200 bg-white">
                    <div class="flex border-b border-slate-200 px-2">
                        <button type="button" class="relative px-3 py-3 text-[9px] font-semibold" :class="activeTab === 'description' ? 'text-prinx-600' : 'text-slate-500'" @click="activeTab = 'description'">Description<span v-if="activeTab === 'description'" class="absolute inset-x-2 bottom-0 h-[2px] bg-prinx-500"></span></button>
                        <button type="button" class="relative px-3 py-3 text-[9px] font-medium" :class="activeTab === 'specs' ? 'text-prinx-600' : 'text-slate-500'" @click="activeTab = 'specs'">Specifications<span v-if="activeTab === 'specs'" class="absolute inset-x-2 bottom-0 h-[2px] bg-prinx-500"></span></button>
                        <button type="button" class="relative px-3 py-3 text-[9px] font-medium" :class="activeTab === 'reviews' ? 'text-prinx-600' : 'text-slate-500'" @click="activeTab = 'reviews'">Reviews ({{ reviewCount }})<span v-if="activeTab === 'reviews'" class="absolute inset-x-2 bottom-0 h-[2px] bg-prinx-500"></span></button>
                    </div>
                    <div class="min-h-[190px] px-4 py-4 text-[10px] leading-5 text-slate-600">
                        <ProductDescription v-if="activeTab === 'description'" :content="product.description" />
                        <div v-else-if="activeTab === 'specs'" class="divide-y divide-slate-100">
                            <div v-for="row in specificationRows" :key="row[0]" class="grid grid-cols-[140px_1fr] gap-4 py-2"><strong class="font-semibold text-slate-700">{{ row[0] }}</strong><span>{{ row[1] }}</span></div>
                            <p v-if="!specificationRows.length" class="py-8 text-center text-slate-400">No additional specifications available.</p>
                        </div>
                        <div v-else class="space-y-5">
                            <div v-if="reviewEligibility.can_review" class="rounded-[8px] border border-prinx-100 bg-prinx-50/40 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <strong class="text-[12px] font-semibold text-slate-900">{{ reviewEligibility.review ? 'Update your review' : 'Write a verified review' }}</strong>
                                        <p class="mt-1 text-[10px] text-slate-500">Only completed purchases can be reviewed. Your review appears after approval.</p>
                                    </div>
                                    <span v-if="reviewEligibility.review?.status" class="rounded-[6px] bg-white px-2 py-1 text-[9px] font-semibold capitalize text-slate-600">{{ reviewEligibility.review.status }}</span>
                                </div>
                                <form class="mt-4 grid gap-3" @submit.prevent="submitReview">
                                    <select v-model.number="reviewRating" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-[12px] outline-none focus:border-prinx-400" aria-label="Review rating">
                                        <option :value="5">5 — Excellent</option><option :value="4">4 — Very good</option><option :value="3">3 — Good</option><option :value="2">2 — Fair</option><option :value="1">1 — Poor</option>
                                    </select>
                                    <input v-model="reviewTitle" maxlength="120" class="h-11 rounded-[6px] border border-slate-200 bg-white px-3 text-[12px] outline-none focus:border-prinx-400" placeholder="Short review title (optional)">
                                    <textarea v-model="reviewBody" required minlength="10" maxlength="1500" rows="4" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2.5 text-[12px] leading-5 outline-none focus:border-prinx-400" placeholder="Tell other customers about the product"></textarea>
                                    <button type="submit" :disabled="reviewSubmitting" class="h-11 rounded-[6px] bg-prinx-500 px-4 text-[11px] font-semibold text-white transition hover:bg-prinx-600 disabled:opacity-50">{{ reviewSubmitting ? 'Submitting…' : 'Submit review' }}</button>
                                </form>
                            </div>
                            <div v-else class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[10px] text-slate-600">{{ reviewEligibility.reason }}</div>
                            <div v-if="reviews.length" class="divide-y divide-slate-100">
                                <article v-for="review in reviews" :key="review.id" class="py-4 first:pt-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2"><strong class="text-[11px] font-semibold text-slate-800">{{ review.reviewer_name }}</strong><span v-if="review.is_verified_purchase" class="rounded-[5px] bg-emerald-50 px-2 py-0.5 text-[8px] font-semibold text-emerald-700">Verified Purchase</span></div>
                                        <span class="text-[9px] text-slate-400">{{ review.created_at }}</span>
                                    </div>
                                    <div class="mt-1 tracking-[-1px] text-amber-500">{{ starsFor(review.rating) }}</div>
                                    <strong v-if="review.title" class="mt-2 block text-[11px] font-semibold text-slate-800">{{ review.title }}</strong>
                                    <p class="mt-1 whitespace-pre-line text-[10px] leading-5 text-slate-600">{{ review.body }}</p>
                                </article>
                            </div>
                            <div v-else class="py-8 text-center text-slate-400">No approved customer reviews yet.</div>
                        </div>
                    </div>
                </article>

                <article class="overflow-hidden rounded-[7px] border border-slate-200 bg-white">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><span class="text-[10px] font-semibold text-slate-800">Customer rating</span><button type="button" class="text-[9px] font-medium text-prinx-600" @click="activeTab = 'reviews'">View reviews</button></header>
                    <div class="flex min-h-[190px] flex-col items-center justify-center px-6 text-center">
                        <strong class="text-[34px] font-semibold leading-none text-slate-950">{{ reviewCount ? reviewAverage.toFixed(1) : '—' }}</strong>
                        <ProductRating class="mt-2 text-[16px]" :rating="reviewAverage" :count="reviewCount" />
                        <p class="mt-2 text-[10px] text-slate-500">{{ reviewCount ? 'Verified customer reviews' : 'No customer reviews yet' }}</p>
                        <button type="button" class="mt-4 h-10 rounded-[6px] border border-prinx-200 bg-white px-4 text-[10px] font-semibold text-prinx-700" @click="activeTab = 'reviews'">{{ reviewEligibility.can_review ? 'Write a review' : 'Read reviews' }}</button>
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
                            <ProductRating class="mt-1 text-[9px]" :rating="item.approved_reviews_average || 0" :count="item.approved_reviews_count || 0" />
                            <div class="mt-1 flex flex-wrap items-center gap-2"><strong class="text-[9px] font-semibold text-slate-800">{{ money(item.current_price) }}</strong><span v-if="Number(item.current_price) < Number(item.price)" class="text-[8px] text-slate-400 line-through">{{ money(item.price) }}</span></div>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" class="h-7 flex-1 rounded-[5px] border border-prinx-300 bg-white px-2 text-[8px] font-semibold text-prinx-600 hover:bg-prinx-500 hover:text-white disabled:border-slate-200 disabled:text-slate-400" :disabled="!item.in_stock" @click="addRelated(item)">{{ Number(item.active_variants_count || 0) > 0 ? 'Select options' : 'Add to Cart' }}</button>
                                <button type="button" class="flex h-7 w-8 items-center justify-center rounded-[5px] border border-slate-200 text-slate-500 hover:border-prinx-300 hover:text-prinx-600" aria-label="Add to wishlist"><svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg></button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-if="whatsappHref" class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/96 px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_28px_rgba(15,23,42,.10)] backdrop-blur lg:hidden">
            <a
                :href="whatsappHref"
                target="_blank"
                rel="noopener"
                class="mx-auto flex h-12 max-w-[560px] items-center justify-center gap-2 rounded-[7px] bg-emerald-600 px-4 text-[12px] font-semibold text-white"
                @click="trackWhatsApp('mobile_sticky')"
            >
                <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.1A8 8 0 1 1 20 11.5Z"/><path stroke-linecap="round" d="M8.5 8.5c.8 3 2.4 4.6 5.4 5.5"/></svg>
                Ask Prinx Tech on WhatsApp
            </a>
        </div>
    </AppLayout>
</template>