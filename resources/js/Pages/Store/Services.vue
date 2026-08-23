<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    services: Object,
    categories: Array,
    selectedCategory: { type: Object, default: null },
    filters: Object,
});

const page = usePage();
const title = computed(() => props.selectedCategory ? `${props.selectedCategory.name} Services` : 'Services');
const description = computed(() => props.selectedCategory?.description || 'Professional tech support, repairs, setup and delivery solutions from Prinx Electronics.');
const canonical = computed(() => props.selectedCategory ? `/services/category/${props.selectedCategory.slug}` : '/services');
const visibleServices = computed(() => (props.services?.data || []).slice(0, 8));
const pricingServices = computed(() => (props.services?.data || []).filter((service) => service.price_from || service.price_label).slice(0, 3));
const heroImages = computed(() => {
    const images = visibleServices.value.map((service) => service.image_url).filter(Boolean);
    return [images[0] || '/assets/phone.svg', images[1] || '/assets/laptop.svg', images[2] || '/assets/accessory.svg'];
});
const storeEmail = computed(() => page.props.store?.email || '');

const servicePrice = (service) => service.price_label || (service.price_from ? `From TZS ${Number(service.price_from).toLocaleString('en-TZ')}` : 'Request quotation');

const processSteps = [
    { number: '1', title: 'Choose a service', text: 'Pick the service you need from our list.', icon: 'calendar' },
    { number: '2', title: 'Book online or call', text: 'Schedule online or contact our support team.', icon: 'calendar' },
    { number: '3', title: 'We confirm & handle', text: 'Our team confirms the request and gets to work.', icon: 'tools' },
    { number: '4', title: 'Receive or collect', text: 'We deliver back or you collect your device.', icon: 'package' },
];
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="title"
            :description="description"
            :canonical="canonical"
            :robots="filters?.search ? 'noindex,follow' : 'index,follow,max-image-preview:large'"
        />

        <main class="bg-[#fbfbfb]">
            <div class="container-shell pb-7 pt-4 sm:pt-5">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                    <span>/</span>
                    <span class="text-prinx-600">Services</span>
                </nav>

                <div class="mt-3">
                    <h1 class="text-[28px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[34px]">{{ selectedCategory?.name || 'Services' }}</h1>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ selectedCategory?.description || 'Professional tech support, repairs, and delivery solutions you can trust.' }}</p>
                </div>

                <section class="relative mt-4 overflow-hidden rounded-[9px] border border-prinx-100 bg-gradient-to-r from-[#fff8f4] via-[#fffdfb] to-[#fff6f0] px-5 py-6 sm:px-7 lg:min-h-[218px] lg:px-8 lg:py-8">
                    <div class="relative z-10 grid items-center gap-8 lg:grid-cols-[.72fr_1.28fr]">
                        <div>
                            <h2 class="max-w-[430px] text-[25px] font-semibold leading-[1.23] tracking-[-.025em] text-slate-950 sm:text-[31px]">
                                Reliable Tech Services<br>for <span class="text-prinx-600">Your Devices</span>
                            </h2>
                            <p class="mt-3 max-w-[390px] text-xs leading-5 text-slate-600">From repairs to setup and fast delivery, Prinx has you covered with expert care and premium support.</p>
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="#service-grid" class="inline-flex h-10 items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-5 text-xs font-semibold text-white transition hover:bg-prinx-600">
                                    <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path stroke-linecap="round" d="M8 3v4M16 3v4M4 10h16"/></svg>
                                    Book a Service
                                </a>
                                <Link href="/pages/contact" class="inline-flex h-10 items-center justify-center gap-2 rounded-[6px] border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-800 transition hover:border-prinx-300 hover:text-prinx-600">
                                    <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                                    Talk to Support
                                </Link>
                            </div>
                        </div>

                        <div class="relative hidden min-h-[165px] items-end justify-center lg:flex">
                            <div class="relative z-10 mb-5 flex items-end justify-center gap-3">
                                <img :src="heroImages[0]" alt="Prinx service device" class="h-[112px] w-[92px] object-contain">
                                <img :src="heroImages[1]" alt="Prinx service equipment" class="h-[142px] w-[200px] object-contain">
                                <img :src="heroImages[2]" alt="Prinx service accessory" class="h-[92px] w-[116px] object-contain">
                            </div>
                        </div>
                    </div>
                </section>

                <section id="service-grid" class="mt-3">
                    <div v-if="visibleServices.length" class="grid gap-2.5 md:grid-cols-2 xl:grid-cols-4">
                        <Link v-for="service in visibleServices" :key="service.id" :href="`/services/${service.slug}`" class="group flex min-h-[116px] items-center gap-4 rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.06)]">
                            <div class="flex h-[64px] w-[64px] shrink-0 items-center justify-center overflow-hidden rounded-[8px] bg-slate-50 p-2">
                                <img :src="service.image_url" :alt="service.name" class="h-full w-full object-contain transition group-hover:scale-105">
                            </div>
                            <div class="min-w-0">
                                <h3 class="line-clamp-1 text-sm font-semibold text-slate-900">{{ service.name }}</h3>
                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ service.short_description || 'Professional support from the Prinx technical team.' }}</p>
                                <span class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-prinx-600">{{ service.price_from ? 'Book now' : 'Learn more' }} <span aria-hidden="true">→</span></span>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="rounded-[8px] border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500">No services are currently available.</div>
                </section>

                <section class="mt-3">
                    <h2 class="mb-2 text-sm font-semibold text-slate-900">How It Works</h2>
                    <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                        <article v-for="step in processSteps" :key="step.number" class="relative min-h-[104px] rounded-[8px] border border-slate-200 bg-white p-4">
                            <span class="absolute left-4 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-prinx-500 text-xs font-semibold text-white">{{ step.number }}</span>
                            <div class="flex items-start gap-3 pt-5">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-prinx-50 text-prinx-600">
                                    <svg v-if="step.icon === 'calendar'" viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7"><rect x="4" y="5" width="16" height="15" rx="2"/><path stroke-linecap="round" d="M8 3v4M16 3v4M4 10h16"/></svg>
                                    <svg v-else-if="step.icon === 'tools'" viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m14.7 6.3 3-3a4 4 0 0 1-5.2 5.2l-7.7 7.7a2 2 0 1 0 2.8 2.8l7.7-7.7a4 4 0 0 1 5.2-5.2l-3 3-2.8-.8-.8-2.8Z"/></svg>
                                    <svg v-else viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"/></svg>
                                </div>
                                <div><h3 class="text-xs font-semibold text-slate-900">{{ step.title }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ step.text }}</p></div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="mt-4 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Service Pricing <span class="font-normal text-slate-500">(Starting From)</span></h2>
                        <div class="mt-2 grid gap-2.5 sm:grid-cols-3">
                            <Link v-for="service in pricingServices" :key="service.id" :href="`/services/${service.slug}`" class="rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                                <h3 class="text-xs font-semibold text-slate-900">{{ service.name }}</h3>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ service.short_description }}</p>
                                <p class="mt-2 text-xs font-medium text-slate-500">From</p>
                                <strong class="mt-1 block text-sm font-semibold text-slate-900">{{ servicePrice(service) }}</strong>
                            </Link>
                            <div v-if="!pricingServices.length" class="col-span-full rounded-[8px] border border-slate-200 bg-white p-5 text-xs text-slate-500">Service pricing is confirmed after assessment.</div>
                        </div>
                        <Link href="/services" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-prinx-600 hover:text-prinx-700">View all services & pricing <span>→</span></Link>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">What You Can Expect</h2>
                        <div class="mt-2 grid gap-2.5 sm:grid-cols-3">
                            <article class="min-h-[126px] rounded-[8px] border border-slate-200 bg-white p-4">
                                <div class="text-2xl font-semibold leading-none text-prinx-500">“</div>
                                <p class="mt-1 text-xs leading-5 text-slate-600">Clear assessment before repair work begins, with the problem and next steps explained first.</p>
                                <div class="mt-3 flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-prinx-50 text-prinx-600"><svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" d="m7 12 3 3 7-7"/></svg></span><strong class="text-xs font-semibold text-slate-800">Transparent service</strong></div>
                            </article>
                            <article class="min-h-[126px] rounded-[8px] border border-slate-200 bg-white p-4">
                                <div class="text-2xl font-semibold leading-none text-prinx-500">“</div>
                                <p class="mt-1 text-xs leading-5 text-slate-600">Professional device handling, quality replacement parts, and support throughout the service process.</p>
                                <div class="mt-3 flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-prinx-50 text-prinx-600"><svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" d="m7 12 3 3 7-7"/></svg></span><strong class="text-xs font-semibold text-slate-800">Quality workmanship</strong></div>
                            </article>
                            <article class="min-h-[126px] rounded-[8px] border border-slate-200 bg-white p-4">
                                <div class="text-2xl font-semibold leading-none text-prinx-500">“</div>
                                <p class="mt-1 text-xs leading-5 text-slate-600">Fast communication and convenient delivery or collection options when your device is ready.</p>
                                <div class="mt-3 flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-prinx-50 text-prinx-600"><svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" d="m7 12 3 3 7-7"/></svg></span><strong class="text-xs font-semibold text-slate-800">Reliable support</strong></div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="mt-4 grid items-center gap-4 rounded-[8px] border border-prinx-100 bg-gradient-to-r from-[#fff6f0] to-[#fffaf7] px-5 py-4 sm:grid-cols-[1fr_auto] lg:grid-cols-[1.1fr_1fr]">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-prinx-500 shadow-sm">
                            <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg>
                        </span>
                        <div><h2 class="text-base font-semibold text-slate-900">Need help or want to book a service?</h2><p class="mt-1 text-xs text-slate-500">Get in touch with our team for service updates, booking assistance and support.</p></div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <a v-if="storeEmail" :href="`mailto:${storeEmail}?subject=Prinx%20Service%20Request`" class="inline-flex h-10 items-center justify-center rounded-[6px] bg-prinx-500 px-6 text-xs font-semibold text-white transition hover:bg-prinx-600">Email Us</a>
                        <Link href="/pages/contact" class="inline-flex h-10 items-center justify-center gap-2 rounded-[6px] border border-prinx-300 bg-white px-6 text-xs font-semibold text-slate-800 transition hover:bg-prinx-50 hover:text-prinx-600">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                            Contact Us
                        </Link>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
