<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProductCard from '../../Components/ProductCard.vue';
import ProductReels from '../../Components/ProductReels.vue';
import HeroShowcase from '../../Components/HeroShowcase.vue';
import SeoHead from '../../Components/SeoHead.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({ heroSlides: Array, productReels: Array, featuredProducts: Array, productCategories: Array, serviceCategories: Array, brands: Array });
const page = usePage();

const homeServices = [
    { name: 'Software Support', description: 'Software design, installation, repair and update for computers, business systems and everyday devices.', icon: 'code-1', href: '/services?category=software-support', comingSoon: false },
    { name: 'Network & Installation', description: 'Network setup, installation and technical configuration for homes, offices and business environments.', icon: 'wifi', href: '/services?category=network-installation', comingSoon: false },
    { name: 'Computer Support & Repair', description: 'Diagnostics, maintenance, upgrades and repair support for desktop and laptop computers.', icon: 'monitor', href: '/services?category=computer-support-repair', comingSoon: false },
    { name: 'Phone & Tablet Repair', description: 'Professional phone and tablet diagnostics and repair service is being prepared for launch.', icon: 'mobile', href: null, comingSoon: true },
];

const homeSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@graph': [
        { '@type': 'OnlineStore', '@id': `${page.props.store.url}/#store`, name: page.props.store.name, legalName: page.props.store.legal_name || page.props.store.name, url: page.props.store.url, logo: { '@type': 'ImageObject', contentUrl: page.props.store.logo, name: `${page.props.store.name} logo`, creditText: page.props.store.image_credit }, image: page.props.store.logo, sameAs: page.props.store.same_as?.length ? page.props.store.same_as : undefined, description: page.props.store.description, telephone: page.props.store.phone, email: page.props.store.email, contactPoint: { '@type': 'ContactPoint', contactType: 'customer service', telephone: page.props.store.phone, email: page.props.store.email, areaServed: page.props.store.country }, address: { '@type': 'PostalAddress', addressLocality: page.props.store.address, addressCountry: page.props.store.country } },
        { '@type': 'WebSite', '@id': `${page.props.store.url}/#website`, url: page.props.store.url, name: page.props.store.name, publisher: { '@id': `${page.props.store.url}/#store` }, potentialAction: { '@type': 'SearchAction', target: `${page.props.store.url}/shop?search={search_term_string}`, 'query-input': 'required name=search_term_string' } },
    ],
}));
</script>

