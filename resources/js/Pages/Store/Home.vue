<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProductCard from '../../Components/ProductCard.vue';
import ProductReels from '../../Components/ProductReels.vue';
import HeroShowcase from '../../Components/HeroShowcase.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    heroSlides: Array,
    productReels: Array,
    featuredProducts: Array,
    productCategories: Array,
    serviceCategories: Array,
    brands: Array,
});

const page = usePage();
const homeSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@graph': [
        {
            '@type': 'OnlineStore',
            '@id': `${page.props.store.url}/#store`,
            name: page.props.store.name,
            legalName: page.props.store.legal_name || page.props.store.name,
            url: page.props.store.url,
            logo: {
                '@type': 'ImageObject',
                contentUrl: page.props.store.logo,
                name: `${page.props.store.name} logo`,
                creditText: page.props.store.image_credit,
            },
            image: page.props.store.logo,
            sameAs: page.props.store.same_as?.length ? page.props.store.same_as : undefined,
            description: page.props.store.description,
            telephone: page.props.store.phone,
            email: page.props.store.email,
            contactPoint: {
                '@type': 'ContactPoint',
                contactType: 'customer service',
                telephone: page.props.store.phone,
                email: page.props.store.email,
                areaServed: page.props.store.country,
            },
            address: {
                '@type': 'PostalAddress',
                addressLocality: page.props.store.address,
                addressCountry: page.props.store.country,
            },
        },
        {
            '@type': 'WebSite',
            '@id': `${page.props.store.url}/#website`,
            url: page.props.store.url,
            name: page.props.store.name,
            publisher: { '@id': `${page.props.store.url}/#store` },
            potentialAction: {
                '@type': 'SearchAction',
                target: `${page.props.store.url}/shop?search={search_term_string}`,
                'query-input': 'required name=search_term_string',
            },
        },
    ],
}));
</script>

<template>
    <AppLayout>
        <SeoHead title="Electronics, Accessories & Technical Services" description="Shop electronics, smartphones, computers and accessories, or request professional electronics services from Prinx Electronics." canonical="/" :schema="homeSchema" />

        <HeroShowcase :slides="heroSlides" />

        <section class="container-shell py-16">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="section-kicker">Shop by category</p><h2 class="section-title">Find the right technology faster</h2><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Browse organised product collections and go directly to the devices or accessories you need.</p></div>
                <Link href="/shop" class="btn-light self-start sm:self-auto">View full catalogue →</Link>
            </div>
            <div class="category-carousel mt-8">
                <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="group category-card category-carousel-card">
                    <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                        <img :src="category.image_url" :alt="`Browse ${category.name} products at Prinx Electronics`" :title="category.name" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/55 via-transparent to-transparent"></div>
                        <span class="absolute bottom-2 left-2 rounded-[8px] bg-white/95 px-2 py-1 text-[9px] font-black text-slate-800">{{ category.products_count }} products</span>
                    </div>
                    <div class="p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0"><h3 class="truncate text-sm font-black text-slate-950 transition group-hover:text-prinx-600">{{ category.name }}</h3><p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ category.description }}</p></div>
                            <span class="shrink-0 text-sm font-black text-prinx-600">→</span>
                        </div>
                    </div>
                </Link>
            </div>
        </section>

        <ProductReels :reels="productReels" />

        <section v-if="featuredProducts.length" class="border-y border-slate-200 bg-white py-16">
            <div class="container-shell">
                <div class="flex items-end justify-between gap-5"><div><p class="section-kicker">Featured products</p><h2 class="section-title">Popular electronics and accessories</h2></div><Link href="/shop" class="hidden text-sm font-black text-prinx-600 sm:block">Shop all →</Link></div>
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4"><ProductCard v-for="product in featuredProducts" :key="product.id" :product="product" compact-mobile :show-description="false" /></div>
            </div>
        </section>

        <section class="container-shell py-16">
            <div class="overflow-hidden rounded-[10px] bg-slate-950 text-white shadow-2xl">
                <div class="grid lg:grid-cols-[.8fr_1.2fr]">
                    <div class="relative overflow-hidden p-7 sm:p-10 lg:p-12">
                        <div class="absolute -left-20 -top-20 h-64 w-64 rounded-[10px] bg-prinx-500/25 blur-3xl"></div>
                        <div class="relative">
                            <p class="text-xs font-black uppercase tracking-[.2em] text-prinx-400">Prinx technical services</p>
                            <h2 class="mt-4 text-3xl font-black leading-tight">Support that keeps your technology working.</h2>
                            <p class="mt-4 max-w-md leading-7 text-slate-300">From diagnostics and repair to upgrades and network installation, our team supports your devices after the sale.</p>
                            <Link href="/services" class="btn-primary mt-7">Explore all services →</Link>
                        </div>
                    </div>
                    <div class="grid gap-px bg-slate-800 sm:grid-cols-2">
                        <Link v-for="category in serviceCategories" :key="category.id" :href="`/services?category=${encodeURIComponent(category.slug)}`" class="group bg-slate-900 p-6 transition hover:bg-slate-800">
                            <img :src="category.image_url" :alt="`${category.name} technical services`" :title="category.name" loading="lazy" decoding="async" class="h-12 w-12 rounded-[10px] bg-white object-cover">
                            <h3 class="mt-5 text-lg font-black group-hover:text-prinx-400">{{ category.name }}</h3>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-400">{{ category.description }}</p>
                            <span class="mt-4 inline-block text-sm font-black text-prinx-400">{{ category.services_count }} services →</span>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-16">
            <div class="container-shell">
                <div class="text-center"><p class="section-kicker">Why choose Prinx</p><h2 class="section-title">A clearer and more dependable buying experience</h2></div>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <div class="card p-6"><div class="flex h-12 w-12 items-center justify-center rounded-[10px] bg-prinx-50 font-black text-prinx-600">01</div><h3 class="mt-5 text-lg font-black">Complete product information</h3><p class="mt-2 text-sm leading-7 text-slate-500">Clear prices, category, brand, stock, condition, warranty and technical details support confident decisions.</p></div>
                    <div class="card p-6"><div class="flex h-12 w-12 items-center justify-center rounded-[10px] bg-prinx-50 font-black text-prinx-600">02</div><h3 class="mt-5 text-lg font-black">Responsive order support</h3><p class="mt-2 text-sm leading-7 text-slate-500">Our team confirms product availability, payment details and delivery arrangements after each order.</p></div>
                    <div class="card p-6"><div class="flex h-12 w-12 items-center justify-center rounded-[10px] bg-prinx-50 font-black text-prinx-600">03</div><h3 class="mt-5 text-lg font-black">Professional technical care</h3><p class="mt-2 text-sm leading-7 text-slate-500">Installation, diagnostics, repair, upgrades and business electronics support from one store.</p></div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
