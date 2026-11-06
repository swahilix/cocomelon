<script setup>
import axios from 'axios';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import PosLayout from '../../../Layouts/PosLayout.vue';
import Icon from '../../../Components/Icon.vue';
import MobileBarcodeScanner from '../../../Components/MobileBarcodeScanner.vue';

const props = defineProps({
    products: Array,
    categories: Array,
    brands: Array,
    customers: Array,
    heldSales: Array,
    resumeHold: Object,
    currentRegister: Object,
    paymentMethods: Array,
});

const page = usePage();
const scanSearch = ref('');
const scanError = ref('');
const mobileScannerOpen = ref(false);
const documentError = ref('');
const documentProcessing = ref(false);
const catalogSearch = ref('');
const category = ref('');
const brand = ref('');
const showHeld = ref(false);
const showNewCustomer = ref(false);
const customers = ref([...props.customers]);
const customerSaving = ref(false);
const customerError = ref('');
const customerDraft = ref({ name: '', phone: '', email: '', tin: '' });
const permissions = computed(() => page.props.auth.permissions || []);
const canDiscount = computed(() => permissions.value.includes('discount_pos'));
const canOverridePrice = computed(() => permissions.value.includes('override_pos_price'));
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(Number(value || 0));
const newOperationId = () => globalThis.crypto?.randomUUID?.() || `pos-${Date.now()}-${Math.random().toString(36).slice(2)}`;
const cashMethod = computed(() => props.paymentMethods.find((method) => Boolean(method.is_cash)) || props.paymentMethods[0] || null);

const resumeItems = Array.isArray(props.resumeHold?.cart) ? props.resumeHold.cart : [];
const initialPaymentMethodId = props.resumeHold?.payment_method_id || cashMethod.value?.id || '';
const form = useForm({
    _operation_id: newOperationId(),
    hold_id: props.resumeHold?.id || '',
    customer_id: props.resumeHold?.customer_id || '',
    customer_name: props.resumeHold?.customer_name || '',
    customer_phone: props.resumeHold?.customer_phone || '',
    customer_email: '',
    customer_tin: '',
    discount: canDiscount.value ? Number(props.resumeHold?.discount || 0) : 0,
    amount_paid: '',
    payment_method_id: initialPaymentMethodId,
    payment_reference: props.resumeHold?.payment_reference || '',
    notes: props.resumeHold?.notes || '',
    items: resumeItems.map((item) => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
        unit_barcodes: Array.isArray(item.unit_barcodes) ? [...item.unit_barcodes] : [],
    })),
});

const selectedPaymentMethod = computed(() => props.paymentMethods.find((method) => Number(method.id) === Number(form.payment_method_id)) || null);
const isCashPayment = computed(() => Boolean(selectedPaymentMethod.value?.is_cash));
const filteredProducts = computed(() => props.products.filter((item) => {
    const q = catalogSearch.value.trim().toLowerCase();
    if (category.value && item.category !== category.value) return false;
    if (brand.value && item.brand !== brand.value) return false;
    return !q || `${item.name} ${item.sku} ${item.category || ''} ${item.brand || ''}`.toLowerCase().includes(q);
}));
const subtotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_price), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));
// An empty "Amount paid" means the customer pays the exact total.
const paidAmount = computed(() => (form.amount_paid === '' || form.amount_paid === null ? total.value : Number(form.amount_paid || 0)));
const change = computed(() => isCashPayment.value ? Math.max(0, paidAmount.value - total.value) : 0);
const shortBy = computed(() => Math.max(0, total.value - paidAmount.value));
// Exact total plus the next round cash amounts a customer is likely to hand over.
const quickAmounts = computed(() => {
    const amount = Math.ceil(total.value);
    const values = new Set([amount]);
    [1000, 5000, 10000, 50000].forEach((step) => {
        const rounded = Math.ceil(amount / step) * step;
        if (rounded > amount) values.add(rounded);
    });
    return [...values].sort((a, b) => a - b).slice(0, 4);
});
const showPay = ref(false);
const payAmountInput = ref(null);
const canCheckout = computed(() => !showNewCustomer.value && form.items.length > 0 && Boolean(props.currentRegister) && !form.processing);
const paymentErrorKeys = ['amount_paid', 'payment_method_id', 'payment_reference', 'notes'];
const paymentErrors = computed(() => paymentErrorKeys.map((key) => form.errors[key]).filter(Boolean));
const cartCount = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

