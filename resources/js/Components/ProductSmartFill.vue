<script setup>
import { computed, ref } from 'vue';
import Icon from './Icon.vue';
import MobileBarcodeScanner from './MobileBarcodeScanner.vue';
import { postForm, postJson } from '../lib/adminApi';

// Finds product details from a barcode or a photo and lets the user review
// them before filling the product form. Emits "apply" with the chosen data.
const props = defineProps({
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    barcodeEnabled: { type: Boolean, default: true },
    aiEnabled: { type: Boolean, default: false },
});
const emit = defineEmits(['apply']);

const barcode = ref('');
const scannerOpen = ref(false);
const photo = ref(null);
const photoPreview = ref('');
const photoInput = ref(null);
const loading = ref('');
const error = ref('');
const suggestion = ref(null);
const selectedImages = ref([]);
const usePhoto = ref(true);

const categoryName = computed(() => props.categories.find((item) => Number(item.id) === Number(suggestion.value?.category_id))?.name || '');
const brandName = computed(() => props.brands.find((item) => Number(item.id) === Number(suggestion.value?.brand_id))?.name || '');
const sourceLabel = computed(() => ({
    barcode_database: 'Barcode database',
    web_search: 'Web search (AI)',
    photo: 'Photo (AI)',
}[suggestion.value?.source] || 'Lookup'));
const host = (url) => { try { return new URL(url).hostname.replace(/^www\./, ''); } catch { return url; } };

const reset = () => {
    error.value = '';
    suggestion.value = null;
    selectedImages.value = [];
};

async function lookupBarcode(value = barcode.value) {
    const code = String(value || '').replace(/\D+/g, '');
    if (!code) return;
    barcode.value = code;
    reset();
    loading.value = 'barcode';
    const response = await postJson('/admin/products/lookup/barcode', { gtin: code });
    loading.value = '';
    if (!response.ok) {
        error.value = response.message;
        return;
    }
    suggestion.value = response.data;
    selectedImages.value = [...(response.data.image_urls || [])].slice(0, 3);
}

function onScanned(code) {
    lookupBarcode(code);
}

function choosePhoto(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photo.value = file;
    photoPreview.value = URL.createObjectURL(file);
    identifyPhoto();
}

async function identifyPhoto() {
    if (!photo.value) return;
    reset();
    loading.value = 'photo';
    const data = new FormData();
    data.append('image', photo.value);
    const response = await postForm('/admin/products/lookup/image', data);
    loading.value = '';
    if (photoInput.value) photoInput.value.value = '';
    if (!response.ok) {
        error.value = response.message;
        return;
    }
    suggestion.value = response.data;
    selectedImages.value = [];
    usePhoto.value = true;
}

const toggleImage = (url) => {
    selectedImages.value = selectedImages.value.includes(url)
        ? selectedImages.value.filter((item) => item !== url)
        : [...selectedImages.value, url];
};

function apply(mode) {
    emit('apply', {
        mode,
        suggestion: suggestion.value,
        imageUrls: selectedImages.value,
        photo: suggestion.value?.source === 'photo' && usePhoto.value ? photo.value : null,
    });
}

const dismiss = () => reset();
</script>

