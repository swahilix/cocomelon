<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
});

const page = usePage();
const currency = computed(() => page.props.store?.currency || 'TZS');
const money = (value) => `${currency.value} ${Number(value || 0).toLocaleString('en-TZ', { maximumFractionDigits: 0 })}`;
const discount = (product) => {
    const regular = Number(product.price || 0);
    const current = Number(product.current_price || 0);
    return regular > current && regular > 0 ? Math.max(1, Math.round(((regular - current) / regular) * 100)) : 0;
};
const addToCart = (product) => {
    if (!product.in_stock) return;
    if (Number(product.active_variants_count || 0) > 0) {
        router.visit(`/products/${product.slug}`);
        return;
    }
    router.post(`/cart/${product.slug}`, { quantity: 1 }, { preserveScroll: true });
};
const remove = (product) => router.delete(`/wishlist/${product.slug}`, {
    preserveScroll: true,
    preserveState: true,
});
</script>

<template>
    <AppLayout>
        <Head title="My Wishlist">
            <meta head-key="robots" name="robots" content="noindex,nofollow">
        </Head>

        <section class="bg-[#fbfbfb]">
            <div class="container-shell pb-8 pt-4 sm:pt-5">
                <nav class="flex items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                    <span>/</span>
                    <span class="text-prinx-600">Wishlist</span>
                </nav>

                <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 class="text-[28px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[34px]">My Wishlist</h1>
                        <p class="mt-1 text-xs text-slate-500">Products you have saved for later.</p>
                    </div>
                    <span v-if="products.length" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">{{ products.length }} {{ products.length === 1 ? 'item' : 'items' }}</span>
                </div>

                <div v-if="products.length" class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 xl:gap-4">
                    <article v-for="product in products" :key="product.id" class="group relative flex min-w-0 flex-col overflow-hidden rounded-[8px] border border-slate-200 bg-white transition hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.05)]">
                        <button type="button" class="absolute right-2 top-2 z-20 flex h-8 w-8 items-center justify-center rounded-full border border-prinx-100 bg-white/95 text-prinx-600 shadow-sm transition hover:bg-prinx-50" :aria-label="`Remove ${product.name} from wishlist`" @click="remove(product)">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-prinx-500 stroke-prinx-500" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                        </button>

                        <Link :href="`/products/${product.slug}`" class="relative flex aspect-square items-center justify-center overflow-hidden bg-slate-50 p-3 sm:p-4">
                            <img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.03]" loading="lazy" decoding="async">
                            <span v-if="discount(product)" class="absolute bottom-2 left-2 rounded-[5px] bg-prinx-50 px-2 py-1 text-xs font-semibold text-prinx-700">-{{ discount(product) }}%</span>
                        </Link>

                        <div class="flex flex-1 flex-col p-3 sm:p-4">
                            <div class="flex min-w-0 items-center justify-between gap-2">
                                <span class="truncate text-xs font-medium text-prinx-600">{{ product.category?.name || 'Electronics' }}</span>
                                <span class="truncate text-xs text-slate-400">{{ product.brand?.name || 'Prinx' }}</span>
                            </div>

                            <Link :href="`/products/${product.slug}`" class="mt-2 line-clamp-2 min-h-[40px] text-xs font-semibold leading-5 text-slate-900 transition hover:text-prinx-600 sm:text-sm">{{ product.name }}</Link>

                            <p class="mt-2 text-xs font-semibold" :class="product.in_stock ? 'text-emerald-600' : 'text-red-500'">{{ product.in_stock ? 'In Stock' : 'Out of Stock' }}</p>

                            <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <strong class="text-xs text-slate-950 sm:text-sm">{{ money(product.current_price) }}</strong>
                                <span v-if="Number(product.current_price) < Number(product.price)" class="text-xs text-slate-400 line-through">{{ money(product.price) }}</span>
                            </div>

                            <div class="mt-auto grid grid-cols-[1fr_38px] gap-2 pt-3">
                                <button type="button" class="flex h-8 max-h-8 min-w-0 items-center justify-center rounded-[6px] bg-prinx-500 px-2 text-[10px] font-semibold leading-none text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!product.in_stock" @click="addToCart(product)">{{ Number(product.active_variants_count || 0) > 0 ? 'Select options' : 'Add to Cart' }}</button>
                                <button type="button" class="flex h-9 w-[38px] items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:text-red-600" :aria-label="`Remove ${product.name}`" @click="remove(product)">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5M14 11v5"/></svg>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-6 rounded-[9px] border border-slate-200 bg-white px-5 py-14 text-center sm:py-20">
                    <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-prinx-50 text-prinx-500">
                        <svg viewBox="0 0 24 24" class="h-9 w-9 fill-none stroke-current" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                    </span>
                    <h2 class="mt-4 text-lg font-semibold text-slate-950">Your wishlist is empty</h2>
                    <p class="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-500">Save products you like and they will stay here for your next visit.</p>
                    <Link href="/shop" class="mt-5 inline-flex h-10 items-center justify-center rounded-[6px] bg-prinx-500 px-6 text-xs font-semibold text-white transition hover:bg-prinx-600">Browse Products</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