function product(id) {
    return props.products.find((item) => Number(item.id) === Number(id));
}

function barcodeAlreadyInCart(barcode) {
    return form.items.some((line) => (line.unit_barcodes || []).some((code) => String(code).toUpperCase() === String(barcode).toUpperCase()));
}

function addProduct(item, unitBarcode = null) {
    if (showNewCustomer.value || !item?.available) return;
    scanError.value = '';

    if (unitBarcode && barcodeAlreadyInCart(unitBarcode)) {
        scanError.value = `Unit ${unitBarcode} is already in this sale.`;
        return;
    }

    const existing = form.items.find((line) => Number(line.product_id) === Number(item.id));
    if (existing) {
        if (item.track_stock && existing.quantity >= item.stock_quantity) {
            scanError.value = `No more available stock for ${item.name}.`;
            return;
        }
        existing.quantity++;
        if (unitBarcode) existing.unit_barcodes.push(unitBarcode);
        return;
    }

    form.items.push({
        product_id: item.id,
        quantity: 1,
        unit_price: Number(item.price),
        unit_barcodes: unitBarcode ? [unitBarcode] : [],
    });
}

function adjustQty(line, delta) {
    const item = product(line.product_id);
    const next = Number(line.quantity) + delta;
    if (next <= 0) return removeLine(line.product_id);
    if (item?.track_stock && next > item.stock_quantity) return;

    if (delta < 0 && Array.isArray(line.unit_barcodes) && line.unit_barcodes.length > next) {
        line.unit_barcodes.splice(next);
    }
    line.quantity = next;
}

function removeLine(id) {
    form.items = form.items.filter((line) => Number(line.product_id) !== Number(id));
}

async function scanEnter() {
    if (showNewCustomer.value) return;
    const raw = scanSearch.value.trim();
    if (!raw) return;
    scanError.value = '';

    if (raw.toUpperCase().startsWith('PX-')) {
        try {
            const { data } = await axios.get(`/admin/pos/unit/${encodeURIComponent(raw.toUpperCase())}`);
            const item = props.products.find((product) => Number(product.id) === Number(data.product.id));
            if (!item) {
                scanError.value = 'The scanned unit belongs to a product that is not currently sellable.';
                return;
            }
            addProduct(item, data.unit.barcode);
            if (!scanError.value) scanSearch.value = '';
        } catch (error) {
            scanError.value = error?.response?.data?.message || 'This unit barcode is unavailable or invalid.';
        }
        return;
    }

    const q = raw.toLowerCase();
    const exact = props.products.find((item) => String(item.sku || '').toLowerCase() === q)
        || props.products.find((item) => String(item.gtin || '').toLowerCase() === q)
        || props.products.find((item) => String(item.name || '').toLowerCase() === q);
    if (exact) {
        addProduct(exact);
        scanSearch.value = '';
    } else {
        scanError.value = 'No item, SKU, GTIN or unit barcode matched that scan.';
    }
}

async function handleMobileScan(barcode) {
    const value = String(barcode || '').trim();
    if (!value || showNewCustomer.value) return;

    scanSearch.value = value;
    scanError.value = '';
    await scanEnter();
}

function selectCustomer() {
    const customer = customers.value.find((item) => Number(item.id) === Number(form.customer_id));
    if (customer) {
        form.customer_name = customer.name;
        form.customer_phone = customer.phone || '';
        form.customer_email = customer.email || '';
        form.customer_tin = customer.tin || '';
        showNewCustomer.value = false;
    } else {
        form.customer_name = '';
        form.customer_phone = '';
        form.customer_email = '';
        form.customer_tin = '';
    }
}

function openNewCustomer() {
    customerDraft.value = { name: '', phone: '', email: '', tin: '' };
    customerError.value = '';
    showNewCustomer.value = true;
}

function cancelNewCustomer() {
    customerDraft.value = { name: '', phone: '', email: '', tin: '' };
    customerError.value = '';
    showNewCustomer.value = false;
}

