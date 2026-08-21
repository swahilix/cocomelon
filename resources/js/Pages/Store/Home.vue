<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
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

        <section class="bg-slate-950 text-white">
            <div class="container-shell grid min-h-[390px] items-center gap-8 py-12 md:grid-cols-2">
                <div>
                    <p class="section-kicker text-prinx-400">Electronics · Services · Support</p>
                    <h1 class="mt-3 text-4xl font-black tracking-tight sm:text-5xl">Technology that works for you.</h1>
                    <p class="mt-4 max-w-xl text-slate-300">Shop reliable electronics, accessories and professional technical services with clear product information and dependable support.</p>
                    <div class="mt-7 flex gap-3">
                        <Link href="/shop" class="btn-primary">Shop products</Link>
                        <Link href="/services" class="btn-light">Explore services</Link>
                    </div>
                </div>
                <div v-if="heroSlides?.length" class="card overflow-hidden bg-white text-slate-900">
                    <img :src="heroSlides[0].item.image_url" :alt="heroSlides[0].item.name" class="aspect-[4/3] w-full object-cover">
                    <div class="p-5">
                        <p class="text-xs font-black uppercase text-prinx-600">{{ heroSlides[0].eyebrow || 'Featured' }}</p>
                        <h2 class="mt-1 text-2xl font-black">{{ heroSlides[0].headline || heroSlides[0].item.name }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ heroSlides[0].description || heroSlides[0].item.short_description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container-shell py-10">
            <div class="flex items-end justify-between">
                <div>
                    <p class="section-kicker">Browse categories</p>
                    <h2 class="section-title">Find what you need</h2>
                </div>
                <Link href="/shop" class="text-sm font-black text-prinx-600">View all →</Link>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Link v-for="c in productCategories" :key="c.id" :href="`/shop/category/${c.slug}`" class="card overflow-hidden">
                    <img :src="c.image_url" :alt="c.name" class="aspect-square w-full object-cover">
                    <div class="p-3">
                        <p class="font-black">{{ c.name }}</p>
                        <p class="text-xs text-slate-500">{{ c.products_count }} products</p>
                    </div>
                </Link>
            </div>
        </section>

        <section class="container-shell py-8">
            <p class="section-kicker">Popular products</p>
            <h2 class="section-title">Featured electronics</h2>
            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="p in featuredProducts" :key="p.id" :product="p" compact-mobile />
            </div>
        </section>

        <section v-if="productReels?.length" class="container-shell py-8">
            <p class="section-kicker">Product reels</p>
            <h2 class="section-title">See products in action</h2>
            <div class="mt-5 flex gap-3 overflow-x-auto pb-2">
                <Link v-for="r in productReels" :key="r.id" :href="r.item_url" class="card w-52 shrink-0 overflow-hidden">
                    <img :src="r.cover_url" :alt="r.title" class="aspect-[9/14] w-full object-cover">
                    <div class="p-3">
                        <p class="font-black">{{ r.title }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ r.caption }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <section class="container-shell py-10">
            <div class="overflow-hidden rounded-[10px] bg-slate-950 text-white md:grid md:grid-cols-[40%_60%]">
                <div class="bg-gradient-to-br from-[#3a1417] via-slate-950 to-slate-950 p-6 sm:p-8 lg:p-12">
                    <p class="section-kicker text-prinx-400">Prinx technical services</p>
                    <h2 class="mt-4 max-w-md text-3xl font-black leading-tight tracking-tight">Support that keeps your technology working.</h2>
                    <p class="mt-4 max-w-md leading-7 text-slate-300">From software support and network installation to computer repair, our team supports your technology after the sale.</p>
                    <Link href="/services" class="btn-primary mt-7 inline-flex items-center gap-2">
                        Explore all services
                        <Icon name="arrow-right" size="18" />
                    </Link>
                </div>

                <div class="grid sm:grid-cols-2">
                    <template v-for="service in serviceTiles" :key="service.id">
                        <div v-if="service.comingSoon" class="border-b border-l border-slate-800 bg-slate-900 p-6 sm:p-7">
                            <div class="flex items-start justify-between gap-3">
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-[9px] bg-white text-prinx-600">
                                    <Icon :name="service.icon" size="28" />
                                </span>
                                <span class="rounded-full border border-prinx-500/40 bg-prinx-500/10 px-2.5 py-1 text-[11px] font-black uppercase tracking-wide text-prinx-400">Coming soon</span>
                            </div>
                            <h3 class="mt-5 text-lg font-black">{{ service.name }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ service.description }}</p>
                        </div>

                        <Link v-else :href="`/services/category/${service.slug}`" class="border-b border-l border-slate-800 bg-slate-900 p-6 transition hover:bg-slate-800 sm:p-7">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-[9px] bg-white text-prinx-600">
                                <Icon :name="service.icon" size="28" />
                            </span>
                            <h3 class="mt-5 text-lg font-black">{{ service.name }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ service.description }}</p>
                            <p class="mt-4 flex items-center gap-1 text-sm font-black text-prinx-500">
                                <span v-if="service.count !== null">{{ service.count }} {{ service.count === 1 ? 'service' : 'services' }}</span>
                                <span v-else>Explore services</span>
                                <Icon name="arrow-right" size="16" />
                            </p>
                        </Link>
                    </template>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
