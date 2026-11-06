<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProductCard from '../../Components/ProductCard.vue';

defineProps({
    products: { type: Array, default: () => [] },
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

                <div v-if="products.length" class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-5 xl:gap-4">
                    <ProductCard v-for="product in products" :key="product.id" :product="product" />
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