async function saveCustomer() {
    if (customerSaving.value) return;

    const name = customerDraft.value.name.trim();
    if (!name) {
        customerError.value = 'Customer name is required.';
        return;
    }

    customerSaving.value = true;
    customerError.value = '';

    try {
        const { data } = await axios.post('/admin/pos/customers', {
            name,
            phone: customerDraft.value.phone || null,
            email: customerDraft.value.email || null,
            tin: customerDraft.value.tin || null,
        });
        const customer = data.customer;
        customers.value.push(customer);
        customers.value.sort((a, b) => String(a.name).localeCompare(String(b.name)));
        form.customer_id = customer.id;
        form.customer_name = customer.name;
        form.customer_phone = customer.phone || '';
        form.customer_email = customer.email || '';
        form.customer_tin = customer.tin || '';
        customerDraft.value = { name: '', phone: '', email: '', tin: '' };
        showNewCustomer.value = false;
    } catch (error) {
        const errors = error?.response?.data?.errors || {};
        customerError.value = Object.values(errors)[0]?.[0]
            || error?.response?.data?.message
            || 'Unable to save this customer.';
    } finally {
        customerSaving.value = false;
    }
}

function completeSale() {
    if (!canCheckout.value) return;
    if (!form.payment_method_id) {
        openPay();
        return;
    }
    if (shortBy.value > 0) {
        openPay();
        return;
    }
    if (form.amount_paid === '' || form.amount_paid === null) form.amount_paid = total.value;
    form.post('/admin/pos', {
        preserveScroll: true,
        onSuccess: () => { showPay.value = false; },
        onError: (errors) => {
            form._operation_id = newOperationId();
            if (Object.keys(errors).some((key) => paymentErrorKeys.includes(key))) showPay.value = true;
        },
    });
}

function openPay() {
    if (!canCheckout.value) return;
    if (!form.payment_method_id) form.payment_method_id = cashMethod.value?.id || '';
    showPay.value = true;
    nextTick(() => {
        payAmountInput.value?.focus();
        payAmountInput.value?.select();
    });
}

function closePay() {
    if (form.processing) return;
    showPay.value = false;
}

function choosePaymentMethod(method) {
    form.payment_method_id = method.id;
    if (!method.is_cash) form.amount_paid = '';
    nextTick(() => payAmountInput.value?.focus());
}

