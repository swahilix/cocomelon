<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ products: Array });
const page = usePage();
const selectedProductId = ref('');
const copies = ref(1);
const lines = ref([]);
const labelSize = ref('50x30');
const showName = ref(true);
const showPrice = ref(true);
const showSku = ref(true);
const showStore = ref(false);
const showBorders = ref(true);
const renderError = ref('');

const sizes = {
    '38x25': { label: '38 × 25 mm', width: 38, height: 25, barcodeHeight: 28 },
    '40x25': { label: '40 × 25 mm', width: 40, height: 25, barcodeHeight: 28 },
    '50x30': { label: '50 × 30 mm', width: 50, height: 30, barcodeHeight: 34 },
    '60x40': { label: '60 × 40 mm', width: 60, height: 40, barcodeHeight: 44 },
};

const currentSize = computed(() => sizes[labelSize.value]);
const selectedProduct = computed(() => props.products.find((product) => Number(product.id) === Number(selectedProductId.value)) || null);
const totalLabels = computed(() => lines.value.reduce((sum, line) => sum + Number(line.copies || 0), 0));
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const expandedLabels = computed(() => {
    const labels = [];
    lines.value.forEach((line) => {
        const count = Math.max(1, Math.min(500, Number(line.copies || 1)));
        for (let index = 0; index < count; index++) {
            labels.push({ ...line, key: `${line.id}-${index}` });
        }
    });
    return labels.slice(0, 500);
});

function addProduct() {
    const product = selectedProduct.value;
    if (!product) return;

    const count = Math.max(1, Math.min(500, Number(copies.value || 1)));
    const existing = lines.value.find((line) => Number(line.id) === Number(product.id));
    if (existing) {
        existing.copies = Math.min(500, Number(existing.copies || 0) + count);
    } else {
        lines.value.push({ ...product, copies: count });
    }
    copies.value = 1;
}

function removeProduct(id) {
    lines.value = lines.value.filter((line) => Number(line.id) !== Number(id));
}

function clearBatch() {
    lines.value = [];
}

function useStockQuantity(line) {
    line.copies = line.track_stock ? Math.max(1, Math.min(500, Number(line.stock_quantity || 1))) : 1;
}

async function renderBarcodes() {
    await nextTick();
    renderError.value = '';

    if (!window.JsBarcode) {
        renderError.value = 'Barcode renderer is unavailable. Reload the page and try again.';
        return;
    }

    document.querySelectorAll('.barcode-svg[data-barcode-value]').forEach((svg) => {
        const value = svg.dataset.barcodeValue || '';
        if (!value) return;

        try {
            window.JsBarcode(svg, value, {
                format: 'CODE128',
                width: 1.35,
                height: currentSize.value.barcodeHeight,
                margin: 0,
                displayValue: false,
                background: '#ffffff',
                lineColor: '#000000',
            });
        } catch (_) {
            renderError.value = `Could not render one or more SKU barcodes. Check that the SKU contains printable characters.`;
        }
    });
}

async function printLabels() {
    if (!expandedLabels.value.length) return;
    await renderBarcodes();
    await nextTick();
    window.print();
}

watch([lines, labelSize], renderBarcodes, { deep: true });
onMounted(renderBarcodes);
</script>

