<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    missingCount: { type: Number, default: 0 },
    withGtinCount: { type: Number, default: 0 },
});

const search = ref('');
const modalOpen = ref(false);
const activeProduct = ref(null);
const video = ref(null);
const gtin = ref('');
const scannerMessage = ref('');
const scannerError = ref('');
const scanning = ref(false);
const captured = ref(false);
const savingNext = ref(false);

let cameraStream = null;
let detectorTimer = null;
let zxingReader = null;
let zxingControls = null;

const form = useForm({ gtin: '' });

const filteredProducts = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.products;

    return props.products.filter((product) => [
        product.name,
        product.sku,
        product.brand?.name,
        product.category?.name,
    ].some((value) => String(value || '').toLowerCase().includes(term)));
});

const progressTotal = computed(() => props.withGtinCount + props.missingCount);
const progress = computed(() => progressTotal.value > 0
    ? Math.round((props.withGtinCount / progressTotal.value) * 100)
    : 100);

const normalizedGtin = computed(() => gtin.value.trim());
const canSave = computed(() => isValidGtin(normalizedGtin.value) && !form.processing);

function hasGtinLength(value) {
    return /^(?:\d{8}|\d{12}|\d{13}|\d{14})$/.test(String(value || ''));
}

function isValidGtin(value) {
    if (!hasGtinLength(value)) return false;

    const digits = String(value).split('').map(Number);
    const checkDigit = digits.pop();
    let sum = 0;

    digits.reverse().forEach((digit, index) => {
        sum += digit * (index % 2 === 0 ? 3 : 1);
    });

    return ((10 - (sum % 10)) % 10) === checkDigit;
}

function normalizeScan(value) {
    const raw = String(value || '').trim();
    if (/^\d+$/.test(raw)) return raw;

    const ai01 = raw.match(/(?:\(01\)|01)(\d{14})/);
    return ai01?.[1] || '';
}

async function openScanner(product) {
    stopScanner();
    activeProduct.value = product;
    gtin.value = '';
    form.clearErrors();
    scannerError.value = '';
    scannerMessage.value = 'Point the rear camera at the product barcode.';
    captured.value = false;
    modalOpen.value = true;
    await nextTick();
    await startScanner();
}

function closeScanner() {
    stopScanner();
    modalOpen.value = false;
    activeProduct.value = null;
    gtin.value = '';
    captured.value = false;
    form.clearErrors();
}

function stopScanner() {
    scanning.value = false;

    if (detectorTimer) {
        window.clearTimeout(detectorTimer);
        detectorTimer = null;
    }

    if (zxingControls) {
        try { zxingControls.stop(); } catch (_) {}
        zxingControls = null;
    }

    zxingReader = null;

    if (cameraStream) {
        cameraStream.getTracks().forEach((track) => track.stop());
        cameraStream = null;
    }

    if (video.value) video.value.srcObject = null;
}

async function startScanner() {
    if (!modalOpen.value || !video.value) return;

    stopScanner();
    scannerError.value = '';
    captured.value = false;
    scanning.value = true;

    if (!window.isSecureContext) {
        scanning.value = false;
        scannerError.value = 'Camera scanning requires HTTPS. You can still enter the GTIN manually below.';
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        scanning.value = false;
        scannerError.value = 'This browser cannot access the camera. Enter the GTIN manually below.';
        return;
    }

    let zxingError = null;

    try {
        await startZxingDetector();
        scannerMessage.value = 'Scanner ready. Hold the barcode steady inside the frame.';
        return;
    } catch (error) {
        zxingError = error;

        if (error?.name === 'NotAllowedError') {
            stopScanner();
            scannerError.value = 'Camera permission was denied. Allow camera access in your browser settings, or enter the GTIN manually.';
            return;
        }

        stopScanner();
        scanning.value = true;
    }

    try {
        if ('BarcodeDetector' in window) {
            await startNativeDetector();
            scannerMessage.value = 'Scanner ready. Hold the barcode steady inside the frame.';
            return;
        }

        throw zxingError || new Error('No barcode scanner is available in this browser.');
    } catch (error) {
        stopScanner();
        const name = error?.name || '';
        scannerError.value = name === 'NotAllowedError'
            ? 'Camera permission was denied. Allow camera access in your browser settings, or enter the GTIN manually.'
            : 'The camera could not be started. Try again or enter the GTIN manually.';
    }
}

