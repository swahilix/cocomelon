<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    page: Object,
    locator: { type: Object, default: null },
});

const appPage = usePage();
const storeName = appPage.props.store?.name || 'Prinx Tech';

const locatorHost = ref(null);
const locatorError = ref(false);

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
        <SeoHead :title="page.title" :description="page.intro" />
        <div class="container-shell max-w-4xl py-10">
            <p class="section-kicker">{{ storeName }}</p>
            <h1 class="section-title">{{ page.title }}</h1>
            <p class="mt-3 text-lg text-slate-500">{{ page.intro }}</p>

            <div class="mt-8 grid gap-4">
                <section v-for="s in page.sections" :key="s.heading" class="card p-5">
                    <h2 class="text-lg font-black">{{ s.heading }}</h2>
                    <p class="mt-2 leading-7 text-slate-600">{{ s.body }}</p>
                </section>
            </div>

            <section v-if="locator?.apiKey" class="mt-6 overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                    <p class="text-sm font-semibold text-slate-950">Find Prinx Tech</p>
                    <p class="mt-0.5 text-xs text-slate-500">Uhindini Street, Dodoma, Tanzania</p>
                </div>
                <div v-if="locatorError" class="px-4 py-8 text-center text-sm text-slate-500">The map could not be loaded. Please use the business address above.</div>
                <div v-else ref="locatorHost" class="prinx-store-locator"></div>
            </section>
        </div>
    </AppLayout>
</template>

<style>
.prinx-store-locator {
    width: 100%;
    height: 560px;
}

.prinx-store-locator gmpx-store-locator {
    width: 100%;
    height: 100%;
    --gmpx-color-surface: #fff;
    --gmpx-color-on-surface: #212121;
    --gmpx-color-on-surface-variant: #757575;
    --gmpx-color-primary: #1967d2;
    --gmpx-color-outline: #e0e0e0;
    --gmpx-fixed-panel-width-row-layout: 28.5em;
    --gmpx-fixed-panel-height-column-layout: 65%;
    --gmpx-font-family-base: "Roboto", sans-serif;
    --gmpx-font-family-headings: "Roboto", sans-serif;
    --gmpx-font-size-base: 0.875rem;
    --gmpx-hours-color-open: #188038;
    --gmpx-hours-color-closed: #d50000;
    --gmpx-rating-color: #ffb300;
    --gmpx-rating-color-empty: #e0e0e0;
}

@media (max-width: 640px) {
    .prinx-store-locator {
        height: 480px;
    }
}
</style>
