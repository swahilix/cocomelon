<script setup>
import axios from 'axios';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ products: Array });
const page = usePage();
const selectedProductId = ref('');
const batch = ref([]);
const labelSize = ref('50x30');
const showName = ref(true);
const showPrice = ref(true);
const showSku = ref(true);
const showUnitCode = ref(true);
const showStore = ref(false);
const showBorders = ref(true);
const renderError = ref('');

const sizes = {
    '38x25': { label: '38 × 25 mm', width: 38, height: 25, barcodeHeight: 27 },
    '40x25': { label: '40 × 25 mm', width: 40, height: 25, barcodeHeight: 27 },
    '50x30': { label: '50 × 30 mm', width: 50, height: 30, barcodeHeight: 34 },
    '60x40': { label: '60 × 40 mm', width: 60, height: 40, barcodeHeight: 44 },
};

const currentSize = computed(() => sizes[labelSize.value]);
const selectedProduct = computed(() => props.products.find((product) => Number(product.id) === Number(selectedProductId.value)) || null);
const totalLabels = computed(() => batch.value.reduce((sum, product) => sum + product.units.length, 0));
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const labels = computed(() => batch.value.flatMap((product) => product.units.map((unit) => ({
    key: unit.id,
    unit_id: unit.id,
    barcode: unit.barcode,
    unit_number: unit.unit_number,
    print_count: unit.print_count,
    last_printed_at: unit.last_printed_at,
    product_id: product.id,
    name: product.name,
    sku: product.sku,
    price: product.price,
}))));

function addProduct() {
    const product = selectedProduct.value;
    if (!product || batch.value.some((item) => Number(item.id) === Number(product.id))) return;
    batch.value.push(product);
}

function removeProduct(id) {
    batch.value = batch.value.filter((product) => Number(product.id) !== Number(id));
}

function clearBatch() {
    batch.value = [];
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
                width: 1.25,
                height: currentSize.value.barcodeHeight,
                margin: 0,
                displayValue: false,
                background: '#ffffff',
                lineColor: '#000000',
            });
        } catch (_) {
            renderError.value = 'Could not render one or more unit barcodes.';
        }
    });
}

async function printLabels() {
    if (!labels.value.length) return;
    await renderBarcodes();
    await nextTick();

    try {
        await axios.post('/admin/barcodes/printed', { unit_ids: labels.value.map((label) => label.unit_id) });
    } catch (_) {
        // Printing must still work if only the optional print-history update fails.
    }

    window.print();
}

watch([batch, labelSize], renderBarcodes, { deep: true });
onMounted(renderBarcodes);
</script>