<template>
    <AppLayout>
        <SeoHead title="Electronics, Accessories & Technical Services" description="Shop electronics, smartphones, computers and accessories, or request professional electronics services from Prinx Electronics." canonical="/" :schema="homeSchema" />

        <HeroShowcase :slides="heroSlides" />

        <section class="bg-[#fbfbfb] pt-5 sm:pt-6">
            <div class="container-shell">
                <div class="overflow-x-auto rounded-[15px] border border-slate-200 bg-white shadow-[0_8px_30px_rgba(15,23,42,.045)]">
                    <div class="grid min-w-[820px] grid-cols-5 px-5 py-5 lg:min-w-0">
                        <div class="flex items-center gap-3 border-r border-slate-200 px-4 first:pl-2">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-[#f4b400]" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 4 6 6-7 7-3 1 1-3 7-7M4 20l3-7 4-4 4 4-4 4-7 3Z"/><path stroke-linecap="round" d="M6 18 3 21m11-17 1-2m5 7 2-1"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Fast Delivery</strong><span class="mt-1 block text-[10px] text-slate-500">20 – 40 mins in main areas</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-r border-slate-200 px-5">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-[#f4b400]" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 4.4 2.4 7.2 7 9 4.6-1.8 7-4.6 7-9V6l-7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">100% Original</strong><span class="mt-1 block text-[10px] text-slate-500">Genuine products only</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-r border-slate-200 px-5">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-[#f4b400]" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2 15 5l4 .5.5 4 2.5 2.5-2.5 2.5-.5 4-4 .5-3 3-3-3-4-.5-.5-4L1 12l2.5-2.5.5-4L8 5l4-3Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Best Prices</strong><span class="mt-1 block text-[10px] text-slate-500">Competitive local pricing</span></div>
                        </div>
                        <div class="flex items-center gap-3 border-r border-slate-200 px-5">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-[#f4b400]" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5"/><path stroke-linecap="round" d="M6.4 8.2A7 7 0 0 1 18.6 6L20 8m-16 8 1.4 2A7 7 0 0 0 17.6 15.8"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Easy Returns</strong><span class="mt-1 block text-[10px] text-slate-500">Clear return support</span></div>
                        </div>
                        <div class="flex items-center gap-3 px-5 pr-2">
                            <svg viewBox="0 0 24 24" class="h-8 w-8 shrink-0 fill-none stroke-[#f4b400]" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                            <div><strong class="block text-[12px] font-semibold text-slate-800">Responsive Support</strong><span class="mt-1 block text-[10px] text-slate-500">We are here to help</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-[#fbfbfb] pb-12 pt-8 sm:pt-9">
            <div class="container-shell">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-[17px] font-semibold text-slate-900 sm:text-[18px]">Shop By Category</h2>
                    <Link href="/shop" class="inline-flex items-center gap-2 text-[11px] font-medium text-[#e8aa00] transition hover:text-amber-700">View all categories <span class="text-base">→</span></Link>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <Link v-for="category in productCategories.slice(0, 7)" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="group flex min-h-[190px] flex-col rounded-[14px] border border-slate-200 bg-white px-3 pb-4 pt-3 text-center shadow-[0_5px_20px_rgba(15,23,42,.035)] transition hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-[0_10px_28px_rgba(15,23,42,.07)]">
                        <div class="flex h-[112px] items-center justify-center overflow-hidden rounded-[10px] bg-[#fafafa]">
                            <img :src="category.image_url || '/assets/accessory.svg'" :alt="category.name" loading="lazy" decoding="async" class="h-[90px] w-[90px] object-contain transition duration-300 group-hover:scale-105">
                        </div>
                        <h3 class="mt-3 line-clamp-1 text-[12px] font-semibold text-slate-800">{{ category.name }}</h3>
                        <span class="mt-2 text-[10px] font-medium text-[#e8aa00]">View all</span>
                    </Link>

                    <Link href="/shop" class="group flex min-h-[190px] flex-col rounded-[14px] border border-slate-200 bg-white px-3 pb-4 pt-3 text-center shadow-[0_5px_20px_rgba(15,23,42,.035)] transition hover:-translate-y-0.5 hover:border-amber-200">
                        <div class="flex h-[112px] items-center justify-center rounded-[10px] bg-[#fafafa]">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700">
                                <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.6" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                            </span>
                        </div>
                        <h3 class="mt-3 text-[12px] font-semibold text-slate-800">More</h3>
                        <span class="mt-2 text-[10px] font-medium text-[#e8aa00]">View all</span>
                    </Link>
                </div>
            </div>
        </section>

        <ProductReels :reels="productReels" />

        <section v-if="featuredProducts.length" class="border-y border-slate-200 bg-white py-12">
            <div class="container-shell">
                <div class="flex items-end justify-between gap-5">
                    <div><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-[#e8aa00]">Featured products</p><h2 class="mt-2 text-2xl font-semibold tracking-[-.02em] text-slate-900">Popular electronics and accessories</h2></div>
                    <Link href="/shop" class="hidden items-center gap-2 text-[12px] font-semibold text-[#e8aa00] sm:flex">Shop all <span>→</span></Link>
                </div>
                <div class="mt-7 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4"><ProductCard v-for="product in featuredProducts" :key="product.id" :product="product" compact-mobile :show-description="false" /></div>
            </div>
        </section>

        <section class="container-shell py-12">
            <div class="overflow-hidden rounded-[14px] bg-slate-950 text-white shadow-xl">
                <div class="grid lg:grid-cols-[.8fr_1.2fr]">
                    <div class="relative overflow-hidden p-7 sm:p-10 lg:p-12">
                        <div class="absolute -left-20 -top-20 h-64 w-64 rounded-full bg-amber-400/15 blur-3xl"></div>
                        <div class="relative">
                            <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-amber-400">Prinx technical services</p>
                            <h2 class="mt-4 text-3xl font-semibold leading-tight">Support that keeps your technology working.</h2>
                            <p class="mt-4 max-w-md text-sm leading-7 text-slate-300">Software, networking, computers and device support from one technical team for home and business.</p>
                            <Link href="/services" class="mt-7 inline-flex h-11 items-center gap-2 rounded-[8px] bg-[#f7b900] px-5 text-sm font-semibold text-slate-950">Explore all services <span>→</span></Link>
                        </div>
                    </div>

                    <div class="grid gap-px bg-slate-800 sm:grid-cols-2">
                        <template v-for="service in homeServices" :key="service.name">
                            <Link v-if="!service.comingSoon" :href="service.href" class="group bg-slate-900 p-6 transition hover:bg-slate-800">
                                <div class="flex h-11 w-11 items-center justify-center rounded-[9px] border border-white/10 bg-white/[.06] text-amber-400"><Icon :name="service.icon" :size="23" /></div>
                                <h3 class="mt-5 text-base font-semibold group-hover:text-amber-300">{{ service.name }}</h3>
                                <p class="mt-2 text-xs leading-6 text-slate-400">{{ service.description }}</p>
                                <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-amber-400">View service →</span>
                            </Link>
                            <div v-else class="relative bg-slate-900 p-6">
                                <span class="absolute right-4 top-4 rounded-[7px] border border-amber-400/30 bg-amber-400/10 px-2 py-1 text-[8px] font-semibold uppercase tracking-[.12em] text-amber-300">Coming Soon</span>
                                <div class="flex h-11 w-11 items-center justify-center rounded-[9px] border border-white/10 bg-white/[.06] text-slate-400"><Icon :name="service.icon" :size="23" /></div>
                                <h3 class="mt-5 text-base font-semibold text-white">{{ service.name }}</h3>
                                <p class="mt-2 text-xs leading-6 text-slate-400">{{ service.description }}</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