async function startNativeDetector() {
    cameraStream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1280 },
            height: { ideal: 720 },
        },
    });

    video.value.srcObject = cameraStream;
    video.value.setAttribute('playsinline', 'true');
    await video.value.play();

    const wanted = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'itf', 'data_matrix'];
    let formats = wanted;

    if (typeof window.BarcodeDetector.getSupportedFormats === 'function') {
        const supported = await window.BarcodeDetector.getSupportedFormats();
        formats = wanted.filter((format) => supported.includes(format));
    }

    const detector = formats.length
        ? new window.BarcodeDetector({ formats })
        : new window.BarcodeDetector();

    const detect = async () => {
        if (!scanning.value || !modalOpen.value || !video.value) return;

        try {
            const codes = await detector.detect(video.value);
            const candidate = codes
                .map((code) => normalizeScan(code.rawValue))
                .find((value) => hasGtinLength(value));

            if (candidate) {
                capture(candidate);
                return;
            }
        } catch (_) {}

        detectorTimer = window.setTimeout(detect, 140);
    };

    detectorTimer = window.setTimeout(detect, 200);
}

async function startZxingDetector() {
    const { BrowserMultiFormatReader } = await import('@zxing/browser');
    zxingReader = new BrowserMultiFormatReader(undefined, {
        delayBetweenScanAttempts: 180,
        delayBetweenScanSuccess: 350,
    });

    zxingControls = await zxingReader.decodeFromConstraints(
        {
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        },
        video.value,
        (result, _error, controls) => {
            if (!result || captured.value || !scanning.value) return;

            const candidate = normalizeScan(result.getText());
            if (!candidate) return;

            if (!hasGtinLength(candidate)) {
                scannerMessage.value = `Barcode read (${candidate.length} digits), but GTIN must be 8, 12, 13 or 14 digits.`;
                return;
            }

            try { controls.stop(); } catch (_) {}
            capture(candidate);
        },
    );
}

function capture(value) {
    if (!hasGtinLength(value)) return;

    gtin.value = value;
    captured.value = true;
    scannerMessage.value = isValidGtin(value)
        ? `Barcode captured: ${value}`
        : `Barcode read: ${value}. The GTIN check digit is invalid; scan again or correct it manually.`;
    stopScanner();

    if (navigator.vibrate) navigator.vibrate(70);
}

async function scanAgain() {
    gtin.value = '';
    form.clearErrors();
    scannerMessage.value = 'Point the rear camera at the product barcode.';
    scannerError.value = '';
    captured.value = false;
    await nextTick();
    await startScanner();
}

