<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    cart: Object,
    recommended: { type: Array, default: () => [] },
});

const page = usePage();
const store = computed(() => page.props.store || {});
const currency = computed(() => store.value.currency || 'TZS');
const money = (value) => `${currency.value} ${Number(value || 0).toLocaleString('en-TZ', { maximumFractionDigits: 0 })}`;
const itemCount = computed(() => Number(props.cart?.count || 0));

const update = (product, quantity) => {
    const max = product.track_stock ? Number(product.stock_quantity || 0) : 99;
    const next = Math.max(0, Math.min(max || 99, Number(quantity)));
    router.patch(`/cart/${product.slug}`, { quantity: next }, { preserveScroll: true });
};
const decrease = (line) => update(line.product, Number(line.quantity) - 1);
const increase = (line) => update(line.product, Number(line.quantity) + 1);
const remove = (product) => router.delete(`/cart/${product.slug}`, { preserveScroll: true });
const clear = () => confirm('Clear all products from your cart?') && router.delete('/cart', { preserveScroll: true });
const addRecommended = (product) => router.post(`/cart/${product.slug}`, { quantity: 1 }, { preserveScroll: true });
const originalPrice = (product) => Number(product.price || 0) > Number(product.current_price || 0) ? Number(product.price) : null;
const discount = (product) => originalPrice(product) ? Math.round((1 - Number(product.current_price) / originalPrice(product)) * 100) : 0;
</script>