<template>
    <section class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="flex items-center gap-2 font-semibold"><Icon name="magic-star" :size="18" class="text-prinx-600" /> Smart fill</h2>
                <p class="mt-1 text-xs text-slate-500">Scan the barcode or take a photo and the product details are found for you. You review them before anything is filled in.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <form class="rounded-[8px] border border-slate-200 p-3" @submit.prevent="lookupBarcode()">
                <label class="label">Barcode</label>
                <div class="flex gap-2">
                    <input v-model.trim="barcode" class="input min-w-0 flex-1 font-mono" inputmode="numeric" placeholder="Scan with a USB scanner or type" :disabled="!barcodeEnabled || !!loading">
                    <button type="button" class="btn-light shrink-0 px-3" title="Scan with camera" :disabled="!barcodeEnabled || !!loading" @click="scannerOpen = true"><Icon name="scan-barcode" :size="17" /></button>
                    <button class="btn-primary shrink-0 px-3" :disabled="!barcodeEnabled || !barcode || !!loading">{{ loading === 'barcode' ? 'Looking up…' : 'Look up' }}</button>
                </div>
                <p v-if="!barcodeEnabled" class="mt-1 text-[11px] text-slate-400">Barcode lookup is turned off on the server.</p>
                <p v-else class="mt-1 text-[11px] text-slate-400">Checks a barcode database first{{ aiEnabled ? ', then searches the web' : '' }}.</p>
            </form>

            <div class="rounded-[8px] border border-slate-200 p-3">
                <label class="label">Photo</label>
                <div class="flex items-center gap-2">
                    <img v-if="photoPreview" :src="photoPreview" alt="" class="h-10 w-10 shrink-0 rounded-[6px] border border-slate-200 object-cover">
                    <label :class="['btn-light flex-1 cursor-pointer justify-center', (!aiEnabled || loading) && 'pointer-events-none opacity-50']">
                        <Icon name="camera" :size="17" /> {{ loading === 'photo' ? 'Identifying product…' : (photo ? 'Take another photo' : 'Take or choose a photo') }}
                        <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" :disabled="!aiEnabled || !!loading" @change="choosePhoto">
                    </label>
                </div>
                <p class="mt-1 text-[11px] text-slate-400">{{ aiEnabled ? 'Photograph the front, box or label. AI reads it and searches for the specs.' : 'Needs OPENAI_API_KEY on the server.' }}</p>
            </div>
        </div>

        <p v-if="loading" class="mt-3 text-xs text-slate-500">{{ loading === 'photo' ? 'This can take up to a minute while the product is identified and checked online.' : 'Searching…' }}</p>
        <p v-if="error" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <div v-if="suggestion" class="mt-4 rounded-[10px] border border-prinx-200 bg-prinx-50/40 p-4">
            <div v-if="suggestion.existing_product" class="mb-3 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                This barcode already belongs to <a :href="`/admin/products/${suggestion.existing_product.slug}/edit`" class="font-semibold underline">{{ suggestion.existing_product.name }}</a>{{ suggestion.existing_product.trashed ? ' (in Trash)' : '' }}.
            </div>

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[.12em] text-slate-400">{{ sourceLabel }}<template v-if="suggestion.source !== 'barcode_database'"> · {{ suggestion.confidence }} confidence</template></p>
                    <p class="mt-1 text-base font-semibold text-slate-950">{{ suggestion.name || 'Unnamed product' }}</p>
                    <p class="text-xs text-slate-600">
                        <span v-if="suggestion.brand">Brand: {{ brandName || suggestion.brand }}<span v-if="!brandName" class="text-amber-700"> (not in your brands)</span></span>
                        <span v-if="suggestion.model"> · Model: {{ suggestion.model }}</span>
                        <span v-if="suggestion.gtin"> · Barcode: {{ suggestion.gtin }}</span>
                        <span v-if="suggestion.category"> · Category: {{ categoryName || suggestion.category }}<span v-if="!categoryName" class="text-amber-700"> (no match)</span></span>
                    </p>
                </div>
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-900" @click="dismiss">Dismiss</button>
            </div>

            <p v-if="suggestion.short_description" class="mt-3 text-sm text-slate-700">{{ suggestion.short_description }}</p>
            <p v-if="suggestion.description" class="mt-2 line-clamp-4 whitespace-pre-line text-xs text-slate-600">{{ suggestion.description }}</p>

            <dl v-if="suggestion.specifications?.length" class="mt-3 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                <div v-for="spec in suggestion.specifications.slice(0, 16)" :key="spec.label" class="flex gap-2"><dt class="shrink-0 text-slate-500">{{ spec.label }}:</dt><dd class="font-medium text-slate-800">{{ spec.value }}</dd></div>
            </dl>

            <div v-if="suggestion.image_urls?.length" class="mt-3">
                <p class="text-xs font-semibold text-slate-600">Images found <span class="font-normal text-slate-400">— tap to include; check you are allowed to use them</span></p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-for="url in suggestion.image_urls" :key="url" type="button" :class="['relative h-16 w-16 overflow-hidden rounded-[8px] border-2 bg-white', selectedImages.includes(url) ? 'border-prinx-500' : 'border-slate-200 opacity-60']" @click="toggleImage(url)">
                        <img :src="url" alt="" class="h-full w-full object-contain" referrerpolicy="no-referrer" @error="$event.target.closest('button').style.display = 'none'">
                    </button>
                </div>
            </div>

            <label v-if="suggestion.source === 'photo' && photo" class="mt-3 flex items-center gap-2 text-sm"><input v-model="usePhoto" type="checkbox"> Use my photo as the main product image</label>

            <p v-if="suggestion.notes" class="mt-3 text-xs text-slate-500">{{ suggestion.notes }}</p>
            <p v-if="suggestion.sources?.length" class="mt-2 text-[11px] text-slate-500">Sources:
                <a v-for="(source, index) in suggestion.sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="text-prinx-700 hover:underline">{{ host(source.url) }}<span v-if="index < suggestion.sources.length - 1">, </span></a>
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn-primary" @click="apply('empty')"><Icon name="tick-circle" :size="16" /> Fill empty fields</button>
                <button type="button" class="btn-light" @click="apply('all')">Replace all fields</button>
            </div>
            <p class="mt-2 text-[11px] text-slate-500">Check the details before saving. AI results can be wrong.</p>
        </div>

        <MobileBarcodeScanner v-model:open="scannerOpen" title="Scan product barcode" message="Point the rear camera at the barcode on the box or product." @scanned="onScanned" />
    </section>
</template>