<template>
    <AdminLayout>
        <Head title="Barcode Printing" />

        <div class="print-hidden flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Physical inventory labels</p>
                <h1 class="section-title">Unit barcode printing</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">Every physical stock unit owns one permanent barcode. If a product has 8 available units, the system prints exactly 8 different barcodes for that product.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/stock/inventory" class="btn-light"><Icon name="box" :size="15" /> Inventory</Link>
                <button type="button" class="btn-primary" :disabled="!labels.length" @click="printLabels"><Icon name="printer" :size="15" /> Print {{ totalLabels || '' }} barcode{{ totalLabels === 1 ? '' : 's' }}</button>
            </div>
        </div>

        <div class="print-hidden mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section class="card p-4">
                <div class="flex items-start gap-2"><Icon name="box" :size="20" class="text-prinx-600" /><div><h2 class="font-semibold">Choose products</h2><p class="mt-0.5 text-[11px] text-slate-500">Label quantity is locked to available stock. There is no manual copies field.</p></div></div>

                <div class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <select v-model="selectedProductId" class="input" data-placeholder="Search product / SKU">
                        <option value="">Search product / SKU</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }} · {{ product.stock_quantity }} in stock</option>
                    </select>
                    <button type="button" class="btn-primary whitespace-nowrap" :disabled="!selectedProduct || batch.some((item) => Number(item.id) === Number(selectedProductId))" @click="addProduct"><Icon name="add-circle" :size="15" /> Add product stock</button>
                </div>

                <div class="mt-4 overflow-hidden rounded-[9px] border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[760px]">
                            <thead><tr><th>Product</th><th>SKU</th><th>Available stock</th><th>Unique barcodes</th><th>Price</th><th class="w-12"></th></tr></thead>
                            <tbody>
                                <tr v-for="product in batch" :key="product.id">
                                    <td><b>{{ product.name }}</b><p class="text-[10px] text-slate-400">{{ product.brand || product.category || 'Product' }}</p></td>
                                    <td class="font-mono text-[11px]">{{ product.sku }}</td>
                                    <td><b>{{ product.stock_quantity }}</b></td>
                                    <td><span :class="product.units.length === product.stock_quantity ? 'text-emerald-600' : 'text-red-600'" class="font-semibold">{{ product.units.length }}</span><span class="text-slate-400"> unit codes</span></td>
                                    <td>{{ money(product.price) }}</td>
                                    <td><button type="button" class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50" title="Remove from print batch" @click="removeProduct(product.id)"><Icon name="trash" :size="14" /></button></td>
                                </tr>
                                <tr v-if="!batch.length"><td colspan="6" class="py-10 text-center text-slate-400">Choose a product to load all of its currently available physical-unit barcodes.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="batch.length" class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-3 py-2 text-[11px]"><span><b>{{ totalLabels }}</b> unique physical-unit labels ready to print</span><button type="button" class="font-semibold text-red-600" @click="clearBatch">Clear batch</button></div>
                </div>
            </section>

            <aside class="card p-4">
                <h2 class="font-semibold">Label settings</h2>
                <div class="mt-3 space-y-3">
                    <div><label class="label">Sticker size</label><select v-model="labelSize" class="input"><option v-for="(size,key) in sizes" :key="key" :value="key">{{ size.label }}</option></select></div>
                    <div class="space-y-2 rounded-[8px] border border-slate-200 p-3">
                        <label class="flex items-center justify-between text-xs"><span>Product name</span><input v-model="showName" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Price</span><input v-model="showPrice" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Product SKU</span><input v-model="showSku" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Unique unit code</span><input v-model="showUnitCode" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Store name</span><input v-model="showStore" type="checkbox"></label>
                        <label class="flex items-center justify-between text-xs"><span>Cutting border</span><input v-model="showBorders" type="checkbox"></label>
                    </div>
                    <div class="rounded-[8px] bg-amber-50 p-3 text-[10px] leading-4 text-amber-800"><b>Important:</b> do not copy one sticker onto several physical items. Each printed code belongs to one inventory unit and is retired from available stock when that unit is sold.</div>
                </div>
            </aside>
        </div>

        <div v-if="renderError" class="print-hidden mt-3 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ renderError }}</div>

        <section class="mt-4">
            <div class="print-hidden mb-2 flex items-center justify-between"><div><h2 class="font-semibold">Print preview</h2><p class="text-[10px] text-slate-500">Each preview card below is one different physical item.</p></div><span class="rounded-[7px] bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">{{ currentSize.label }}</span></div>

            <div v-if="labels.length" class="barcode-print-area rounded-[9px] border border-slate-200 bg-white p-3 print:border-0 print:p-0" :style="{ '--barcode-label-width': `${currentSize.width}mm`, '--barcode-label-height': `${currentSize.height}mm` }">
                <article v-for="label in labels" :key="label.key" :class="['barcode-label', showBorders ? 'barcode-label-bordered' : '']">
                    <div v-if="showStore" class="barcode-store">Prinx Electronics</div>
                    <div v-if="showName" class="barcode-product-name">{{ label.name }}</div>
                    <svg class="barcode-svg" :data-barcode-value="label.barcode"></svg>
                    <div class="barcode-meta">
                        <span v-if="showUnitCode" class="barcode-unit-code">{{ label.barcode }}</span>
                        <span v-if="showSku" class="barcode-sku">SKU {{ label.sku }}</span>
                        <b v-if="showPrice" class="barcode-price">{{ money(label.price) }}</b>
                    </div>
                </article>
            </div>
            <div v-else class="print-hidden empty-state card">Select a product to preview its unique physical-unit barcodes.</div>
        </section>
    </AdminLayout>
</template>