<template>
    <AppLayout>
        <Head title="Shopping Cart"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>

        <main class="bg-[#fbfbfb]">
            <div class="container-shell pb-7 pt-4 sm:pt-5">
                <nav class="flex items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                    <span>/</span>
                    <span class="text-prinx-600">Cart</span>
                </nav>

                <div class="mt-3">
                    <h1 class="text-[28px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[34px]">Shopping Cart</h1>
                    <p class="mt-1 text-xs text-slate-500">Review your items, update quantities, and proceed to checkout.</p>
                </div>

                <div v-if="cart.items.length" class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
                    <div class="min-w-0">
                        <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                            <div class="hidden grid-cols-[minmax(0,1.7fr)_150px_150px_160px_115px] items-center gap-3 border-b border-slate-200 px-5 py-3 text-xs font-semibold text-slate-700 lg:grid">
                                <span>Product</span><span class="text-center">Price</span><span class="text-center">Quantity</span><span class="text-center">Subtotal</span><span>Actions</span>
                            </div>

                            <article v-for="line in cart.items" :key="line.product.id" class="border-b border-slate-200 last:border-b-0">
                                <div class="hidden grid-cols-[minmax(0,1.7fr)_150px_150px_160px_115px] items-center gap-3 px-5 py-4 lg:grid">
                                    <div class="flex min-w-0 items-center gap-4">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-[4px] bg-prinx-500 text-white">
                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 12 4 4 8-9"/></svg>
                                        </span>
                                        <Link :href="`/products/${line.product.slug}`" class="flex h-[88px] w-[88px] shrink-0 items-center justify-center overflow-hidden rounded-[6px] bg-slate-50 p-1.5">
                                            <img :src="line.product.image_url" :alt="line.product.name" class="h-full w-full object-contain">
                                        </Link>
                                        <div class="min-w-0">
                                            <Link :href="`/products/${line.product.slug}`" class="line-clamp-2 text-sm font-semibold text-slate-950 transition hover:text-prinx-600">{{ line.product.name }}</Link>
                                            <p v-if="line.product.color" class="mt-1 text-xs text-slate-500">{{ line.product.color }}</p>
                                            <p class="mt-1 text-xs font-semibold" :class="line.product.in_stock ? 'text-emerald-600' : 'text-red-500'">{{ line.product.in_stock ? 'In Stock' : 'Out of Stock' }}</p>
                                            <p v-if="line.product.warranty" class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-500">
                                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg>
                                                {{ line.product.warranty }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="text-center text-xs font-semibold text-slate-800">{{ money(line.unit_price) }}</div>

                                    <div class="flex justify-center">
                                        <div class="inline-flex h-9 items-center overflow-hidden rounded-[6px] border border-slate-200 bg-white">
                                            <button type="button" class="flex h-full w-9 items-center justify-center text-lg text-slate-600 transition hover:bg-slate-50 disabled:opacity-40" :disabled="line.quantity <= 1" @click="decrease(line)">−</button>
                                            <span class="flex h-full min-w-[40px] items-center justify-center border-x border-slate-200 text-xs font-semibold text-slate-900">{{ line.quantity }}</span>
                                            <button type="button" class="flex h-full w-9 items-center justify-center text-lg text-slate-600 transition hover:bg-slate-50" @click="increase(line)">+</button>
                                        </div>
                                    </div>

                                    <div class="text-center text-xs font-semibold text-slate-900">{{ money(line.line_total) }}</div>

                                    <div>
                                        <button type="button" class="flex items-center gap-2 text-xs font-medium text-slate-700 transition hover:text-red-600" @click="remove(line.product)">
                                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5M14 11v5"/></svg>
                                            Remove
                                        </button>
                                        <Link :href="`/products/${line.product.slug}`" class="mt-3 flex items-center gap-2 text-xs font-medium text-slate-700 transition hover:text-prinx-600">
                                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                                            View item
                                        </Link>
                                    </div>
                                </div>

                                <div class="p-4 lg:hidden">
                                    <div class="flex gap-3">
                                        <Link :href="`/products/${line.product.slug}`" class="flex h-24 w-24 shrink-0 items-center justify-center rounded-[7px] bg-slate-50 p-2"><img :src="line.product.image_url" :alt="line.product.name" class="h-full w-full object-contain"></Link>
                                        <div class="min-w-0 flex-1">
                                            <Link :href="`/products/${line.product.slug}`" class="line-clamp-2 text-sm font-semibold text-slate-950">{{ line.product.name }}</Link>
                                            <p v-if="line.product.color" class="mt-1 text-xs text-slate-500">{{ line.product.color }}</p>
                                            <p class="mt-1 text-xs font-semibold text-emerald-600">{{ line.product.in_stock ? 'In Stock' : 'Out of Stock' }}</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-950">{{ money(line.unit_price) }}</p>
                                        </div>
                                        <button type="button" class="self-start p-1 text-slate-500" aria-label="Remove product" @click="remove(line.product)"><svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13"/></svg></button>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                        <div class="inline-flex h-9 items-center overflow-hidden rounded-[6px] border border-slate-200"><button class="h-full w-9" :disabled="line.quantity <= 1" @click="decrease(line)">−</button><span class="flex h-full min-w-[40px] items-center justify-center border-x border-slate-200 text-xs font-semibold">{{ line.quantity }}</span><button class="h-full w-9" @click="increase(line)">+</button></div>
                                        <div class="text-right"><p class="text-xs text-slate-500">Subtotal</p><strong class="mt-1 block text-sm text-slate-950">{{ money(line.line_total) }}</strong></div>
                                    </div>
                                </div>
                            </article>

                            <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <Link href="/shop" class="inline-flex items-center gap-2 text-xs font-semibold text-prinx-600 transition hover:text-prinx-700">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14 7-5 5 5 5M9 12h10"/></svg>
                                    Continue Shopping
                                </Link>
                                <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 text-xs font-medium text-slate-700 transition hover:border-red-200 hover:text-red-600" @click="clear">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13"/></svg>
                                    Clear Cart
                                </button>
                            </div>
                        </section>

                        <section v-if="recommended.length" class="mt-5">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="text-lg font-semibold text-slate-950">You May Also Like</h2>
                                <Link href="/shop" class="text-xs font-semibold text-prinx-600 hover:text-prinx-700">View all</Link>
                            </div>
                            <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                                <article v-for="product in recommended" :key="product.id" class="group relative rounded-[8px] border border-slate-200 bg-white p-3 transition hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.05)]">
                                    <span v-if="discount(product)" class="absolute right-2 top-2 z-10 rounded-[5px] bg-prinx-50 px-2 py-1 text-xs font-semibold text-prinx-600">-{{ discount(product) }}%</span>
                                    <Link :href="`/products/${product.slug}`" class="flex h-[132px] items-center justify-center rounded-[6px] bg-slate-50 p-2"><img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain transition group-hover:scale-[1.03]"></Link>
                                    <Link :href="`/products/${product.slug}`" class="mt-3 line-clamp-2 min-h-[38px] text-xs font-semibold leading-5 text-slate-900 hover:text-prinx-600">{{ product.name }}</Link>
                                    <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1"><strong class="text-xs text-slate-950">{{ money(product.current_price) }}</strong><span v-if="originalPrice(product)" class="text-xs text-slate-400 line-through">{{ money(originalPrice(product)) }}</span></div>
                                    <button type="button" class="mt-3 flex h-9 w-full items-center justify-center rounded-[6px] border border-prinx-300 bg-white text-xs font-semibold text-prinx-600 transition hover:bg-prinx-500 hover:text-white" @click="addRecommended(product)">Add to Cart</button>
                                </article>
                            </div>
                        </section>
                    </div>

                    <aside class="space-y-3 xl:sticky xl:top-4 xl:self-start">
                        <section class="rounded-[9px] border border-slate-200 bg-white p-5 shadow-[0_5px_20px_rgba(15,23,42,.025)]">
                            <h2 class="text-xl font-semibold text-slate-950">Order Summary</h2>
                            <div class="mt-5 space-y-3 text-xs">
                                <div class="flex items-center justify-between gap-4"><span class="text-slate-700">Subtotal ({{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }})</span><strong class="text-slate-900">{{ money(cart.subtotal) }}</strong></div>
                                <div class="flex items-center justify-between gap-4"><span class="text-slate-700">Estimated Shipping</span><strong class="text-slate-900">{{ cart.shipping ? money(cart.shipping) : 'Calculated at checkout' }}</strong></div>
                            </div>

                            <div class="mt-5 border-t border-slate-200 pt-4">
                                <div class="flex items-center justify-between gap-4"><span class="text-base font-semibold text-slate-950">Grand Total</span><strong class="text-xl font-semibold text-prinx-600">{{ money(cart.total) }}</strong></div>
                                <p class="mt-1 text-xs text-slate-400">Inclusive of applicable taxes</p>
                            </div>

                            <div class="mt-5 flex h-10 overflow-hidden rounded-[6px] border border-slate-200 bg-slate-50 opacity-70" title="Coupon codes are not currently enabled">
                                <input disabled class="min-w-0 flex-1 border-0 bg-transparent px-3 text-xs outline-none" placeholder="Enter coupon code">
                                <button disabled class="w-[88px] bg-slate-200 text-xs font-semibold text-slate-500">Apply</button>
                            </div>

                            <div class="mt-4 flex items-start gap-3 rounded-[7px] bg-prinx-50 px-4 py-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] bg-white text-prinx-600">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                </span>
                                <div><strong class="text-xs text-prinx-700">Estimated Delivery</strong><p class="mt-1 text-xs font-semibold text-slate-800">Today, 20 – 40 mins</p><p class="mt-1 text-xs text-slate-500">Delivery time depends on your location.</p></div>
                            </div>

                            <Link href="/checkout" class="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-5 text-xs font-semibold text-white transition hover:bg-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                Proceed to Checkout
                            </Link>
                            <Link href="/checkout" class="mt-2.5 flex h-11 w-full items-center justify-center gap-2 rounded-[6px] border border-prinx-300 bg-white px-5 text-xs font-semibold text-prinx-600 transition hover:bg-prinx-50">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><rect x="6" y="2" width="12" height="20" rx="2"/><path stroke-linecap="round" d="M9 5h6M10 19h4"/></svg>
                                Mobile Money at Checkout
                            </Link>

                            <div v-if="store.payment_strip_url" class="mt-4 flex items-center justify-center gap-3 border-t border-slate-100 pt-3">
                                <span class="shrink-0 text-xs text-slate-500">We accept</span>
                                <img :src="store.payment_strip_url" alt="Accepted payment methods" class="h-auto max-h-[28px] min-w-0 max-w-[220px] object-contain">
                            </div>
                        </section>

                        <section class="grid grid-cols-2 gap-px overflow-hidden rounded-[8px] border border-slate-200 bg-slate-200">
                            <div class="flex gap-3 bg-white p-3"><svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8h12l-1 12H7L6 8Zm3 0a3 3 0 0 1 6 0M9 12l2 2 4-4"/></svg><div><strong class="text-xs text-slate-900">Secure Payment</strong><p class="mt-1 text-xs leading-4 text-slate-500">Safe and encrypted payments</p></div></div>
                            <div class="flex gap-3 bg-white p-3"><svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg><div><strong class="text-xs text-slate-900">Genuine Products</strong><p class="mt-1 text-xs leading-4 text-slate-500">Original products and warranty</p></div></div>
                            <div class="flex gap-3 bg-white p-3"><svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg><div><strong class="text-xs text-slate-900">Fast Delivery</strong><p class="mt-1 text-xs leading-4 text-slate-500">20 – 40 mins where available</p></div></div>
                            <div class="flex gap-3 bg-white p-3"><svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg><div><strong class="text-xs text-slate-900">Customer Support</strong><p class="mt-1 text-xs leading-4 text-slate-500">We’re here when you need help</p></div></div>
                        </section>
                    </aside>
                </div>

                <div v-else class="mt-6 rounded-[9px] border border-slate-200 bg-white px-5 py-16 text-center sm:py-20">
                    <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-prinx-50"><img src="/assets/accessory.svg" class="h-16 w-16" alt="Empty cart"></div>
                    <h2 class="mt-5 text-xl font-semibold text-slate-950">Your cart is empty</h2>
                    <p class="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-500">Browse our catalogue and add the products you need. Your selected items will appear here.</p>
                    <Link href="/shop" class="mt-5 inline-flex h-10 items-center justify-center rounded-[6px] bg-prinx-500 px-6 text-xs font-semibold text-white hover:bg-prinx-600">Start Shopping</Link>
                </div>

                <section v-if="cart.items.length" class="mt-6 grid gap-px overflow-hidden rounded-[8px] border border-prinx-100 bg-prinx-100 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="flex items-center justify-center gap-3 bg-prinx-50 px-4 py-4 lg:justify-start"><svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-600" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path stroke-linecap="round" d="M3 20c.6-4 2.5-6 6-6s5.4 2 6 6M15 15c2.9 0 4.8 1.6 5.5 4.5"/></svg><div><strong class="text-xs text-slate-900">Trusted across Tanzania</strong><p class="mt-1 text-xs text-slate-500">Quality products and service</p></div></div>
                    <div class="flex items-center justify-center gap-3 bg-prinx-50 px-4 py-4"><svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-600" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg><div><strong class="text-sm text-prinx-600">Quality</strong><p class="mt-1 text-xs text-slate-500">Carefully selected products</p></div></div>
                    <div class="flex items-center justify-center gap-3 bg-prinx-50 px-4 py-4"><svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-600" stroke-width="1.7"><path stroke-linecap="round" d="M4 12a8 8 0 0 1 14-5M20 12a8 8 0 0 1-14 5"/><path stroke-linecap="round" stroke-linejoin="round" d="m18 3 .2 4-4-.2M6 21l-.2-4 4 .2"/></svg><div><strong class="text-sm text-prinx-600">Easy Returns</strong><p class="mt-1 text-xs text-slate-500">See our returns policy</p></div></div>
                    <div class="flex items-center justify-center gap-3 bg-prinx-50 px-4 py-4"><svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-600" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg><div><strong class="text-xs text-slate-900">Local Support</strong><p class="mt-1 text-xs text-slate-500">Help when you need it</p></div></div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
