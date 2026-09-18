<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';

const props = defineProps({
    guide: Object,
    relatedProducts: { type: Array, default: () => [] },
    relatedGuides: { type: Array, default: () => [] },
});

const page = usePage();
const canonical = computed(() => `/guides/${props.guide.slug}`);
const schema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: props.guide.title,
    description: props.guide.meta_description || props.guide.excerpt,
    datePublished: props.guide.published_at,
    dateModified: props.guide.updated_at,
    image: props.guide.cover_image_url || undefined,
    author: { '@type': 'Organization', name: page.props.store.name, url: page.props.store.url },
    publisher: { '@type': 'Organization', name: page.props.store.name, url: page.props.store.url },
    mainEntityOfPage: `${page.props.store.url}/guides/${props.guide.slug}`,
}));
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency || 'TZS',
    maximumFractionDigits: 0,
}).format(Number(value || 0));
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="guide.meta_title || `${guide.title} | Prinx Tech`"
            :description="guide.meta_description || guide.excerpt"
            :canonical="canonical"
            :image="guide.cover_image_url"
            type="article"
            :schema="schema"
        />

        <main class="bg-[#fbfbfb]">
            <article class="container-shell max-w-4xl py-7 sm:py-10">
                <nav class="flex flex-wrap items-center gap-2 text-[10px] text-slate-500">
                    <Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span>
                    <Link href="/guides" class="hover:text-prinx-600">Buying Guides</Link><span>/</span>
                    <span class="text-slate-700">{{ guide.title }}</span>
                </nav>

                <header class="mt-5">
                    <Link v-if="guide.category" :href="`/shop/category/${guide.category.slug}`" class="text-[10px] font-semibold uppercase tracking-[.1em] text-prinx-600">{{ guide.category.name }}</Link>
                    <h1 class="mt-2 text-[30px] font-semibold leading-[1.15] tracking-[-.025em] text-slate-950 sm:text-[38px]">{{ guide.title }}</h1>
                    <p v-if="guide.excerpt" class="mt-4 text-[14px] leading-7 text-slate-600">{{ guide.excerpt }}</p>
                    <p v-if="guide.published_at" class="mt-3 text-[10px] text-slate-400">Published {{ new Date(guide.published_at).toLocaleDateString() }} · Prinx Tech</p>
                </header>

                <img v-if="guide.cover_image_url" :src="guide.cover_image_url" :alt="guide.title" class="mt-6 aspect-[16/9] w-full rounded-[8px] border border-slate-200 bg-white object-cover">

                <div class="mt-7 rounded-[8px] border border-slate-200 bg-white p-5 sm:p-7">
                    <div class="whitespace-pre-line text-[13px] leading-7 text-slate-700">{{ guide.body }}</div>
                </div>

                <section v-if="guide.category" class="mt-5 rounded-[8px] border border-prinx-100 bg-prinx-50 p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[.1em] text-prinx-700">Continue shopping</p>
                    <p class="mt-1 text-[12px] text-slate-700">Browse current {{ guide.category.name }} products, prices and availability.</p>
                    <Link :href="`/shop/category/${guide.category.slug}`" class="mt-3 inline-flex rounded-[6px] bg-prinx-500 px-4 py-2.5 text-[11px] font-semibold text-white">Shop {{ guide.category.name }}</Link>
                </section>

                <section v-if="relatedProducts.length" class="mt-8">
                    <h2 class="text-[18px] font-semibold text-slate-950">Products mentioned in this guide</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <Link v-for="product in relatedProducts" :key="product.id" :href="`/products/${product.slug}`" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white p-3 hover:border-prinx-200">
                            <img :src="product.image_url" :alt="product.name" class="h-28 w-full object-contain">
                            <p class="mt-2 line-clamp-2 text-[10px] font-semibold leading-4 text-slate-800">{{ product.name }}</p>
                            <strong class="mt-2 block text-[10px] text-prinx-600">{{ money(product.current_price) }}</strong>
                        </Link>
                    </div>
                </section>

                <section v-if="relatedGuides.length" class="mt-8">
                    <div class="flex items-end justify-between"><h2 class="text-[18px] font-semibold text-slate-950">More buying guides</h2><Link href="/guides" class="text-[10px] font-semibold text-prinx-600">All guides →</Link></div>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <Link v-for="item in relatedGuides" :key="item.id" :href="`/guides/${item.slug}`" class="rounded-[8px] border border-slate-200 bg-white p-4 hover:border-prinx-200">
                            <span v-if="item.category" class="text-[9px] font-semibold text-prinx-600">{{ item.category.name }}</span>
                            <strong class="mt-1 block text-[12px] leading-5 text-slate-900">{{ item.title }}</strong>
                            <p class="mt-2 line-clamp-2 text-[10px] leading-4 text-slate-500">{{ item.excerpt }}</p>
                        </Link>
                    </div>
                </section>
            </article>
        </main>
    </AppLayout>
</template>
