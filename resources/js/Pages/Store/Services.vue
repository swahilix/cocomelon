<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import SeoHead from '../../Components/SeoHead.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    services: Object,
    categories: Array,
    selectedCategory: { type: Object, default: null },
    filters: Object,
});

const search = ref(props.filters.search || '');
const title = computed(() => props.selectedCategory ? `${props.selectedCategory.name} Services` : 'Electronics Services');
const description = computed(() => props.selectedCategory?.description || 'Request electronics installation, maintenance, diagnostics and repair services from Prinx Electronics.');
const canonical = computed(() => props.selectedCategory ? `/services/category/${props.selectedCategory.slug}` : '/services');
const hasSearch = computed(() => !!search.value);
const apply = () => router.get('/services', { search: search.value, category: props.selectedCategory?.slug || props.filters.category || '' }, { preserveState: true, replace: true });
const clear = () => { search.value = ''; apply(); };
</script>

<template>
    <AppLayout>
        <SeoHead :title="title" :description="description" :canonical="canonical" :robots="hasSearch ? 'noindex,follow' : 'index,follow,max-image-preview:large'" />

        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="absolute inset-0 opacity-25" style="background-image:radial-gradient(circle at 85% 20%,rgba(249,115,22,.65),transparent 26%),linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:auto,40px 40px,40px 40px"></div>
            <div class="container-shell relative grid items-center gap-10 py-14 lg:grid-cols-[1fr_420px] lg:py-20">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.2em] text-prinx-400">Professional technical support</p>
                    <h1 class="mt-4 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">{{ selectedCategory?.name || 'Keep your technology working at its best.' }}</h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">{{ selectedCategory?.description || 'Request installation, setup, diagnostics, maintenance, upgrades or repair support from the Prinx technical team.' }}</p>
                    <div class="mt-7 flex flex-wrap gap-3"><a href="#service-list" class="btn-primary">Browse services <Icon name="arrow-down-2" :size="16" /></a><Link href="/pages/contact" class="inline-flex items-center rounded-[10px] border border-slate-700 px-5 py-3 text-sm font-extrabold hover:border-prinx-400">Contact our team</Link></div>
                </div>
                <div class="glass-card p-5">
                    <p class="text-[11px] font-black uppercase tracking-[.18em] text-prinx-300">How it works</p>
                    <div class="mt-4 space-y-3">
                        <div class="flex gap-3 rounded-[10px] bg-white/5 p-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-prinx-500 text-xs font-black">1</span><div><b class="text-sm">Choose a service</b><p class="mt-1 text-xs leading-5 text-slate-400">Open the service that matches your device or project.</p></div></div>
                        <div class="flex gap-3 rounded-[10px] bg-white/5 p-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-prinx-500 text-xs font-black">2</span><div><b class="text-sm">Describe your request</b><p class="mt-1 text-xs leading-5 text-slate-400">Send the device details, problem or installation scope.</p></div></div>
                        <div class="flex gap-3 rounded-[10px] bg-white/5 p-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-prinx-500 text-xs font-black">3</span><div><b class="text-sm">Receive confirmation</b><p class="mt-1 text-xs leading-5 text-slate-400">We confirm assessment, schedule and quotation details.</p></div></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 bg-white py-8">
            <div class="container-shell">
                <div class="flex items-end justify-between gap-5"><div><p class="section-kicker">Service categories</p><h2 class="section-title">Choose the support you need</h2></div><Link v-if="selectedCategory" href="/services" class="btn-light hidden sm:inline-flex">View all categories</Link></div>
                <div class="category-carousel mt-6">
                    <Link v-for="category in categories" :key="category.id" :href="`/services?category=${encodeURIComponent(category.slug)}`" :class="['group category-card category-carousel-card', selectedCategory?.id === category.id ? 'border-prinx-500 bg-prinx-50' : '']">
                        <img :src="category.image_url" :alt="`${category.name} services from Prinx Electronics`" :title="category.name" loading="lazy" decoding="async" class="aspect-[4/3] w-full bg-slate-100 object-cover">
                        <div class="p-3"><p class="text-[9px] font-black uppercase tracking-[.12em] text-prinx-600">{{ category.services_count }} services</p><h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ category.name }}</h3><p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ category.description }}</p></div>
                    </Link>
                </div>
            </div>
        </section>

        <section id="service-list" class="container-shell py-12">
            <div class="mb-7 flex flex-col gap-4 rounded-[10px] border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm font-bold text-slate-500"><b class="text-xl font-black text-slate-950">{{ services.total }}</b> services available</p><p v-if="selectedCategory" class="mt-1 text-xs font-bold text-prinx-600">{{ selectedCategory.name }}</p></div>
                <form class="flex w-full max-w-md" @submit.prevent="apply"><input v-model="search" class="input rounded-r-none border-r-0" placeholder="Search services"><button class="btn-primary rounded-l-none px-5">Search</button></form>
            </div>

            <div v-if="services.data.length" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="service in services.data" :key="service.id" :href="`/services/${service.slug}`" class="group category-card flex h-full flex-col">
                    <div class="relative aspect-[16/10] overflow-hidden bg-slate-100"><img :src="service.image_url" :alt="`${service.name} service by Prinx Electronics`" :title="service.name" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105"><span v-if="service.category" class="absolute bottom-3 left-3 rounded-[10px] bg-white/95 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700">{{ service.category.name }}</span></div>
                    <div class="flex flex-1 flex-col p-6"><p class="section-kicker">Technical service</p><h2 class="mt-2 text-xl font-black leading-7 text-slate-950 group-hover:text-prinx-600">{{ service.name }}</h2><p class="mt-3 line-clamp-3 text-sm leading-7 text-slate-500">{{ service.short_description }}</p><div class="mt-auto flex items-end justify-between gap-4 pt-6"><div><span class="block text-[10px] font-black uppercase tracking-wide text-slate-400">Estimated price</span><b class="mt-1 block text-sm text-slate-950">{{ service.price_label || (service.price_from ? `From TZS ${Number(service.price_from).toLocaleString('en-TZ')}` : 'Request quotation') }}</b></div><span class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-slate-950 text-white transition group-hover:bg-prinx-500"><Icon name="arrow-right-3" :size="18" /></span></div></div>
                </Link>
            </div>
            <div v-else class="card py-20 text-center"><h2 class="text-xl font-black">No services found</h2><p class="mt-2 text-sm text-slate-500">Try another category or search phrase.</p><button v-if="search" class="btn-primary mt-5" @click="clear">Clear search</button></div>
            <Pagination :links="services.links" />
        </section>
    </AppLayout>
</template>
