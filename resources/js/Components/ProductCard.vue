<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Storefront product card. The design comes from Admin → Settings →
 * Storefront appearance (shared as store.product_card_style); `variant`
 * overrides it, which the settings page uses for its live previews.
 */
const props = defineProps({
    product: { type: Object, required: true },
    variant: { type: String, default: '' },
    metric: { type: String, default: '' },
    sizes: { type: String, default: '(max-width: 639px) 48vw, (max-width: 1279px) 25vw, 240px' },
    eager: { type: Boolean, default: false },
});

const STYLES = ['clean', 'trust', 'compact', 'premium'];

const page = usePage();
const busy = ref(false);

const design = computed(() => {
    const chosen = props.variant || page.props.store?.product_card_style;
    return STYLES.includes(chosen) ? chosen : 'clean';
});
const currency = computed(() => page.props.store?.currency || 'TZS');
const money = (value) => `${currency.value} ${Math.round(Number(value || 0)).toLocaleString('en-US')}`;

const href = computed(() => `/products/${props.product.slug}`);
const regularPrice = computed(() => Number(props.product.price || 0));
const price = computed(() => Number(props.product.current_price ?? props.product.price ?? 0));
const onSale = computed(() => regularPrice.value > 0 && price.value < regularPrice.value);
const percentOff = computed(() => (onSale.value ? Math.max(1, Math.round(((regularPrice.value - price.value) / regularPrice.value) * 100)) : 0));
const saved = computed(() => (onSale.value ? regularPrice.value - price.value : 0));

const inStock = computed(() => props.product.in_stock !== false);
const unitsLeft = computed(() => Number(props.product.stock_quantity || 0));
const lowStock = computed(() => inStock.value && Boolean(props.product.low_stock) && unitsLeft.value > 0);
const lowStockBar = computed(() => {
    const threshold = Number(props.product.low_stock_threshold || 0) || unitsLeft.value || 1;
    return `${Math.max(10, Math.min(100, Math.round((unitsLeft.value / threshold) * 50)))}%`;
});
const hasOptions = computed(() => Number(props.product.active_variants_count || 0) > 0);

const reviewCount = computed(() => Number(props.product.approved_reviews_count || 0));
const rating = computed(() => Math.max(0, Math.min(5, Number(props.product.approved_reviews_average || 0))).toFixed(1));
const ratingLabel = computed(() => `${rating.value} out of 5 from ${reviewCount.value} ${reviewCount.value === 1 ? 'review' : 'reviews'}`);

const brand = computed(() => props.product.brand?.name || props.product.category?.name || '');
const category = computed(() => props.product.category?.name || props.product.brand?.name || '');
const warranty = computed(() => {
    const text = String(props.product.warranty || '').trim().replace(/\s*warranty$/i, '');
    return text.length > 0 && text.length <= 24 ? text : '';
});
const stockText = computed(() => {
    if (!inStock.value) return 'Out of stock';
    if (lowStock.value) return `Only ${unitsLeft.value} left`;
    return 'In stock';
});
const actionLabel = computed(() => {
    if (!inStock.value) return `${props.product.name} is out of stock`;
    if (hasOptions.value) return `Choose options for ${props.product.name}`;
    return `Add ${props.product.name} to cart`;
});

const wished = computed(() => (page.props.wishlistProductIds || []).map(Number).includes(Number(props.product.id)));
const imageAlt = computed(() => `${props.product.name}${props.product.brand?.name ? ` by ${props.product.brand.name}` : ''}`);

function addToCart() {
    if (!inStock.value || busy.value) return;
    if (hasOptions.value) {
        router.visit(href.value);
        return;
    }
    router.post(`/cart/${props.product.slug}`, { quantity: 1 }, {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
    });
}

function toggleWishlist() {
    if (!page.props.auth?.user) {
        router.visit('/login');
        return;
    }
    router.post(`/wishlist/${props.product.slug}`, {}, { preserveScroll: true });
}
</script>

