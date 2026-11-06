<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    page: Object,
    locator: { type: Object, default: null },
});

const inertiaPage = usePage();
const store = computed(() => inertiaPage.props.store || {});
const locatorHost = ref(null);
const locatorError = ref(false);

const phoneHref = computed(() => store.value.phone ? `tel:${String(store.value.phone).replace(/\s+/g, '')}` : null);
const secondaryPhoneHref = computed(() => store.value.secondary_phone ? `tel:${String(store.value.secondary_phone).replace(/\s+/g, '')}` : null);
const emailHref = computed(() => store.value.email ? `mailto:${store.value.email}` : null);
const whatsappHref = computed(() => {
    const digits = String(store.value.whatsapp || store.value.phone || '').replace(/\D/g, '');
    return digits ? `https://wa.me/${digits}` : null;
});
const locationText = computed(() => [store.value.address, store.value.city, store.value.region, store.value.country].filter(Boolean).filter((value, index, values) => values.indexOf(value) === index).join(', '));
const mapsHref = computed(() => store.value.maps_url || 'https://www.google.com/maps/search/?api=1&query=Prinx%20Tech%2C%20Uhindini%20Street%2C%20Dodoma%2C%20Tanzania&query_place_id=ChIJ89WEjrrlTRgRikjhlXOgR0Q');

const loadLocatorLibrary = () => new Promise((resolve, reject) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        resolve();
        return;
    }

    if (customElements.get('gmpx-store-locator')) {
        resolve();
        return;
    }

    const existing = document.querySelector('script[data-prinx-google-maps-ecl]');
    if (existing) {
        customElements.whenDefined('gmpx-store-locator').then(resolve).catch(reject);
        return;
    }

    const script = document.createElement('script');
    script.type = 'module';
    script.src = 'https://ajax.googleapis.com/ajax/libs/@googlemaps/extended-component-library/0.6.15/index.min.js';
    script.dataset.prinxGoogleMapsEcl = 'true';
    script.addEventListener('load', () => customElements.whenDefined('gmpx-store-locator').then(resolve).catch(reject), { once: true });
    script.addEventListener('error', reject, { once: true });
    document.head.appendChild(script);
});

const initialiseLocator = async () => {
    if (!props.locator?.apiKey || !locatorHost.value || typeof document === 'undefined') return;

    try {
        await loadLocatorLibrary();
        await nextTick();

        const loader = document.createElement('gmpx-api-loader');
        loader.setAttribute('key', props.locator.apiKey);
        loader.setAttribute('solution-channel', 'GMP_QB_locatorplus_v11_c');

        const storeLocator = document.createElement('gmpx-store-locator');
        storeLocator.setAttribute('map-id', 'DEMO_MAP_ID');

        locatorHost.value.replaceChildren(loader, storeLocator);
        await customElements.whenDefined('gmpx-store-locator');

        storeLocator.configureFromQuickBuilder({
            locations: props.locator.locations,
            mapOptions: {
                center: { lat: -6.1759075, lng: 35.7490705 },
                fullscreenControl: true,
                mapTypeControl: false,
                streetViewControl: false,
                zoom: 15,
                zoomControl: true,
                maxZoom: 17,
                mapId: '',
            },
            mapsApiKey: props.locator.apiKey,
            capabilities: {
                input: false,
                autocomplete: false,
                directions: false,
                distanceMatrix: false,
                details: false,
                actions: false,
            },
        });
    } catch (error) {
        locatorError.value = true;
        console.error('Unable to initialise Google Maps store locator.', error);
    }
};

onMounted(initialiseLocator);
</script>