function onKeydown(event) {
    if (event.key === 'Escape' && showPay.value) closePay();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

function createDocument(type) {
    if (showNewCustomer.value || !form.items.length || documentProcessing.value) return;
    documentError.value = '';
    documentProcessing.value = true;

    router.post(`/admin/pos/documents/${type}`, {
        customer_id: form.customer_id || null,
        customer_name: form.customer_name || null,
        customer_phone: form.customer_phone || null,
        customer_email: form.customer_email || null,
        customer_tin: form.customer_tin || null,
        discount: Number(form.discount || 0),
        notes: form.notes || null,
        items: form.items.map((item) => ({
            product_id: Number(item.product_id),
            quantity: Number(item.quantity),
            unit_price: Number(item.unit_price),
        })),
    }, {
        preserveScroll: true,
        onError: (errors) => { documentError.value = errors.document || 'Unable to create this document.'; },
        onFinish: () => { documentProcessing.value = false; },
    });
}

function holdSale() {
    if (showNewCustomer.value || !form.items.length || !props.currentRegister || form.processing) return;
    form.post('/admin/pos/hold', { preserveScroll: true });
}

function clearSale() {
    form.reset();
    form.hold_id = '';
    form.items = [];
    form.customer_id = '';
    form.customer_name = '';
    form.customer_phone = '';
    form.customer_email = '';
    form.customer_tin = '';
    form.payment_method_id = cashMethod.value?.id || '';
    form.payment_reference = '';
    form.amount_paid = '';
    form.discount = 0;
    form._operation_id = newOperationId();
    showPay.value = false;
    scanSearch.value = '';
    scanError.value = '';
    documentError.value = '';
    customerError.value = '';
    customerDraft.value = { name: '', phone: '', email: '', tin: '' };
    showNewCustomer.value = false;
}
</script>

<template>
    <PosLayout>
        <Head title="Point of Sale" />

        <template #header-actions>
            <Link href="/admin/pos/documents" class="flex h-9 items-center gap-1.5 rounded-[7px] px-2.5 text-xs font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white"><Icon name="document-text" :size="16" /><span class="hidden sm:inline">Documents</span></Link>
            <button type="button" class="flex h-9 items-center gap-1.5 rounded-[7px] px-2.5 text-xs font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white" @click="showHeld = true">
                <Icon name="pause-circle" :size="16" />
                <span class="hidden sm:inline">Held</span>
                <span v-if="heldSales.length" class="rounded-full bg-red-500 px-1.5 py-0.5 text-[9px] font-semibold text-white">{{ heldSales.length }}</span>
            </button>
        </template>

        <div v-if="form.errors.pos" class="mb-2 flex items-center gap-2 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
            <Icon name="danger" :size="16" class="shrink-0" />{{ form.errors.pos }}
        </div>
        <div v-if="documentError" class="mb-2 flex items-center gap-2 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
            <Icon name="danger" :size="16" class="shrink-0" />{{ documentError }}
        </div>
        <div v-if="resumeHold" class="mb-2 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
            Resuming held sale {{ resumeHold.reference }}.
        </div>

        <div class="grid gap-2 lg:h-[calc(100vh-82px)] lg:min-h-[620px] lg:grid-cols-[minmax(0,7fr)_minmax(360px,5fr)]">
            <section class="order-2 flex min-h-[620px] min-w-0 flex-col overflow-hidden rounded-[9px] border border-slate-200 bg-white shadow-sm lg:order-1 lg:min-h-0">
                <div class="flex h-11 shrink-0 items-center justify-between border-b border-slate-200 px-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-[7px] bg-prinx-50 text-prinx-600"><Icon name="shopping-cart" :size="16" /></span>
                        <div><h1 class="text-sm font-semibold text-slate-950">Sales invoice</h1><p class="text-[10px] text-slate-400">{{ cartCount }} unit{{ cartCount === 1 ? '' : 's' }} in current sale</p></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="!currentRegister" href="/admin/registers" class="rounded-[7px] bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">Open register</Link>
                        <span v-else class="inline-flex items-center gap-1 rounded-[7px] bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ currentRegister.reference }}</span>
                        <button v-if="form.items.length" type="button" class="rounded-[7px] px-2 py-1 text-[10px] font-semibold text-red-600 hover:bg-red-50" @click="clearSale">Clear</button>
                    </div>
                </div>

                <div class="shrink-0 border-b border-slate-200 p-2.5">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="pos-customer-group flex min-w-0">
                            <select v-model="form.customer_id" class="pos-customer-select min-w-0" data-placeholder="Walk-in customer" :disabled="showNewCustomer" @change="selectCustomer">
                                <option value="">Walk-in customer</option>
                                <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` · ${customer.phone}` : '' }}</option>
                            </select>
                            <button type="button" class="pos-customer-add flex h-9 w-9 shrink-0 items-center justify-center border border-l-0 border-slate-300 bg-white text-prinx-600 transition hover:bg-prinx-50 disabled:cursor-not-allowed disabled:opacity-50" title="New customer" aria-label="New customer" :disabled="showNewCustomer" @click="openNewCustomer"><Icon name="add-circle" :size="18" /></button>
                        </div>

                        <div>
                            <div class="flex min-w-0">
                                <input v-model="scanSearch" class="h-9 min-w-0 flex-1 rounded-l-[8px] border border-r-0 border-slate-300 bg-white px-3 text-xs outline-none placeholder:text-slate-400 focus:border-prinx-500 disabled:cursor-not-allowed disabled:bg-slate-100 md:rounded-[8px] md:border-r" placeholder="Unit barcode / SKU / GTIN / item name" :disabled="showNewCustomer" autofocus @keyup.enter="scanEnter">
                                <button type="button" class="flex h-9 w-10 shrink-0 items-center justify-center rounded-r-[8px] border border-slate-300 bg-slate-950 text-white transition hover:bg-prinx-500 disabled:cursor-not-allowed disabled:opacity-50 md:hidden" title="Scan barcode with camera" aria-label="Scan barcode with camera" :disabled="showNewCustomer" @click="mobileScannerOpen = true">
                                    <Icon name="scan-barcode" :size="18" />
                                </button>
                            </div>
                            <p v-if="scanError" class="mt-1 text-[10px] font-medium text-red-600">{{ scanError }}</p>
                        </div>
                    </div>

                    <form v-if="showNewCustomer" class="mt-2 grid gap-2 rounded-[8px] border border-slate-200 bg-slate-50 p-2 sm:grid-cols-2 xl:grid-cols-5" @submit.prevent="saveCustomer">
                        <input v-model="customerDraft.name" class="input" placeholder="Customer name *" autofocus>
                        <input v-model="customerDraft.phone" class="input" placeholder="Phone">
                        <input v-model="customerDraft.email" class="input" placeholder="Email">
                        <input v-model="customerDraft.tin" class="input" placeholder="TIN (optional)">
                        <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
                            <button type="button" class="flex h-9 flex-1 items-center justify-center gap-1 rounded-[7px] border border-slate-300 bg-white px-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-100" :disabled="customerSaving" @click="cancelNewCustomer"><Icon name="close-circle" :size="14" />Cancel</button>
                            <button type="submit" class="flex h-9 flex-1 items-center justify-center gap-1 rounded-[7px] bg-prinx-500 px-2 text-[11px] font-semibold text-white hover:bg-prinx-600 disabled:opacity-50" :disabled="customerSaving"><Icon :name="customerSaving ? 'refresh-2' : 'tick-circle'" :size="14" />{{ customerSaving ? 'Saving...' : 'Save customer' }}</button>
                        </div>
                        <p v-if="customerError" class="text-[10px] font-medium text-red-600 sm:col-span-2 xl:col-span-5">{{ customerError }}</p>
                    </form>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full min-w-[720px] border-collapse text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-950 text-white">
                            <tr><th class="px-3 py-2 text-left font-semibold">Item</th><th class="w-20 px-2 py-2 text-center font-semibold">Stock</th><th class="w-32 px-2 py-2 text-center font-semibold">Quantity</th><th class="w-32 px-2 py-2 text-right font-semibold">Price</th><th class="w-32 px-2 py-2 text-right font-semibold">Subtotal</th><th class="w-10 px-2 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="line in form.items" :key="line.product_id" class="hover:bg-slate-50">
                                <td class="px-3 py-2.5"><p class="font-semibold text-slate-900">{{ product(line.product_id)?.name }}</p><p class="mt-0.5 text-[10px] text-slate-400">{{ product(line.product_id)?.sku }}<span v-if="line.unit_barcodes?.length" class="ml-1 font-semibold text-emerald-600">· {{ line.unit_barcodes.length }} unit barcode{{ line.unit_barcodes.length === 1 ? '' : 's' }} scanned</span></p></td>
                                <td class="px-2 py-2.5 text-center text-slate-500">{{ product(line.product_id)?.track_stock ? product(line.product_id)?.stock_quantity : '∞' }}</td>
                                <td class="px-2 py-2.5"><div class="mx-auto flex w-fit items-center overflow-hidden rounded-[7px] border border-slate-200"><button type="button" class="h-8 w-8 text-base text-red-500 hover:bg-red-50" @click="adjustQty(line, -1)">−</button><span class="min-w-9 px-1 text-center font-semibold">{{ line.quantity }}</span><button type="button" class="h-8 w-8 text-base text-emerald-600 hover:bg-emerald-50" @click="adjustQty(line, 1)">+</button></div></td>
                                <td class="px-2 py-2.5 text-right"><input v-model.number="line.unit_price" type="number" min="0" step="0.01" :disabled="!canOverridePrice" class="h-8 w-28 rounded-[7px] border border-slate-200 px-2 text-right text-xs outline-none focus:border-prinx-500 disabled:bg-transparent disabled:text-slate-700"></td>
                                <td class="px-2 py-2.5 text-right font-semibold text-slate-900">{{ money(line.quantity * line.unit_price) }}</td>
                                <td class="px-2 py-2.5 text-center"><button type="button" class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-500 hover:bg-red-50" @click="removeLine(line.product_id)"><Icon name="trash" :size="15" /></button></td>
                            </tr>
                            <tr v-if="!form.items.length"><td colspan="6" class="py-16 text-center"><Icon name="shopping-cart" :size="28" class="mx-auto text-slate-300" /><p class="mt-2 text-sm font-semibold text-slate-500">Invoice is empty</p><p class="mt-1 text-xs text-slate-400">Scan a unit barcode or choose an item from the product panel.</p></td></tr>
                        </tbody>
                    </table>
                </div>

                <form id="pos-checkout-form" class="shrink-0 border-t border-slate-200 bg-slate-50/80" @submit.prevent="completeSale">
                    <div class="grid grid-cols-2 divide-x divide-y divide-slate-200 border-b border-slate-200 sm:grid-cols-4 sm:divide-y-0">
                        <div class="px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Quantity</p><b class="mt-0.5 block text-base text-slate-800">{{ cartCount }}</b></div>
                        <div class="px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Total amount</p><b class="mt-0.5 block text-base text-slate-800">{{ money(subtotal) }}</b></div>
                        <div class="px-3 py-2 text-right"><div class="flex items-center justify-end gap-1"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Discount</p><Icon name="edit-2" :size="11" class="text-prinx-500" /></div><input v-model.number="form.discount" type="number" min="0" :max="subtotal" :disabled="!canDiscount" class="mt-0.5 h-6 w-24 rounded-[5px] border border-slate-200 bg-white px-1.5 text-right text-sm font-semibold outline-none disabled:border-transparent disabled:bg-transparent"></div>
                        <div class="px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-prinx-600">Grand total</p><b class="mt-0.5 block text-lg text-prinx-600">{{ money(total) }}</b></div>
                    </div>

                    <div class="grid grid-cols-5 gap-1.5 p-2">
                        <button type="button" class="flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-[5px] px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45 border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50" :disabled="!canCheckout" title="Hold this sale and serve the next customer" @click="holdSale"><Icon name="pause-circle" :size="15" class="shrink-0 text-amber-600 max-[420px]:hidden" /><span class="truncate">Hold</span></button>
                        <button type="button" class="flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-[5px] px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45 border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50" :disabled="showNewCustomer || !form.items.length || documentProcessing" title="Generate a quotation" @click="createDocument('quotation')"><Icon name="document-text" :size="15" class="shrink-0 text-blue-600 max-[420px]:hidden" /><span class="truncate">Quote</span></button>
                        <button type="button" class="flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-[5px] px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45 border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50" :disabled="showNewCustomer || !form.items.length || documentProcessing" title="Generate an invoice" @click="createDocument('invoice')"><Icon name="receipt-item" :size="15" class="shrink-0 text-prinx-600 max-[420px]:hidden" /><span class="truncate">Invoice</span></button>
                        <button type="button" class="flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-[5px] px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45 bg-slate-900 text-white hover:bg-slate-800" :disabled="!canCheckout" title="Choose payment method, amount and note" @click="openPay"><Icon name="wallet" :size="15" class="shrink-0 max-[420px]:hidden" /><span class="truncate">Pay</span></button>
                        <button type="submit" class="flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-[5px] px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45 bg-prinx-500 text-white hover:bg-prinx-600" :disabled="!canCheckout" :title="`Complete now: ${selectedPaymentMethod?.name || 'Cash'}, ${money(paidAmount)}`"><Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" class="shrink-0 max-[420px]:hidden" /><span class="truncate">{{ form.processing ? 'Saving…' : 'Complete' }}</span></button>
                    </div>
                    <p v-if="form.items.length && (form.payment_method_id !== cashMethod?.id || form.amount_paid !== '' || form.notes)" class="-mt-1 px-2.5 pb-2 text-[10px] text-slate-500">
                        Payment: <b class="font-semibold text-slate-700">{{ selectedPaymentMethod?.name || 'Not set' }}</b> · {{ money(paidAmount) }}<template v-if="change"> · change {{ money(change) }}</template><template v-if="form.notes"> · note added</template>
                        <button type="button" class="ml-1 font-semibold text-prinx-600 hover:underline" @click="openPay">Edit</button>
                    </p>
                </form>
            </section>

            <aside class="order-1 flex min-h-[470px] min-w-0 flex-col overflow-hidden rounded-[9px] border border-slate-200 bg-white shadow-sm lg:order-2 lg:min-h-0">
                <div class="shrink-0 border-b border-slate-200 p-2.5">
                    <div class="grid grid-cols-2 gap-2"><select v-model="category" class="input"><option value="">All categories</option><option v-for="item in categories" :key="item" :value="item">{{ item }}</option></select><select v-model="brand" class="input"><option value="">All brands</option><option v-for="item in brands" :key="item" :value="item">{{ item }}</option></select></div>
                    <div class="relative mt-2"><Icon name="search-normal" :size="16" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="catalogSearch" class="input pl-8" placeholder="Item name / SKU"></div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2.5">
                    <div class="grid grid-cols-2 gap-2 2xl:grid-cols-3">
                        <button v-for="item in filteredProducts" :key="item.id" type="button" :disabled="showNewCustomer || !item.available" class="group min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-white text-left transition hover:border-prinx-300 hover:shadow-sm active:scale-[.99] disabled:cursor-not-allowed disabled:opacity-45" @click="addProduct(item)">
                            <div class="flex gap-2 p-2"><img :src="item.image_url" :alt="item.name" class="h-[62px] w-[62px] shrink-0 rounded-[7px] bg-slate-100 object-cover"><div class="min-w-0 flex-1"><p class="line-clamp-2 text-[11px] font-semibold leading-4 text-slate-900">{{ item.name }}</p><p class="mt-0.5 truncate text-[9px] text-slate-400">{{ item.sku }}</p><p class="mt-1 text-xs font-semibold text-prinx-600">{{ money(item.price) }}</p></div></div>
                            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-2 py-1.5"><span class="truncate text-[9px] text-slate-400">{{ item.brand || item.category }}</span><span :class="['shrink-0 text-[9px] font-semibold', item.available ? 'text-slate-500' : 'text-red-600']">{{ item.track_stock ? `${item.stock_quantity} left` : 'In stock' }}</span></div>
                        </button>
                    </div>
                    <div v-if="!filteredProducts.length" class="flex min-h-48 flex-col items-center justify-center text-center"><Icon name="search-normal" :size="28" class="text-slate-300" /><p class="mt-2 text-sm font-semibold text-slate-500">No matching items</p><p class="mt-1 text-xs text-slate-400">Change the category, brand or search term.</p></div>
                </div>
            </aside>
        </div>

        <div v-if="showPay" class="fixed inset-0 z-[95] flex items-end justify-center bg-slate-950/50 sm:items-center sm:p-4" @click.self="closePay">
            <form class="flex max-h-[100dvh] w-full max-w-[440px] flex-col overflow-hidden rounded-t-[8px] bg-white shadow-2xl sm:max-h-[calc(100dvh-2rem)] sm:rounded-[8px]" role="dialog" aria-modal="true" aria-labelledby="pos-pay-title" @submit.prevent="completeSale">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-2.5">
                    <div class="min-w-0">
                        <h2 id="pos-pay-title" class="text-sm font-semibold text-slate-950">Payment</h2>
                        <p class="truncate text-[11px] text-slate-500">{{ cartCount }} unit{{ cartCount === 1 ? '' : 's' }} · {{ form.customer_name || 'Walk-in customer' }}</p>
                    </div>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-[5px] text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" aria-label="Close payment" :disabled="form.processing" @click="closePay"><Icon name="close-circle" :size="17" /></button>
                </div>

                <div class="flex items-end justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-500">Amount due</p>
                        <p v-if="Number(form.discount || 0) > 0" class="mt-0.5 text-[11px] text-slate-500">After {{ money(form.discount) }} discount</p>
                    </div>
                    <p class="text-2xl font-semibold tabular-nums tracking-tight text-slate-950">{{ money(total) }}</p>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
                    <div>
                        <p class="mb-1.5 text-[11px] font-semibold text-slate-600">Payment method</p>
                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3" role="radiogroup" aria-label="Payment method">
                            <button
                                v-for="method in paymentMethods"
                                :key="method.id"
                                type="button"
                                role="radio"
                                :aria-checked="Number(form.payment_method_id) === Number(method.id)"
                                class="flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-[5px] border px-2 text-xs font-semibold transition"
                                :class="Number(form.payment_method_id) === Number(method.id) ? 'border-prinx-500 bg-prinx-50 text-prinx-700' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                                @click="choosePaymentMethod(method)"
                            >
                                <Icon :name="method.is_cash ? 'money-add' : 'card'" :size="14" class="shrink-0" />
                                <span class="truncate">{{ method.name }}</span>
                            </button>
                        </div>
                        <p v-if="selectedPaymentMethod && !isCashPayment && (selectedPaymentMethod.account_number || selectedPaymentMethod.account_name)" class="mt-1.5 rounded-[5px] bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-600">
                            Pay to <b class="font-semibold text-slate-800">{{ selectedPaymentMethod.account_number }}</b><span v-if="selectedPaymentMethod.account_name"> · {{ selectedPaymentMethod.account_name }}</span>
                        </p>
                    </div>

                    <div>
                        <label for="pos-amount-paid" class="mb-1.5 block text-[11px] font-semibold text-slate-600">Amount paid</label>
                        <input id="pos-amount-paid" ref="payAmountInput" v-model.number="form.amount_paid" type="number" min="0" step="0.01" inputmode="decimal" class="h-9 w-full rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-prinx-500 focus:ring-2 focus:ring-prinx-100 h-10 text-right text-base font-semibold tabular-nums" :placeholder="String(Math.ceil(total))">
                        <div v-if="isCashPayment" class="mt-1.5 flex flex-wrap gap-1.5">
                            <button v-for="(amount, index) in quickAmounts" :key="amount" type="button" class="h-7 rounded-[4px] border px-2 text-[11px] font-semibold tabular-nums transition" :class="Number(paidAmount) === amount ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'" @click="form.amount_paid = amount">{{ index === 0 ? 'Exact' : money(amount) }}</button>
                        </div>
                    </div>

                    <div v-if="isCashPayment" class="flex h-10 items-center justify-between rounded-[5px] px-3 text-sm" :class="shortBy > 0 ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800'">
                        <span class="font-medium">{{ shortBy > 0 ? 'Short by' : 'Change' }}</span>
                        <b class="font-semibold tabular-nums">{{ money(shortBy > 0 ? shortBy : change) }}</b>
                    </div>
                    <p v-else-if="shortBy > 0" class="rounded-[5px] bg-red-50 px-3 py-2 text-xs font-medium text-red-700">Amount paid is {{ money(shortBy) }} less than the total.</p>

                    <div v-if="!isCashPayment">
                        <label for="pos-payment-reference" class="mb-1.5 block text-[11px] font-semibold text-slate-600">Transaction reference</label>
                        <input id="pos-payment-reference" v-model="form.payment_reference" class="h-9 w-full rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-prinx-500 focus:ring-2 focus:ring-prinx-100" placeholder="e.g. M-Pesa or bank transaction ID">
                    </div>

                    <div>
                        <label for="pos-sale-note" class="mb-1.5 block text-[11px] font-semibold text-slate-600">Note <span class="font-normal text-slate-400">(optional, also used on quotes and invoices)</span></label>
                        <input id="pos-sale-note" v-model="form.notes" class="h-9 w-full rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-prinx-500 focus:ring-2 focus:ring-prinx-100" placeholder="Sale / document note">
                    </div>

                    <div v-if="paymentErrors.length || form.errors.pos" class="rounded-[5px] border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        <p v-for="message in [...paymentErrors, form.errors.pos].filter(Boolean)" :key="message">{{ message }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-[1fr_2fr] gap-2 border-t border-slate-200 bg-slate-50 px-4 py-2.5">
                    <button type="button" class="h-10 rounded-[5px] border border-slate-300 bg-white text-xs font-semibold text-slate-700 transition hover:bg-slate-100" :disabled="form.processing" @click="closePay">Cancel</button>
                    <button type="submit" class="flex h-10 items-center justify-center gap-1.5 rounded-[5px] bg-prinx-500 px-3 text-xs font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canCheckout || !form.payment_method_id || shortBy > 0">
                        <Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" />
                        <span class="whitespace-nowrap">{{ form.processing ? 'Saving…' : 'Complete sale' }}</span>
                        <span v-if="!form.processing" class="whitespace-nowrap tabular-nums max-[400px]:hidden">· {{ money(total) }}</span>
                    </button>
                </div>
            </form>
        </div>

        <MobileBarcodeScanner
            v-model:open="mobileScannerOpen"
            title="Scan item barcode"
            message="Point the rear camera at the unit barcode, SKU barcode or product barcode."
            @scanned="handleMobileScan"
        />

        <div v-if="showHeld" class="fixed inset-0 z-[90] bg-slate-950/55 p-0 sm:p-3" @click.self="showHeld = false">
            <div class="ml-auto h-full w-full max-w-md overflow-y-auto bg-white p-3 shadow-2xl sm:rounded-[9px]">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3"><div><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-prinx-600">POS</p><h2 class="mt-0.5 text-lg font-semibold">Held invoices</h2></div><button class="flex h-9 w-9 items-center justify-center rounded-[7px] border border-slate-200" @click="showHeld = false"><Icon name="close-circle" :size="17" /></button></div>
                <div class="mt-3 space-y-2">
                    <div v-for="hold in heldSales" :key="hold.id" class="rounded-[8px] border border-slate-200 p-3"><div class="flex justify-between gap-3"><div class="min-w-0"><b class="text-xs">{{ hold.reference }}</b><p class="mt-1 text-[10px] text-slate-500">{{ hold.customer_name || 'Walk-in customer' }} · {{ new Date(hold.created_at).toLocaleString() }}</p></div><div class="flex shrink-0 gap-1"><Link :href="`/admin/pos?hold=${hold.id}`" class="flex h-8 items-center rounded-[7px] border border-slate-200 px-2 text-[10px] font-semibold">Resume</Link><button class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50" @click="router.delete(`/admin/pos/hold/${hold.id}`)"><Icon name="trash" :size="14" /></button></div></div></div>
                    <div v-if="!heldSales.length" class="py-12 text-center text-xs text-slate-400">No held invoices.</div>
                </div>
            </div>
        </div>
    </PosLayout>
</template>