<template>
    <article class="pc" :class="`pc--${design}`">
        <!-- A · Clean & minimal -->
        <div v-if="design === 'clean'" class="pc-card">
            <div class="pc-photo">
                <Link :href="href" class="pc-photo-link" tabindex="-1" aria-hidden="true">
                    <img :src="product.image_url" :srcset="product.image_srcset || undefined" :sizes="sizes" :alt="imageAlt" :loading="eager ? 'eager' : 'lazy'" decoding="async">
                </Link>
                <span v-if="percentOff" class="pc-tag">-{{ percentOff }}%</span>
                <button type="button" class="pc-fav" :class="{ 'is-on': wished }" :aria-pressed="wished" :aria-label="wished ? `Remove ${product.name} from wishlist` : `Save ${product.name} to wishlist`" @click="toggleWishlist">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z" /></svg>
                </button>
                <span v-if="!inStock" class="pc-sold">Out of stock</span>
            </div>
            <div class="pc-body">
                <span v-if="brand" class="pc-brand">{{ brand }}</span>
                <Link :href="href" class="pc-name">{{ product.name }}</Link>
                <span v-if="reviewCount" class="pc-stars" :aria-label="ratingLabel"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8L1.5 7.7l5.9-.8z" /></svg><b>{{ rating }}</b><span>({{ reviewCount }})</span></span>
                <span v-if="metric" class="pc-metric">{{ metric }}</span>
                <div class="pc-row">
                    <div class="pc-prices">
                        <strong class="pc-price">{{ money(price) }}</strong>
                        <s v-if="onSale" class="pc-was">{{ money(regularPrice) }}</s>
                        <span v-else-if="lowStock" class="pc-low">Only {{ unitsLeft }} left</span>
                    </div>
                    <button type="button" class="pc-round" :disabled="!inStock || busy" :aria-label="actionLabel" :title="hasOptions ? 'Choose options' : 'Add to cart'" @click="addToCart">
                        <svg v-if="hasOptions" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L20.5 8H6.2" /><circle cx="10" cy="20" r="1.3" /><circle cx="17" cy="20" r="1.3" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- B · Trust & clear button -->
        <div v-else-if="design === 'trust'" class="pc-card">
            <div class="pc-photo">
                <Link :href="href" class="pc-photo-link" tabindex="-1" aria-hidden="true">
                    <img :src="product.image_url" :srcset="product.image_srcset || undefined" :sizes="sizes" :alt="imageAlt" :loading="eager ? 'eager' : 'lazy'" decoding="async">
                </Link>
                <span v-if="percentOff" class="pc-tag">-{{ percentOff }}% OFF</span>
                <button type="button" class="pc-fav" :class="{ 'is-on': wished }" :aria-pressed="wished" :aria-label="wished ? `Remove ${product.name} from wishlist` : `Save ${product.name} to wishlist`" @click="toggleWishlist">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z" /></svg>
                </button>
            </div>
            <div class="pc-body">
                <Link :href="href" class="pc-name">{{ product.name }}</Link>
                <span v-if="reviewCount" class="pc-stars" :aria-label="ratingLabel"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8L1.5 7.7l5.9-.8z" /></svg><b>{{ rating }}</b><span>({{ reviewCount }})</span></span>
                <div class="pc-prices">
                    <strong class="pc-price">{{ money(price) }}</strong>
                    <s v-if="onSale" class="pc-was">{{ money(regularPrice) }}</s>
                </div>
                <div class="pc-meta">
                    <span class="pc-dot" :class="{ 'is-low': lowStock, 'is-out': !inStock }" aria-hidden="true"></span>
                    <span>{{ stockText }}</span>
                    <template v-if="warranty">
                        <span class="pc-sep" aria-hidden="true">·</span>
                        <span class="pc-warranty" :title="`${warranty} warranty`">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z" /><path d="M9 12l2 2 4-4" /></svg>
                            <span>{{ warranty }}</span>
                        </span>
                    </template>
                </div>
                <span v-if="metric" class="pc-metric">{{ metric }}</span>
                <button type="button" class="pc-cta" :class="{ 'is-options': hasOptions && inStock }" :disabled="!inStock || busy" :aria-label="actionLabel" @click="addToCart">
                    <template v-if="!inStock">Out of stock</template>
                    <template v-else-if="hasOptions">Choose options</template>
                    <template v-else>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L20.5 8H6.2" /><circle cx="10" cy="20" r="1.3" /><circle cx="17" cy="20" r="1.3" /></svg>
                        Add to cart
                    </template>
                </button>
            </div>
        </div>

        <!-- C · Marketplace compact -->
        <div v-else-if="design === 'compact'" class="pc-card">
            <div class="pc-photo">
                <Link :href="href" class="pc-photo-link" tabindex="-1" aria-hidden="true">
                    <img :src="product.image_url" :srcset="product.image_srcset || undefined" :sizes="sizes" :alt="imageAlt" :loading="eager ? 'eager' : 'lazy'" decoding="async">
                </Link>
                <span v-if="percentOff" class="pc-tag">-{{ percentOff }}%</span>
                <button type="button" class="pc-fav" :class="{ 'is-on': wished }" :aria-pressed="wished" :aria-label="wished ? `Remove ${product.name} from wishlist` : `Save ${product.name} to wishlist`" @click="toggleWishlist">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z" /></svg>
                </button>
                <button type="button" class="pc-plus" :disabled="!inStock || busy" :aria-label="actionLabel" :title="hasOptions ? 'Choose options' : 'Add to cart'" @click="addToCart">
                    <svg v-if="hasOptions" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                </button>
            </div>
            <div class="pc-body">
                <Link :href="href" class="pc-name">{{ product.name }}</Link>
                <strong class="pc-price">{{ money(price) }}</strong>
                <div v-if="onSale" class="pc-save"><s>{{ money(regularPrice) }}</s><b>You save {{ money(saved) }}</b></div>
                <template v-if="lowStock">
                    <span class="pc-low">Only {{ unitsLeft }} left</span>
                    <span class="pc-bar" aria-hidden="true"><i :style="{ width: lowStockBar }"></i></span>
                </template>
                <span v-if="!inStock" class="pc-out">Out of stock</span>
                <span v-if="metric" class="pc-metric">{{ metric }}</span>
                <span v-if="reviewCount" class="pc-stars" :aria-label="ratingLabel"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8L1.5 7.7l5.9-.8z" /></svg><b>{{ rating }}</b><span>({{ reviewCount }})</span></span>
            </div>
        </div>

        <!-- D · Premium showcase -->
        <div v-else class="pc-card">
            <div class="pc-photo">
                <Link :href="href" class="pc-photo-link" tabindex="-1" aria-hidden="true">
                    <img :src="product.image_url" :srcset="product.image_srcset || undefined" :sizes="sizes" :alt="imageAlt" :loading="eager ? 'eager' : 'lazy'" decoding="async">
                </Link>
                <span v-if="percentOff" class="pc-tag">Save {{ percentOff }}%</span>
                <span v-else-if="lowStock" class="pc-tag is-low">Only {{ unitsLeft }} left</span>
                <button type="button" class="pc-fav" :class="{ 'is-on': wished }" :aria-pressed="wished" :aria-label="wished ? `Remove ${product.name} from wishlist` : `Save ${product.name} to wishlist`" @click="toggleWishlist">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z" /></svg>
                </button>
            </div>
            <div class="pc-body">
                <span v-if="category" class="pc-category">{{ category }}</span>
                <Link :href="href" class="pc-name">{{ product.name }}</Link>
                <span v-if="reviewCount" class="pc-stars" :aria-label="ratingLabel"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8L1.5 7.7l5.9-.8z" /></svg><b>{{ rating }}</b><span>({{ reviewCount }})</span></span>
                <span v-if="metric" class="pc-metric">{{ metric }}</span>
                <div class="pc-row">
                    <div class="pc-prices">
                        <strong class="pc-price">{{ money(price) }}</strong>
                        <s v-if="onSale" class="pc-was">{{ money(regularPrice) }}</s>
                    </div>
                    <button type="button" class="pc-pill" :disabled="!inStock || busy" :aria-label="actionLabel" @click="addToCart">
                        <template v-if="!inStock">Sold out</template>
                        <template v-else>
                            <span>{{ hasOptions ? 'Options' : 'Add' }}</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
