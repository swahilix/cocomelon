<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({ page: Object });
const inertiaPage = usePage();
const store = computed(() => inertiaPage.props.store || {});
const whatsappHref = computed(() => {
    const digits = String(store.value.whatsapp || store.value.phone || '').replace(/\D/g, '');
    return digits ? `https://wa.me/${digits}` : null;
});

const focusAreas = [
    {
        title: 'Electronics',
        text: 'Reliable devices and everyday technology selected for practical use.',
        icon: 'device',
    },
    {
        title: 'Accessories',
        text: 'Useful accessories that keep your devices connected, protected and productive.',
        icon: 'accessory',
    },
    {
        title: 'Technical Services',
        text: 'Professional support for installations, maintenance and technology needs.',
        icon: 'service',
    },
    {
        title: 'Software Solutions',
        text: 'Purpose-built software for businesses and organisations that need better systems.',
        icon: 'software',
    },
];

const standards = [
    { title: 'Clear information', text: 'Straightforward product details, pricing and guidance before you buy.' },
    { title: 'Responsive support', text: 'A team you can reach when you need sales or technical assistance.' },
    { title: 'Practical solutions', text: 'Recommendations focused on what works for your real needs and budget.' },
];
</script>

<template>
    <AppLayout>
        <SeoHead :title="page.title" :description="page.intro" canonical="/pages/about" />

        <div class="container-shell pb-8 pt-4 sm:pt-5">
            <nav class="flex items-center gap-2 text-[10px] font-medium text-slate-500" aria-label="Breadcrumb">
                <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                <span>/</span>
                <span class="text-prinx-600">About Us</span>
            </nav>

            <section class="mt-3 overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                <div class="grid lg:grid-cols-[1.08fr_.92fr]">
                    <div class="flex flex-col justify-center px-5 py-7 sm:px-7 sm:py-9 lg:px-10 lg:py-11">
                        <div class="inline-flex w-fit items-center gap-2 rounded-[5px] bg-prinx-50 px-2.5 py-1.5 text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-600">
                            <span class="h-1.5 w-1.5 rounded-full bg-prinx-500"></span>
                            About Prinx
                        </div>

                        <h1 class="mt-4 max-w-[680px] text-[30px] font-semibold leading-[1.06] tracking-[-.03em] text-slate-950 sm:text-[38px] lg:text-[43px]">
                            Technology, support and solutions you can rely on.
                        </h1>
                        <p class="mt-4 max-w-[650px] text-[12px] leading-6 text-slate-600 sm:text-[13px]">
                            {{ page.intro }} We bring products, services and software together in one place so customers can buy with clarity and get dependable support after the sale.
                        </p>

                        <div class="mt-5 flex flex-wrap gap-2.5">
                            <Link href="/shop" class="inline-flex h-[38px] items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[10px] font-semibold text-white transition hover:bg-prinx-600">
                                Shop Products
                                <span aria-hidden="true">→</span>
                            </Link>
                            <Link href="/services" class="inline-flex h-[38px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 text-[10px] font-semibold text-slate-700 transition hover:border-prinx-300 hover:text-prinx-600">
                                Explore Services
                            </Link>
                        </div>

                        <div class="mt-6 grid max-w-[640px] grid-cols-1 gap-2 sm:grid-cols-3">
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-600">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[5px] bg-emerald-50 text-emerald-600">✓</span>
                                Original products
                            </div>
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-600">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[5px] bg-amber-50 text-amber-600">✓</span>
                                Local customer support
                            </div>
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-600">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[5px] bg-sky-50 text-sky-600">✓</span>
                                Practical tech solutions
                            </div>
                        </div>
                    </div>

                    <div class="relative min-h-[330px] overflow-hidden border-t border-slate-100 bg-[#fff8f4] p-5 sm:p-7 lg:min-h-[430px] lg:border-l lg:border-t-0 lg:p-8">
                        <div class="absolute -right-16 -top-20 h-52 w-52 rounded-full border-[34px] border-white/70"></div>
                        <div class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full border-[38px] border-prinx-100/60"></div>

                        <div class="relative z-10 flex h-full flex-col">
                            <div class="flex items-center justify-between gap-4">
                                <img src="/assets/prinx-logo.png" :alt="store.name" class="h-9 w-auto max-w-[150px] object-contain">
                                <span class="rounded-[5px] border border-prinx-100 bg-white/80 px-2.5 py-1.5 text-[8px] font-semibold uppercase tracking-[.1em] text-prinx-600">Technology Partner</span>
                            </div>

                            <div class="my-auto py-7">
                                <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-500">One place. More possibilities.</p>
                                <h2 class="mt-2 max-w-[360px] text-[27px] font-semibold leading-[1.08] tracking-[-.03em] text-slate-950 sm:text-[31px]">Products, services and software working together.</h2>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="rounded-[7px] border border-white bg-white/90 p-3 shadow-[0_5px_18px_rgba(15,23,42,.04)]">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 6h6M10 18h4"/></svg>
                                    </div>
                                    <strong class="mt-2 block text-[10px] font-semibold text-slate-800">Electronics</strong>
                                </div>
                                <div class="rounded-[7px] border border-white bg-white/90 p-3 shadow-[0_5px_18px_rgba(15,23,42,.04)]">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path d="M4 8h16v10H4zM8 5h8M9 13h6"/></svg>
                                    </div>
                                    <strong class="mt-2 block text-[10px] font-semibold text-slate-800">Accessories</strong>
                                </div>
                                <div class="rounded-[7px] border border-white bg-white/90 p-3 shadow-[0_5px_18px_rgba(15,23,42,.04)]">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
                                    </div>
                                    <strong class="mt-2 block text-[10px] font-semibold text-slate-800">Software</strong>
                                </div>
                                <div class="rounded-[7px] border border-white bg-white/90 p-3 shadow-[0_5px_18px_rgba(15,23,42,.04)]">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.7"><path d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path d="m9 12 2 2 4-4"/></svg>
                                    </div>
                                    <strong class="mt-2 block text-[10px] font-semibold text-slate-800">Services</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-5 grid gap-3 lg:grid-cols-[1.15fr_.85fr]">
                <article class="rounded-[8px] border border-slate-200 bg-white p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7"><path d="M5 20V8l7-4 7 4v12M9 20v-6h6v6M8 10h.01M12 10h.01M16 10h.01"/></svg>
                        </span>
                        <div>
                            <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-500">Who we are</p>
                            <h2 class="mt-1.5 text-[18px] font-semibold text-slate-900">{{ page.sections?.[0]?.heading || 'What we do' }}</h2>
                            <p class="mt-3 max-w-[760px] text-[11px] leading-6 text-slate-600">{{ page.sections?.[0]?.body }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-[8px] border border-slate-200 bg-slate-950 p-5 text-white sm:p-6">
                    <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-400">Our standard</p>
                    <h2 class="mt-2 text-[18px] font-semibold">Better service starts with trust.</h2>
                    <p class="mt-3 text-[11px] leading-6 text-slate-300">{{ page.sections?.[1]?.body }}</p>
                    <div class="mt-4 flex items-center gap-2 text-[9px] font-medium text-slate-300">
                        <span class="h-px w-8 bg-prinx-500"></span>
                        Built around customers, not complexity.
                    </div>
                </article>
            </section>

            <section class="mt-7">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-500">What we offer</p>
                        <h2 class="mt-1.5 text-[20px] font-semibold tracking-[-.02em] text-slate-900">Technology for everyday and business needs</h2>
                    </div>
                    <Link href="/shop" class="text-[10px] font-semibold text-prinx-600 hover:text-prinx-700">Browse all products →</Link>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="area in focusAreas" :key="area.title" class="rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                        <div class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                            <svg v-if="area.icon === 'device'" viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 6h6M10 18h4"/></svg>
                            <svg v-else-if="area.icon === 'accessory'" viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><path d="M5 8h14v9H5zM8 5h8M8 12h3M13 12h3"/></svg>
                            <svg v-else-if="area.icon === 'service'" viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><path d="m14 6 4 4M12 8l4 4-7.5 7.5H4.5v-4L12 8Z"/><path d="M15 5 17 3l4 4-2 2"/></svg>
                            <svg v-else viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16M9 9v11"/></svg>
                        </div>
                        <h3 class="mt-3 text-[12px] font-semibold text-slate-800">{{ area.title }}</h3>
                        <p class="mt-1.5 text-[10px] leading-5 text-slate-500">{{ area.text }}</p>
                    </article>
                </div>
            </section>

            <section class="mt-7 rounded-[8px] border border-slate-200 bg-white p-5 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-[.8fr_1.2fr] lg:items-center">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-500">Why customers choose Prinx</p>
                        <h2 class="mt-2 text-[21px] font-semibold leading-tight tracking-[-.02em] text-slate-900">Simple service. Clear choices. Dependable support.</h2>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div v-for="item in standards" :key="item.title" class="border-l-2 border-prinx-100 pl-3">
                            <strong class="block text-[10px] font-semibold text-slate-800">{{ item.title }}</strong>
                            <p class="mt-1.5 text-[9px] leading-4 text-slate-500">{{ item.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-5 overflow-hidden rounded-[8px] bg-prinx-500">
                <div class="flex flex-col gap-4 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div class="max-w-[700px] text-white">
                        <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-white/70">Need help choosing?</p>
                        <h2 class="mt-1.5 text-[19px] font-semibold">Talk to the Prinx team before you buy.</h2>
                        <p class="mt-1.5 text-[10px] leading-5 text-white/80">Tell us what you need and we’ll help you find the right product, service or solution.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link href="/pages/contact" class="inline-flex h-[36px] items-center justify-center rounded-[6px] bg-white px-4 text-[10px] font-semibold text-prinx-600 transition hover:bg-prinx-50">Contact Us</Link>
                        <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener" class="inline-flex h-[36px] items-center justify-center rounded-[6px] border border-white/35 px-4 text-[10px] font-semibold text-white transition hover:bg-white/10">WhatsApp</a>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
