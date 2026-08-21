<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import HeroCarousel from '../../Components/HeroCarousel.vue';
import ProductReelsCarousel from '../../Components/ProductReelsCarousel.vue';
import Icon from '../../Components/Icon.vue';
import ProductCard from '../../Components/ProductCard.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    heroSlides: Array,
    productReels: Array,
    featuredProducts: Array,
    productCategories: Array,
    serviceCategories: Array,
    brands: Array,
});

const productTrack = ref(null);
const categoryTrack = ref(null);

const scrollTrack = (track, direction) => {
    if (!track?.value) return;
    track.value.scrollBy({ left: direction * Math.min(track.value.clientWidth * 0.85, 900), behavior: 'smooth' });
};

const serviceDefinitions = [
    {
        key: 'software',
        name: 'Software Support',
        description: 'Software design, installation, repair and updates.',
        icon: 'code-1',
        fallbackSlug: 'software-support',
        comingSoon: false,
    },
    {
        key: 'network',
        name: 'Network & Installation',
        description: 'Connectivity, Wi-Fi, network setup and electronics installation services.',
        icon: 'wifi-square',
        fallbackSlug: 'network-installation',
        comingSoon: false,
    },
    {
        key: 'computer',
        name: 'Computer Support & Repair',
        description: 'Computer diagnostics, repair, upgrades, maintenance and software support.',
        icon: 'monitor',
        fallbackSlug: 'computer-support-repair',
        comingSoon: false,
    },
    {
        key: 'phone',
        name: 'Phone & Tablet Repair',
        description: 'Phone and tablet diagnostics, screen, battery, charging and software repair.',
        icon: 'mobile',
        fallbackSlug: 'phone-tablet-repair',
        comingSoon: true,
    },
];

const serviceTiles = computed(() => serviceDefinitions.map((definition) => {
    const category = (props.serviceCategories || []).find((item) => {
        const haystack = `${item.name || ''} ${item.slug || ''}`.toLowerCase();
        return haystack.includes(definition.key);
    });

    return {
        ...definition,
        id: category?.id || definition.key,
        slug: category?.slug || definition.fallbackSlug,
        count: category?.services_count ?? category?.service_count ?? null,
    };
}));
</script>