<template>
    <AppLayout>
        <SeoHead :title="page.title" :description="page.intro" canonical="/pages/contact" />

        <div class="container-shell pb-9 pt-4 sm:pt-5">
            <nav class="flex items-center gap-2 text-[10px] font-medium text-slate-500" aria-label="Breadcrumb">
                <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                <span>/</span>
                <span class="text-prinx-600">Contact Us</span>
            </nav>

            <section class="mt-3 overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                <div class="grid lg:grid-cols-[1.08fr_.92fr]">
                    <div class="flex flex-col justify-center px-5 py-7 sm:px-7 sm:py-9 lg:px-10 lg:py-11">
                        <div class="inline-flex w-fit items-center gap-2 rounded-[5px] bg-prinx-50 px-2.5 py-1.5 text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-600">
                            <span class="h-1.5 w-1.5 rounded-full bg-prinx-500"></span>
                            Contact Prinx
                        </div>

                        <h1 class="mt-4 max-w-[650px] text-[30px] font-semibold leading-[1.06] tracking-[-.03em] text-slate-950 sm:text-[38px] lg:text-[43px]">
                            Get the help you need, without the runaround.
                        </h1>
                        <p class="mt-4 max-w-[620px] text-[12px] leading-6 text-slate-600 sm:text-[13px]">
                            {{ page.intro }} Reach us for product questions, order support, technical services, software enquiries or directions to our Dodoma store.
                        </p>

                        <div class="mt-5 flex flex-wrap gap-2.5">
                            <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener" class="inline-flex h-[38px] items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-4 text-[10px] font-semibold text-white transition hover:bg-prinx-600">
                                Chat on WhatsApp
                                <span aria-hidden="true">→</span>
                            </a>
                            <a v-if="phoneHref" :href="phoneHref" class="inline-flex h-[38px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 text-[10px] font-semibold text-slate-700 transition hover:border-prinx-300 hover:text-prinx-600">
                                Call {{ store.phone }}
                            </a>
                        </div>

                        <div class="mt-6 grid max-w-[640px] grid-cols-1 gap-2 sm:grid-cols-3">
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-600">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">✓</span>
                                Sales assistance
                            </div>
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-600">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">✓</span>
                                Technical support
                            </div>
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-medium text-slate-600">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[5px] bg-prinx-50 text-prinx-600">✓</span>
                                Local store support
                            </div>
                        </div>
                    </div>

                    <div class="relative min-h-[310px] overflow-hidden border-t border-slate-100 bg-[#fff8f4] p-5 sm:p-7 lg:min-h-[420px] lg:border-l lg:border-t-0 lg:p-8">
                        <div class="absolute -right-20 -top-24 h-56 w-56 rounded-full border-[38px] border-white/70"></div>
                        <div class="absolute -bottom-24 -left-20 h-60 w-60 rounded-full border-[42px] border-prinx-100/60"></div>

                        <div class="relative z-10 flex h-full flex-col">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-500">Visit our store</p>
                                <span class="rounded-[5px] border border-prinx-100 bg-white/80 px-2.5 py-1.5 text-[8px] font-semibold uppercase tracking-[.1em] text-prinx-600">Dodoma</span>
                            </div>

                            <div class="my-auto py-7">
                                <div class="flex h-10 w-10 items-center justify-center rounded-[7px] bg-white text-prinx-600 shadow-[0_8px_24px_rgba(15,23,42,.06)]">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                </div>
                                <h2 class="mt-4 text-[25px] font-semibold leading-[1.08] tracking-[-.03em] text-slate-950 sm:text-[29px]">Prinx Tech</h2>
                                <p class="mt-2 max-w-[360px] text-[11px] leading-5 text-slate-600">{{ locationText || 'Uhindini Street, Dodoma, Tanzania' }}</p>
                            </div>

                            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                <div v-if="store.working_hours" class="rounded-[7px] border border-white bg-white/90 p-3 shadow-[0_5px_18px_rgba(15,23,42,.04)]">
                                    <span class="text-[8px] font-semibold uppercase tracking-[.1em] text-slate-400">Working hours</span>
                                    <strong class="mt-1 block text-[10px] font-semibold leading-5 text-slate-800">{{ store.working_hours }}</strong>
                                </div>
                                <a :href="mapsHref" target="_blank" rel="noopener" class="flex min-h-[64px] items-center justify-between gap-3 rounded-[7px] border border-prinx-100 bg-prinx-500 p-3 text-white transition hover:bg-prinx-600">
                                    <div>
                                        <span class="text-[8px] font-semibold uppercase tracking-[.1em] text-white/70">Directions</span>
                                        <strong class="mt-1 block text-[10px] font-semibold">Open Google Maps</strong>
                                    </div>
                                    <span class="text-base">↗</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-7">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-500">Talk to us</p>
                        <h2 class="mt-1.5 text-[20px] font-semibold tracking-[-.02em] text-slate-900">Choose the easiest way to reach Prinx</h2>
                    </div>
                    <p class="max-w-[420px] text-[10px] leading-5 text-slate-500 sm:text-right">Our contact details are managed from the Prinx admin panel, so this page always shows the latest information.</p>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <a v-if="phoneHref" :href="phoneHref" class="group rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><path d="M7 3h3l1.5 4-2 1.5a14 14 0 0 0 6 6l1.5-2 4 1.5v3c0 2-1 3-3 3C10 20 4 14 4 6c0-2 1-3 3-3Z"/></svg>
                            </span>
                            <span class="text-[10px] text-slate-300 transition group-hover:text-prinx-500">↗</span>
                        </div>
                        <p class="mt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Call us</p>
                        <strong class="mt-1 block text-[12px] font-semibold text-slate-800">{{ store.phone }}</strong>
                        <span class="mt-1.5 block text-[9px] leading-4 text-slate-500">Product, order and service assistance.</span>
                    </a>

                    <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener" class="group rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><path d="M20 11.5a8 8 0 0 1-11.7 7.1L4 20l1.4-4.1A8 8 0 1 1 20 11.5Z"/><path d="M8.5 8.5c.4 3.2 2.1 5 5 6l1.5-1.3 2 .8"/></svg>
                            </span>
                            <span class="text-[10px] text-slate-300 transition group-hover:text-prinx-500">↗</span>
                        </div>
                        <p class="mt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">WhatsApp</p>
                        <strong class="mt-1 block text-[12px] font-semibold text-slate-800">{{ store.whatsapp || store.phone }}</strong>
                        <span class="mt-1.5 block text-[9px] leading-4 text-slate-500">Quick questions, availability and ordering.</span>
                    </a>

                    <a v-if="emailHref" :href="emailHref" class="group rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                            </span>
                            <span class="text-[10px] text-slate-300 transition group-hover:text-prinx-500">↗</span>
                        </div>
                        <p class="mt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Email</p>
                        <strong class="mt-1 block break-all text-[12px] font-semibold text-slate-800">{{ store.email }}</strong>
                        <span class="mt-1.5 block text-[9px] leading-4 text-slate-500">Detailed enquiries and business requests.</span>
                    </a>

                    <a v-if="secondaryPhoneHref" :href="secondaryPhoneHref" class="group rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><path d="M7 3h3l1.5 4-2 1.5a14 14 0 0 0 6 6l1.5-2 4 1.5v3c0 2-1 3-3 3C10 20 4 14 4 6c0-2 1-3 3-3Z"/></svg>
                            </span>
                            <span class="text-[10px] text-slate-300 transition group-hover:text-prinx-500">↗</span>
                        </div>
                        <p class="mt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Alternative line</p>
                        <strong class="mt-1 block text-[12px] font-semibold text-slate-800">{{ store.secondary_phone }}</strong>
                        <span class="mt-1.5 block text-[9px] leading-4 text-slate-500">Another direct line to the Prinx team.</span>
                    </a>

                    <article v-if="!secondaryPhoneHref && store.working_hours" class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-prinx-50 text-prinx-600">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        </span>
                        <p class="mt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Working hours</p>
                        <strong class="mt-1 block text-[12px] font-semibold leading-5 text-slate-800">{{ store.working_hours }}</strong>
                        <span class="mt-1.5 block text-[9px] leading-4 text-slate-500">Visit or contact us during business hours.</span>
                    </article>
                </div>
            </section>

            <section class="mt-7 overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                <div class="grid lg:grid-cols-[300px_minmax(0,1fr)] xl:grid-cols-[330px_minmax(0,1fr)]">
                    <div class="flex flex-col border-b border-slate-200 p-5 sm:p-6 lg:border-b-0 lg:border-r">
                        <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-500">Find us</p>
                        <h2 class="mt-2 text-[22px] font-semibold tracking-[-.02em] text-slate-900">Visit Prinx Tech in Dodoma</h2>
                        <p class="mt-3 text-[10px] leading-5 text-slate-500">See our location on the map or open Google Maps for turn-by-turn directions.</p>

                        <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                            <div>
                                <span class="text-[8px] font-semibold uppercase tracking-[.1em] text-slate-400">Address</span>
                                <p class="mt-1 text-[11px] font-medium leading-5 text-slate-700">{{ locationText || 'Uhindini Street, Dodoma, Tanzania' }}</p>
                            </div>
                            <div v-if="store.working_hours">
                                <span class="text-[8px] font-semibold uppercase tracking-[.1em] text-slate-400">Hours</span>
                                <p class="mt-1 text-[11px] font-medium leading-5 text-slate-700">{{ store.working_hours }}</p>
                            </div>
                        </div>

                        <a :href="mapsHref" target="_blank" rel="noopener" class="mt-5 inline-flex h-[38px] w-fit items-center justify-center gap-2 rounded-[6px] bg-slate-950 px-4 text-[10px] font-semibold text-white transition hover:bg-prinx-600">
                            Get directions
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>

                    <div class="min-h-[460px] bg-slate-50">
                        <div v-if="locator?.apiKey && !locatorError" ref="locatorHost" class="prinx-contact-locator"></div>
                        <div v-else class="flex h-full min-h-[460px] items-center justify-center p-6 text-center">
                            <div class="max-w-[300px]">
                                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-[7px] bg-white text-prinx-600 shadow-sm">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.7"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                </span>
                                <p class="mt-3 text-[12px] font-semibold text-slate-800">{{ locatorError ? 'Map could not be loaded' : 'Interactive map is not configured yet' }}</p>
                                <p class="mt-1.5 text-[10px] leading-5 text-slate-500">You can still open the location directly in Google Maps.</p>
                                <a :href="mapsHref" target="_blank" rel="noopener" class="mt-3 inline-flex text-[10px] font-semibold text-prinx-600 hover:text-prinx-700">Open Google Maps →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-5 rounded-[8px] bg-slate-950 px-5 py-5 text-white sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[.12em] text-prinx-400">Need something specific?</p>
                        <h2 class="mt-1.5 text-[17px] font-semibold">Shop products or explore our technical services.</h2>
                        <p class="mt-1.5 text-[10px] leading-5 text-slate-400">Find what you need online, then contact us if you want help choosing the right option.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link href="/shop" class="inline-flex h-[36px] items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-[9px] font-semibold text-white transition hover:bg-prinx-600">Shop Products</Link>
                        <Link href="/services" class="inline-flex h-[36px] items-center justify-center rounded-[6px] border border-slate-700 px-4 text-[9px] font-semibold text-white transition hover:border-prinx-400 hover:text-prinx-300">View Services</Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style>
.prinx-contact-locator {
    width: 100%;
    height: 100%;
    min-height: 560px;
}

.prinx-contact-locator gmpx-store-locator {
    width: 100%;
    height: 100%;
    --gmpx-color-surface: #ffffff;
    --gmpx-color-on-surface: #0f172a;
    --gmpx-color-on-surface-variant: #64748b;
    --gmpx-color-primary: #ff4d0a;
    --gmpx-color-outline: #e2e8f0;
    --gmpx-fixed-panel-width-row-layout: 25em;
    --gmpx-fixed-panel-height-column-layout: 60%;
    --gmpx-font-family-base: var(--store-font);
    --gmpx-font-family-headings: var(--store-font);
    --gmpx-font-size-base: 0.8125rem;
    --gmpx-hours-color-open: #15803d;
    --gmpx-hours-color-closed: #dc2626;
    --gmpx-rating-color: #ff4d0a;
    --gmpx-rating-color-empty: #e2e8f0;
}

@media (max-width: 640px) {
    .prinx-contact-locator {
        min-height: 480px;
    }
}
</style>