/* Shared ------------------------------------------------------------------ */
.pc { container: pcard / inline-size; min-width: 0; height: 100%; }
.pc-card { position: relative; display: flex; height: 100%; flex-direction: column; overflow: hidden; background: #fff; transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease; }
.pc-photo { position: relative; display: flex; aspect-ratio: 1 / 1; align-items: center; justify-content: center; overflow: hidden; }
.pc-photo-link { display: block; width: 100%; height: 100%; }
.pc-photo img { width: 100%; height: 100%; object-fit: contain; transition: transform .35s ease; }
.pc-card:hover .pc-photo img { transform: scale(1.04); }
.pc-body { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.pc-name { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; font-weight: 600; color: #0f172a; transition: color .15s ease; }
.pc-name:hover { color: #ee3500; }
.pc-price { font-weight: 800; color: #0f172a; white-space: nowrap; letter-spacing: -.01em; }
.pc-was { font-size: 12px; color: #94a3b8; white-space: nowrap; }
.pc-prices { display: flex; flex-direction: column; min-width: 0; }
.pc-stars { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #94a3b8; }
.pc-stars svg { width: 12px; height: 12px; fill: #f59e0b; }
.pc-stars b { color: #0f172a; font-weight: 700; }
.pc-metric { font-size: 11px; font-weight: 500; color: #64748b; }
.pc-low { font-size: 11px; font-weight: 600; color: #d97706; white-space: nowrap; }
.pc-fav { position: absolute; z-index: 2; display: flex; background: transparent; align-items: center; justify-content: center; color: #475569; transition: color .15s ease, background-color .15s ease, transform .15s ease; }
.pc-fav svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.pc-fav:hover { color: #ee3500; transform: scale(1.06); }
.pc .pc-fav.is-on { color: #ff4d0a; }
.pc .pc-fav.is-on svg { fill: currentColor; }
.pc-tag { position: absolute; z-index: 1; font-size: 11px; font-weight: 700; line-height: 1.2; white-space: nowrap; pointer-events: none; }
button:disabled { cursor: not-allowed; }
button:focus-visible, .pc-name:focus-visible { outline: 2px solid #ff4d0a; outline-offset: 2px; }

/* A · Clean & minimal ---------------------------------------------------- */
.pc--clean .pc-card { border: 1px solid #edf0f4; border-radius: 16px; }
.pc--clean .pc-card:hover { border-color: #e2e8f0; box-shadow: 0 12px 28px -14px rgba(15, 23, 42, .22); }
.pc--clean .pc-photo { background: #f5f6f8; padding: 18px; }
.pc--clean .pc-photo img { mix-blend-mode: multiply; }
.pc--clean .pc-tag { left: 10px; top: 10px; border-radius: 999px; background: #ff4d0a; padding: 4px 8px; color: #fff; }
.pc--clean .pc-fav { right: 10px; top: 10px; width: 34px; height: 34px; border-radius: 999px; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
.pc--clean .pc-sold { position: absolute; inset: auto 10px 10px 10px; border-radius: 8px; background: rgba(15, 23, 42, .72); padding: 5px; text-align: center; font-size: 11px; font-weight: 600; color: #fff; pointer-events: none; }
.pc--clean .pc-body { gap: 6px; padding: 12px 14px 14px; }
.pc--clean .pc-brand { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
.pc--clean .pc-name { min-height: 40px; font-size: 14px; line-height: 20px; }
.pc--clean .pc-row { display: flex; margin-top: auto; align-items: flex-end; justify-content: space-between; gap: 8px; padding-top: 4px; }
.pc--clean .pc-price { font-size: 17px; }
.pc--clean .pc-round { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 999px; background: #0f172a; color: #fff; transition: background-color .15s ease, transform .15s ease; }
.pc--clean .pc-round:hover:not(:disabled) { background: #ff4d0a; transform: translateY(-1px); }
.pc--clean .pc-round:disabled { background: #e2e8f0; color: #94a3b8; }
.pc--clean .pc-round svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

@container pcard (max-width: 200px) {
    .pc--clean .pc-photo { padding: 14px; }
    .pc--clean .pc-body { padding: 10px 11px 12px; }
    .pc--clean .pc-name { font-size: 13px; line-height: 18px; min-height: 36px; }
    .pc--clean .pc-price { font-size: 15px; }
    .pc--clean .pc-was { font-size: 11px; }
    .pc--clean .pc-round { width: 34px; height: 34px; }
    .pc--clean .pc-fav { width: 30px; height: 30px; }
}

/* B · Trust & clear button ---------------------------------------------- */
.pc--trust .pc-card { border: 1px solid #e5e7eb; border-radius: 14px; }
.pc--trust .pc-card:hover { border-color: #fed7c3; box-shadow: 0 12px 28px -16px rgba(238, 53, 0, .28); }
.pc--trust .pc-photo { border-bottom: 1px solid #f1f5f9; background: #fff; padding: 16px; }
.pc--trust .pc-tag { left: 10px; top: 10px; border-radius: 6px; background: #fff1ea; padding: 4px 8px; color: #ee3500; }
.pc--trust .pc-fav { right: 8px; top: 8px; width: 32px; height: 32px; border-radius: 8px; color: #64748b; }
.pc--trust .pc-body { gap: 6px; padding: 12px; }
.pc--trust .pc-name { min-height: 40px; font-size: 14px; line-height: 20px; color: #1e293b; }
.pc--trust .pc-prices { flex-direction: row; flex-wrap: wrap; align-items: baseline; gap: 2px 8px; }
.pc--trust .pc-price { font-size: 18px; color: #ee3500; }
.pc--trust .pc-meta { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: 2px 6px; font-size: 11.5px; color: #475569; }
.pc--trust .pc-dot { display: inline-block; width: 7px; height: 7px; flex-shrink: 0; border-radius: 999px; background: #22c55e; }
.pc--trust .pc-dot.is-low { background: #f59e0b; }
.pc--trust .pc-dot.is-out { background: #ef4444; }
.pc--trust .pc-sep { color: #cbd5e1; }
.pc--trust .pc-warranty { display: inline-flex; min-width: 0; align-items: center; gap: 3px; white-space: nowrap; }
.pc--trust .pc-warranty svg { width: 13px; height: 13px; flex-shrink: 0; fill: none; stroke: #16a34a; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.pc--trust .pc-cta { display: flex; height: 40px; margin-top: auto; align-items: center; justify-content: center; gap: 8px; border: 1.5px solid #ff4d0a; border-radius: 10px; background: #ff4d0a; font-size: 13.5px; font-weight: 700; color: #fff; transition: background-color .15s ease, border-color .15s ease; }
.pc--trust .pc-meta { margin-bottom: 4px; }
.pc--trust .pc-cta:hover:not(:disabled) { border-color: #ee3500; background: #ee3500; }
.pc--trust .pc-cta.is-options { border-color: #cbd5e1; background: #fff; color: #0f172a; }
.pc--trust .pc-cta.is-options:hover { border-color: #0f172a; background: #fff; }
.pc--trust .pc-cta:disabled { border-color: #f1f5f9; background: #f1f5f9; color: #64748b; }
.pc--trust .pc-cta svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

@container pcard (max-width: 200px) {
    .pc--trust .pc-photo { padding: 12px; }
    .pc--trust .pc-body { padding: 10px; }
    .pc--trust .pc-name { font-size: 13px; line-height: 18px; min-height: 36px; }
    .pc--trust .pc-price { font-size: 16px; }
    .pc--trust .pc-meta { font-size: 11px; }
    .pc--trust .pc-cta { height: 38px; font-size: 13px; }
    .pc--trust .pc-cta svg { display: none; }
}

/* C · Marketplace compact ------------------------------------------------ */
.pc--compact .pc-card { border-radius: 12px; box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 0 0 1px rgba(15, 23, 42, .05); }
.pc--compact .pc-card:hover { box-shadow: 0 10px 24px -12px rgba(15, 23, 42, .25), 0 0 0 1px rgba(15, 23, 42, .06); }
.pc--compact .pc-photo { background: #fff; padding: 14px; }
.pc--compact .pc-tag { left: 0; top: 12px; border-radius: 0 999px 999px 0; background: #ff4d0a; padding: 4px 10px 4px 8px; font-size: 12px; font-weight: 800; color: #fff; }
.pc--compact .pc-fav { right: 8px; top: 8px; width: 32px; height: 32px; color: #94a3b8; }
.pc--compact .pc-plus { position: absolute; right: 10px; bottom: 10px; z-index: 2; display: flex; width: 40px; height: 40px; align-items: center; justify-content: center; border-radius: 999px; background: #ff4d0a; color: #fff; box-shadow: 0 6px 14px rgba(255, 77, 10, .35); transition: transform .15s ease, background-color .15s ease; }
.pc--compact .pc-plus:hover:not(:disabled) { background: #ee3500; transform: scale(1.06); }
.pc--compact .pc-plus:disabled { background: #cbd5e1; box-shadow: none; }
.pc--compact .pc-plus svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
.pc--compact .pc-body { gap: 4px; padding: 4px 12px 12px; }
.pc--compact .pc-name { min-height: 38px; font-size: 13.5px; font-weight: 500; line-height: 19px; color: #334155; }
.pc--compact .pc-price { margin-top: 2px; font-size: 17px; }
.pc--compact .pc-save { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: 0 6px; font-size: 11.5px; }
.pc--compact .pc-save s { color: #94a3b8; }
.pc--compact .pc-save b { font-weight: 700; color: #16a34a; white-space: nowrap; }
.pc--compact .pc-low { color: #b45309; }
.pc--compact .pc-bar { display: block; height: 5px; overflow: hidden; border-radius: 999px; background: #fef3c7; }
.pc--compact .pc-bar i { display: block; height: 100%; border-radius: 999px; background: #f59e0b; }
.pc--compact .pc-out { font-size: 11.5px; font-weight: 600; color: #ef4444; }
.pc--compact .pc-stars { margin-top: auto; padding-top: 4px; font-size: 11px; }
.pc--compact .pc-stars svg { width: 11px; height: 11px; }

@container pcard (max-width: 200px) {
    .pc--compact .pc-photo { padding: 12px; }
    .pc--compact .pc-body { padding: 4px 10px 10px; }
    .pc--compact .pc-name { font-size: 13px; line-height: 18px; min-height: 36px; }
    .pc--compact .pc-price { font-size: 15px; }
    .pc--compact .pc-save s { display: none; }
    .pc--compact .pc-plus { width: 36px; height: 36px; right: 8px; bottom: 8px; }
}

/* D · Premium showcase --------------------------------------------------- */
.pc--premium .pc-card { border-radius: 22px; padding: 8px; box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .18); }
.pc--premium .pc-card:hover { box-shadow: 0 18px 40px -14px rgba(15, 23, 42, .26); transform: translateY(-2px); }
.pc--premium .pc-photo { border-radius: 16px; background: radial-gradient(circle at 50% 38%, #fff 0%, #fff4ed 70%, #ffe6d4 100%); padding: 20px; }
.pc--premium .pc-photo::before { content: ''; position: absolute; left: 22%; right: 22%; bottom: 12px; height: 12px; border-radius: 50%; background: rgba(198, 37, 0, .10); filter: blur(6px); pointer-events: none; }
.pc--premium .pc-photo-link { position: relative; } /* no z-index: a stacking context would stop the multiply blend */
.pc--premium .pc-photo img { mix-blend-mode: multiply; }
.pc--premium .pc-tag { left: 12px; top: 12px; z-index: 2; border-radius: 999px; background: #0f172a; padding: 5px 9px; color: #fff; }
.pc--premium .pc-tag.is-low { background: #f59e0b; }
.pc--premium .pc-fav { right: 12px; top: 12px; width: 34px; height: 34px; border-radius: 999px; background: rgba(255, 255, 255, .85); color: #0f172a; }
.pc--premium .pc-body { gap: 5px; padding: 12px 8px 6px; }
.pc--premium .pc-category { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #ff4d0a; }
.pc--premium .pc-name { min-height: 40px; font-size: 14.5px; line-height: 20px; }
.pc--premium .pc-row { display: flex; margin-top: auto; align-items: center; justify-content: space-between; gap: 8px; padding-top: 6px; }
.pc--premium .pc-price { font-size: 17px; line-height: 1.15; }
.pc--premium .pc-was { font-size: 11.5px; }
.pc--premium .pc-pill { display: flex; height: 38px; flex-shrink: 0; align-items: center; justify-content: center; gap: 6px; border-radius: 999px; background: #0f172a; padding: 0 14px; font-size: 13px; font-weight: 700; color: #fff; transition: background-color .15s ease; }
.pc--premium .pc-pill:hover:not(:disabled) { background: #ff4d0a; }
.pc--premium .pc-pill:disabled { background: #f1f5f9; color: #64748b; }
.pc--premium .pc-pill svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

@container pcard (max-width: 235px) {
    .pc--premium .pc-card { border-radius: 18px; padding: 6px; }
    .pc--premium .pc-photo { border-radius: 13px; padding: 14px; }
    .pc--premium .pc-body { padding: 10px 4px 4px; }
    .pc--premium .pc-name { font-size: 13px; line-height: 18px; min-height: 36px; }
    .pc--premium .pc-row { flex-direction: column; align-items: stretch; gap: 8px; }
    .pc--premium .pc-price { font-size: 15px; }
}
</style>
