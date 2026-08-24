<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Scan barcode' },
    message: { type: String, default: 'Point the rear camera at the barcode.' },
});

const emit = defineEmits(['update:open', 'scanned']);

const video = ref(null);
const scanning = ref(false);
const scannerMessage = ref(props.message);
const scannerError = ref('');

let cameraStream = null;
let detectorTimer = null;
let zxingReader = null;
let zxingControls = null;

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

function closeScanner() {
    stopScanner();
    emit('update:open', false);
}

function capture(value) {
    const barcode = String(value || '').trim();
    if (!barcode) return;

    stopScanner();
    if (navigator.vibrate) navigator.vibrate(70);
    emit('scanned', barcode);
    emit('update:open', false);
}

async function startScanner() {
    if (!props.open || !video.value) return;

    stopScanner();
    scannerMessage.value = props.message;
    scannerError.value = '';
    scanning.value = true;

    if (!window.isSecureContext) {
        scanning.value = false;
        scannerError.value = 'Camera scanning requires HTTPS. Close the scanner and enter the barcode manually.';
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        scanning.value = false;
        scannerError.value = 'This browser cannot access the camera. Close the scanner and enter the barcode manually.';
        return;
    }

    try {
        if ('BarcodeDetector' in window) {
            await startNativeDetector();
            return;
        }

        await startZxingDetector();
    } catch (error) {
        stopScanner();
        const name = error?.name || '';
        scannerError.value = name === 'NotAllowedError'
            ? 'Camera permission was denied. Allow camera access in your browser settings and try again.'
            : 'The camera could not be started. Try again or enter the barcode manually.';
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

    const wanted = [
        'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39',
        'codabar', 'itf', 'data_matrix', 'qr_code',
    ];
    let formats = wanted;

    if (typeof window.BarcodeDetector.getSupportedFormats === 'function') {
        const supported = await window.BarcodeDetector.getSupportedFormats();
        formats = wanted.filter((format) => supported.includes(format));
    }

    const detector = formats.length
        ? new window.BarcodeDetector({ formats })
        : new window.BarcodeDetector();

    const detect = async () => {
        if (!scanning.value || !props.open || !video.value) return;

        try {
            const codes = await detector.detect(video.value);
            const barcode = codes.map((code) => String(code.rawValue || '').trim()).find(Boolean);
            if (barcode) {
                capture(barcode);
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
        delayBetweenScanAttempts: 250,
        delayBetweenScanSuccess: 500,
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
            if (!result || !scanning.value) return;
            try { controls.stop(); } catch (_) {}
            capture(result.getText());
        },
    );
}

async function retryScanner() {
    scannerError.value = '';
    await nextTick();
    await startScanner();
}

watch(() => props.open, async (open) => {
    if (!open) {
        stopScanner();
        return;
    }

    scannerMessage.value = props.message;
    scannerError.value = '';
    await nextTick();
    await startScanner();
});

onBeforeUnmount(stopScanner);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[110] flex items-end justify-center bg-slate-950/75 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closeScanner">
        <section class="max-h-[96vh] w-full overflow-y-auto rounded-t-[12px] bg-white shadow-2xl sm:max-w-lg sm:rounded-[10px]">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 py-3 sm:px-4">
                <div class="min-w-0">
                    <p class="text-[10px] font-medium uppercase tracking-[.12em] text-prinx-600">Mobile scanner</p>
                    <h2 class="truncate text-sm font-semibold text-slate-950">{{ title }}</h2>
                </div>
                <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-slate-200 text-slate-600" aria-label="Close scanner" @click="closeScanner">
                    <Icon name="close-circle" :size="20" />
                </button>
            </div>

            <div class="p-3 sm:p-4">
                <div class="relative overflow-hidden rounded-[9px] bg-slate-950">
                    <video ref="video" class="aspect-[4/3] w-full object-cover" muted playsinline></video>
                    <div v-if="scanning" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative h-28 w-[82%] max-w-sm rounded-[8px] border-2 border-white/80">
                            <span class="absolute left-2 right-2 top-1/2 h-px bg-prinx-400 shadow-[0_0_8px_rgba(255,255,255,.6)]"></span>
                        </div>
                    </div>
                    <div v-if="!scanning" class="absolute inset-0 flex items-center justify-center bg-slate-950 px-6 text-center text-xs leading-5 text-slate-300">
                        Camera preview unavailable.
                    </div>
                </div>

                <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <div class="flex items-start gap-2">
                        <Icon name="scan-barcode" :size="18" class="mt-0.5 shrink-0 text-prinx-600" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800">{{ scannerMessage }}</p>
                            <p class="mt-1 text-[11px] leading-4 text-slate-500">Hold the phone steady and keep the barcode inside the frame.</p>
                        </div>
                    </div>
                </div>

                <div v-if="scannerError" class="mt-3 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2.5 text-xs leading-5 text-red-700">
                    {{ scannerError }}
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="flex h-10 items-center justify-center gap-1.5 rounded-[8px] border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="closeScanner">
                        <Icon name="close-circle" :size="16" />Close
                    </button>
                    <button type="button" class="flex h-10 items-center justify-center gap-1.5 rounded-[8px] bg-prinx-500 text-xs font-semibold text-white hover:bg-prinx-600" @click="retryScanner">
                        <Icon name="refresh-2" :size="16" />Restart camera
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