<template>
    <AppLayout>
        <SeoHead title="Prinx Electronics" description="Shop electronics and book professional technical services from Prinx Electronics in Tanzania." />

        <HeroCarousel :slides="heroSlides || []" />

        <section class="border-b border-slate-100 bg-white">
            <div class="container-shell grid grid-cols-2 gap-px py-4 sm:grid-cols-4">
                <div class="flex items-center gap-3 px-2 py-2 sm:px-4">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="shield-tick" size="19" /></span>
                    <div><p class="text-sm font-extrabold">Reliable products</p><p class="text-xs text-slate-500">Clear product information</p></div>
                </div>
                <div class="flex items-center gap-3 px-2 py-2 sm:px-4">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="truck-fast" size="19" /></span>
                    <div><p class="text-sm font-extrabold">Delivery support</p><p class="text-xs text-slate-500">Simple order fulfilment</p></div>
                </div>
                <div class="flex items-center gap-3 px-2 py-2 sm:px-4">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="setting-2" size="19" /></span>
                    <div><p class="text-sm font-extrabold">Technical services</p><p class="text-xs text-slate-500">Professional assistance</p></div>
                </div>
                <div class="flex items-center gap-3 px-2 py-2 sm:px-4">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="message-question" size="19" /></span>
                    <div><p class="text-sm font-extrabold">Prinx support</p><p class="text-xs text-slate-500">Help before and after purchase</p></div>
                </div>
            </div>
        </section>

        <section class="container-shell py-9 sm:py-12">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="section-kicker">Browse categories</p>
                    <h2 class="section-title">Find what you need</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Browse Prinx electronics by category and get to the right products faster.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="hidden gap-2 sm:flex">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white hover:border-prinx-300 hover:text-prinx-600" aria-label="Previous categories" @click="scrollTrack(categoryTrack, -1)"><Icon name="arrow-left-2" size="18" /></button>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white hover:border-prinx-300 hover:text-prinx-600" aria-label="Next categories" @click="scrollTrack(categoryTrack, 1)"><Icon name="arrow-right-3" size="18" /></button>
                    </div>
                    <Link href="/shop" class="hidden items-center gap-1 text-sm font-extrabold text-prinx-600 md:inline-flex">View all <Icon name="arrow-right" size="15" /></Link>
                </div>
            </div>

            <div ref="categoryTrack" class="mt-5 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <Link v-for="category in productCategories" :key="category.id" :href="`/shop/category/${category.slug}`" class="group w-[44vw] max-w-[190px] shrink-0 snap-start overflow-hidden rounded-[10px] border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:border-prinx-200 hover:shadow-sm sm:w-[180px]">
                    <div class="aspect-[4/3] overflow-hidden bg-slate-50 p-3">
                        <img :src="category.image_url" :alt="category.name" class="h-full w-full object-contain transition duration-300 group-hover:scale-105" />
                    </div>
                    <div class="border-t border-slate-100 p-3">
                        <p class="font-extrabold leading-tight">{{ category.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ category.products_count }} {{ category.products_count === 1 ? 'product' : 'products' }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <section class="bg-slate-50 py-9 sm:py-12">
            <div class="container-shell">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="section-kicker">Popular products</p>
                        <h2 class="section-title">Featured electronics</h2>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Selected products currently highlighted by Prinx.</p>
                    </div>
                    <div class="hidden gap-2 sm:flex">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white hover:border-prinx-300 hover:text-prinx-600" aria-label="Previous products" @click="scrollTrack(productTrack, -1)"><Icon name="arrow-left-2" size="18" /></button>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white hover:border-prinx-300 hover:text-prinx-600" aria-label="Next products" @click="scrollTrack(productTrack, 1)"><Icon name="arrow-right-3" size="18" /></button>
                    </div>
                </div>

                <div ref="productTrack" class="mt-5 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <div v-for="product in featuredProducts" :key="product.id" class="w-[72vw] max-w-[285px] shrink-0 snap-start sm:w-[260px] lg:w-[calc(25%-10px)] lg:max-w-none">
                        <ProductCard :product="product" compact-mobile />
                    </div>
                </div>

                <div class="mt-5 text-center sm:hidden"><Link href="/shop" class="inline-flex items-center gap-1 text-sm font-extrabold text-prinx-600">Browse all products <Icon name="arrow-right" size="15" /></Link></div>
            </div>
        </section>

        <ProductReelsCarousel :reels="productReels || []" />

        <section v-if="brands?.length" class="border-y border-slate-100 bg-white py-8">
            <div class="container-shell">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="shrink-0">
                        <p class="section-kicker">Brands</p>
                        <h2 class="text-xl font-extrabold tracking-tight">Brands available at Prinx</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5 lg:justify-end">
                        <Link v-for="brand in brands" :key="brand.id" :href="`/shop?brand=${brand.slug}`" class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-prinx-300 hover:bg-prinx-50 hover:text-prinx-700">{{ brand.name }}</Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="container-shell py-10 sm:py-14">
            <div class="overflow-hidden rounded-[10px] bg-slate-950 text-white md:grid md:grid-cols-[38%_62%]">
                <div class="bg-gradient-to-br from-[#3a1417] via-slate-950 to-slate-950 p-6 sm:p-8 lg:p-11">
                    <p class="section-kicker text-prinx-400">Prinx technical services</p>
                    <h2 class="mt-4 max-w-md text-3xl font-extrabold leading-tight tracking-tight">Support that keeps your technology working.</h2>
                    <p class="mt-4 max-w-md leading-7 text-slate-300">Software support, network installation, computer repair and practical technical assistance from Prinx.</p>
                    <Link href="/services" class="btn-primary mt-7 inline-flex items-center gap-2">Explore all services <Icon name="arrow-right" size="18" /></Link>
                </div>

                <div class="grid sm:grid-cols-2">
                    <template v-for="service in serviceTiles" :key="service.id">
                        <div v-if="service.comingSoon" class="border-b border-l border-slate-800 bg-slate-900 p-6 sm:p-7">
                            <div class="flex items-start justify-between gap-3">
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-[9px] bg-white text-prinx-600"><Icon :name="service.icon" size="28" /></span>
                                <span class="rounded-full border border-prinx-500/40 bg-prinx-500/10 px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wide text-prinx-400">Coming soon</span>
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold">{{ service.name }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ service.description }}</p>
                        </div>

                        <Link v-else :href="`/services/category/${service.slug}`" class="border-b border-l border-slate-800 bg-slate-900 p-6 transition hover:bg-slate-800 sm:p-7">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-[9px] bg-white text-prinx-600"><Icon :name="service.icon" size="28" /></span>
                            <h3 class="mt-5 text-lg font-extrabold">{{ service.name }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ service.description }}</p>
                            <p class="mt-4 flex items-center gap-1 text-sm font-extrabold text-prinx-500">
                                <span v-if="service.count !== null">{{ service.count }} {{ service.count === 1 ? 'service' : 'services' }}</span>
                                <span v-else>Explore services</span>
                                <Icon name="arrow-right" size="16" />
                            </p>
                        </Link>
                    </template>
                </div>
            </div>
        </section>

        <section class="bg-slate-950 py-10 text-white">
            <div class="container-shell flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[.16em] text-prinx-400">Need help choosing?</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight">Talk to Prinx before you buy.</h2>
                    <p class="mt-2 text-sm text-slate-400">Get product guidance or technical service assistance.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/contact" class="btn-primary inline-flex items-center gap-2"><Icon name="message-question" size="18" /> Contact us</Link>
                    <Link href="/shop" class="inline-flex items-center gap-2 rounded-[8px] border border-white/15 px-4 py-2.5 text-sm font-bold hover:bg-white/10"><Icon name="bag-2" size="18" /> Shop electronics</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