<template>
    <AdminLayout>
        <Head title="Barcode Printing" />

        <div class="print-hidden flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Inventory labels</p>
                <h1 class="section-title">Barcode printing</h1>
                <p class="mt-1 text-xs text-slate-500">Create Code 128 labels from product SKUs. These labels scan directly into the POS item search.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/stock/inventory" class="btn-light"><Icon name="box" :size="15" /> Inventory</Link>
                <button type="button" class="btn-primary" :disabled="!expandedLabels.length" @click="printLabels"><Icon name="printer" :size="15" /> Print {{ totalLabels || '' }} label{{ totalLabels === 1 ? '' : 's' }}</button>
            </div>
        </div>

        <div class="print-hidden mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section class="card p-4">
                <div class="flex items-start gap-2"><Icon name="barcode" :size="20" class="text-prinx-600" /><div><h2 class="font-semibold">Build label batch</h2><p class="mt-0.5 text-[11px] text-slate-500">Select a product and enter how many stickers you need.</p></div></div>

                <div class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_110px_auto]">
                    <select v-model="selectedProductId" class="input" data-placeholder="Search product / SKU">
                        <option value="">Search product / SKU</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }}</option>
                    </select>
                    <input v-model.number="copies" type="number" min="1" max="500" class="input" placeholder="Copies">
                    <button type="button" class="btn-primary whitespace-nowrap" :disabled="!selectedProduct" @click="addProduct"><Icon name="add-circle" :size="15" /> Add labels</button>
                </div>

                <div class="mt-4 overflow-hidden rounded-[9px] border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[720px]">
                            <thead><tr><th>Product</th><th>SKU / barcode</th><th>Price</th><th>Stock</th><th class="w-32">Copies</th><th class="w-24"></th></tr></thead>
                            <tbody>
                                <tr v-for="line in lines" :key="line.id">
                                    <td><b>{{ line.name }}</b><p class="text-[10px] text-slate-400">{{ line.brand || line.category || 'Product' }}</p></td>
                                    <td class="font-mono text-[11px]">{{ line.sku }}</td>
                                    <td>{{ money(line.price) }}</td>
                                    <td>{{ line.track_stock ? line.stock_quantity : 'Not tracked' }}</td>
                                    <td><input v-model.number="line.copies" type="number" min="1" max="500" class="input h-8 py-1"></td>
                                    <td><div class="flex items-center gap-1"><button v-if="line.track_stock && line.stock_quantity > 0" type="button" class="rounded-[6px] border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600" title="Set copies to current stock" @click="useStockQuantity(line)">Stock</button><button type="button" class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50" title="Remove" @click="removeProduct(line.id)"><Icon name="trash" :size="14" /></button></div></td>
                                </tr>
                                <tr v-if="!lines.length"><td colspan="6" class="py-10 text-center text-slate-400">No products added to this print batch yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="lines.length" class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-3 py-2 text-[11px]"><span><b>{{ totalLabels }}</b> labels in batch <span v-if="totalLabels > 500" class="text-amber-600">· preview/print is limited to 500 at a time</span></span><button type="button" class="font-semibold text-red-600" @click="clearBatch">Clear batch</button></div>
                </div>
            </section>

            <aside class="card p-4">
                <h2 class="font-semibold">Label settings</h2>
                <div class="mt-3 space-y-3">
                    <div><label class="label">Sticker size</label><select v-model="labelSize" class="input"><option v-for="(size,key) in sizes" :key="key" :value="key">{{ size.label }}</option></select></div>
                    <div class="space-y-2 rounded-[8px] border border-slate-200 p-3">
                        <label class="flex items-center justify-between text-xs"><span>Product name</span><input v-model="showName" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Price</span><input v-model="showPrice" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>SKU text</span><input v-model="showSku" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Store name</span><input v-model="showStore" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Cutting border</span><input v-model="showBorders" type="checkbox"></label>
                    </div>
                    <p class="text-[10px] leading-4 text-slate-500">For thermal sticker printers, select the matching paper size in the browser print dialog and set margins to none/default as appropriate for the printer.</p>
                </div>
            </aside>
        </div>

        <div v-if="renderError" class="print-hidden mt-3 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ renderError }}</div>

        <section class="mt-4">
            <div class="print-hidden mb-2 flex items-center justify-between"><div><h2 class="font-semibold">Print preview</h2><p class="text-[10px] text-slate-500">Actual dimensions are preserved in print output.</p></div><span class="rounded-[7px] bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">{{ currentSize.label }}</span></div>

            <div v-if="expandedLabels.length" class="barcode-print-area rounded-[9px] border border-slate-200 bg-white p-3 print:border-0 print:p-0" :style="{ '--barcode-label-width': `${currentSize.width}mm`, '--barcode-label-height': `${currentSize.height}mm` }">
                <article v-for="label in expandedLabels" :key="label.key" :class="['barcode-label', showBorders ? 'barcode-label-bordered' : '']">
                    <div v-if="showStore" class="barcode-store">Prinx Electronics</div>
                    <div v-if="showName" class="barcode-product-name">{{ label.name }}</div>
                    <svg class="barcode-svg" :data-barcode-value="label.sku"></svg>
                    <div class="barcode-meta">
                        <span v-if="showSku" class="barcode-sku">{{ label.sku }}</span>
                        <b v-if="showPrice" class="barcode-price">{{ money(label.price) }}</b>
                    </div>
                </article>
            </div>
            <div v-else class="print-hidden empty-state card">Add at least one product to preview barcode labels.</div>
        </section>
    </AdminLayout>
</template>