function saveAndNext() {
    if (!activeProduct.value || !canSave.value) return;

    const currentId = activeProduct.value.id;
    form.gtin = normalizedGtin.value;
    savingNext.value = true;

    form.patch(`/admin/gtin/${activeProduct.value.slug}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            const nextProduct = props.products.find((product) => product.id !== currentId) || null;

            if (!nextProduct) {
                closeScanner();
                return;
            }

            activeProduct.value = nextProduct;
            gtin.value = '';
            form.gtin = '';
            form.clearErrors();
            scannerMessage.value = 'Saved. Scan the next product barcode.';
            scannerError.value = '';
            captured.value = false;
            await nextTick();
            await startScanner();
        },
        onFinish: () => {
            savingNext.value = false;
        },
    });
}

function manualInput(event) {
    gtin.value = String(event.target.value || '').replace(/\D/g, '').slice(0, 14);
    captured.value = false;
    form.clearErrors('gtin');
}

onBeforeUnmount(stopScanner);
</script>

<template>
    <AdminLayout>
        <Head title="GTIN products" />

        <div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="section-kicker">Catalogue identifiers</p>
                    <h1 class="section-title">GTIN products</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500 sm:text-sm">Scan the barcode on each physical product. Saving a valid GTIN removes it from this queue and opens the next product automatically.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600">
                    {{ missingCount }} product{{ missingCount === 1 ? '' : 's' }} remaining
                </div>
            </div>

            <section class="mt-4 rounded-[9px] border border-slate-200 bg-white p-3 sm:p-4">
                <div class="flex items-center justify-between gap-3 text-xs">
                    <span class="font-semibold text-slate-800">GTIN completion</span>
                    <span class="font-medium text-slate-500">{{ withGtinCount }} / {{ progressTotal }} · {{ progress }}%</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-prinx-500 transition-all" :style="{ width: `${progress}%` }"></div>
                </div>
            </section>

            <div v-if="products.length" class="mt-4">
                <div class="relative">
                    <Icon name="search-normal-1" :size="17" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" class="input h-11 pl-10 text-base sm:text-sm" placeholder="Search product, SKU, brand or category">
                </div>
            </div>

            <div v-if="!products.length" class="mt-5 rounded-[10px] border border-emerald-200 bg-emerald-50 p-6 text-center sm:p-10">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><Icon name="tick-circle" :size="24" /></div>
                <h2 class="mt-3 text-base font-semibold text-emerald-950">All products have GTINs</h2>
                <p class="mt-1 text-sm text-emerald-700">There are no products waiting for barcode entry.</p>
            </div>

            <div v-else-if="!filteredProducts.length" class="mt-5 rounded-[9px] border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                No missing-GTIN products match “{{ search }}”.
            </div>

            <div v-else class="mt-4 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                <article v-for="product in filteredProducts" :key="product.id" class="flex min-w-0 items-center gap-3 rounded-[9px] border border-slate-200 bg-white p-2.5 sm:p-3">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-[7px] border border-slate-100 bg-slate-50">
                        <img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain" loading="lazy">
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-2 text-[13px] font-semibold leading-5 text-slate-900">{{ product.name }}</p>
                        <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ product.brand?.name || 'No brand' }} · {{ product.sku }}</p>
                        <p class="mt-1 text-[10px] font-medium uppercase tracking-wide text-amber-600">GTIN missing</p>
                    </div>
                    <button type="button" class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-[7px] bg-slate-950 px-3 text-xs font-semibold text-white transition hover:bg-prinx-500" @click="openScanner(product)">
                        <Icon name="scan-barcode" :size="17" />
                        <span class="hidden min-[390px]:inline">Add</span>
                    </button>
                </article>
            </div>
        </div>

        <div v-if="modalOpen" class="fixed inset-0 z-[80] flex items-end justify-center bg-slate-950/70 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closeScanner">
            <section class="max-h-[96vh] w-full overflow-y-auto rounded-t-[12px] bg-white shadow-2xl sm:max-w-lg sm:rounded-[10px]">
                <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 py-3 sm:px-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-medium uppercase tracking-[.12em] text-prinx-600">Scan product GTIN</p>
                        <h2 class="truncate text-sm font-semibold text-slate-950">{{ activeProduct?.name }}</h2>
                    </div>
                    <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-slate-200 text-slate-600" aria-label="Close scanner" @click="closeScanner"><Icon name="close-circle" :size="20" /></button>
                </div>

                <div class="p-3 sm:p-4">
                    <div class="relative overflow-hidden rounded-[9px] bg-slate-950">
                        <video ref="video" class="aspect-[4/3] w-full object-cover" muted playsinline></video>
                        <div v-if="scanning" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="relative h-28 w-[82%] max-w-sm rounded-[8px] border-2 border-white/80">
                                <span class="absolute left-2 right-2 top-1/2 h-px bg-prinx-400 shadow-[0_0_8px_rgba(255,255,255,.6)]"></span>
                            </div>
                        </div>
                        <div v-if="!scanning && !captured" class="absolute inset-0 flex items-center justify-center bg-slate-950 px-6 text-center text-xs leading-5 text-slate-300">
                            Camera preview unavailable. You can enter the GTIN manually below.
                        </div>
                        <div v-if="captured" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/90 p-6 text-center text-white">
                            <div :class="['flex h-12 w-12 items-center justify-center rounded-full', isValidGtin(gtin) ? 'bg-emerald-500' : 'bg-amber-500']"><Icon :name="isValidGtin(gtin) ? 'tick-circle' : 'danger'" :size="25" /></div>
                            <p class="mt-3 text-xs text-slate-300">{{ isValidGtin(gtin) ? 'Barcode captured' : 'Barcode read' }}</p>
                            <strong class="mt-1 text-xl tracking-[.08em]">{{ gtin }}</strong>
                        </div>
                    </div>

                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ scannerMessage }}</p>
                    <p v-if="scannerError" class="mt-2 rounded-[7px] bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-700">{{ scannerError }}</p>

                    <div class="mt-4">
                        <label class="label">GTIN</label>
                        <input :value="gtin" inputmode="numeric" autocomplete="off" class="input h-12 text-center text-lg tracking-[.08em]" maxlength="14" placeholder="Scan or enter barcode" @input="manualInput">
                        <p class="mt-1.5 text-[11px] leading-5 text-slate-500">Accepted GTIN lengths: 8, 12, 13 or 14 digits. The check digit is verified before saving.</p>
                        <p v-if="gtin && !isValidGtin(gtin)" class="mt-1 text-xs font-medium text-amber-600">The barcode was read, but this is not a valid GTIN yet. Scan again or correct the digits.</p>
                        <p v-if="form.errors.gtin" class="error">{{ form.errors.gtin }}</p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" class="btn-light min-h-11" :disabled="scanning" @click="scanAgain"><Icon name="scan-barcode" :size="17" /> Scan again</button>
                        <button type="button" class="btn-primary min-h-11" :disabled="!canSave" @click="saveAndNext">
                            <Icon name="arrow-right" :size="17" />
                            {{ form.processing || savingNext ? 'Saving...' : 'Save & next' }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
