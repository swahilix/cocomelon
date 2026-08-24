<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    state: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    historicalPurchases: { type: Array, default: () => [] },
    historicalSales: { type: Array, default: () => [] },
});

const tab = ref(props.state?.purchases_confirmed ? 'sales' : 'purchases');
const yesterday = () => {
    const date = new Date();
    date.setDate(date.getDate() - 1);
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
};
const yesterdayNoon = () => `${yesterday()}T12:00`;
const money = (value) => new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0));
const emptyPurchaseItem = () => ({ product_id: '', quantity: 1, unit_cost: '' });
const emptySaleItem = () => ({ product_id: '', quantity: 1, unit_price: '', unit_cost: '' });
const firstPayment = props.paymentMethods[0] || null;

const purchaseForm = useForm({
    supplier_id: '',
    one_time_supplier_name: '',
    invoice_number: '',
    purchase_date: yesterday(),
    additional_cost: 0,
    discount: 0,
    tax: 0,
    amount_paid: '',
    payment_method: firstPayment?.name || 'Cash',
    payment_date: yesterday(),
    transaction_reference: '',
    notes: '',
    items: [emptyPurchaseItem()],
});

const saleForm = useForm({
    sale_at: yesterdayNoon(),
    customer_name: '',
    customer_phone: '',
    discount: 0,
    amount_paid: '',
    payment_method_id: firstPayment?.id || '',
    payment_reference: '',
    notes: '',
    items: [emptySaleItem()],
});

const purchaseSubtotal = computed(() => purchaseForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_cost || 0), 0));
const purchaseTotal = computed(() => Math.max(0, purchaseSubtotal.value + Number(purchaseForm.additional_cost || 0) - Number(purchaseForm.discount || 0) + Number(purchaseForm.tax || 0)));
const saleSubtotal = computed(() => saleForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));
const saleTotal = computed(() => Math.max(0, saleSubtotal.value - Number(saleForm.discount || 0)));
const setupLocked = computed(() => Boolean(props.state?.completed));

function product(id) {
    return props.products.find((item) => Number(item.id) === Number(id));
}

function choosePurchaseProduct(item) {
    const selected = product(item.product_id);
    if (!selected) return;
    if (item.unit_cost === '' || Number(item.unit_cost) === 0) {
        item.unit_cost = selected.last_purchase_cost || selected.average_cost || '';
    }
}

function chooseSaleProduct(item) {
    const selected = product(item.product_id);
    if (!selected) return;
    if (item.unit_price === '' || Number(item.unit_price) === 0) item.unit_price = selected.price || '';
    // Leave cost empty deliberately: the server calculates the historical cost
    // from purchases dated on/before the sale date. Staff can override if needed.
}

function addPurchaseItem() { purchaseForm.items.push(emptyPurchaseItem()); }
function addSaleItem() { saleForm.items.push(emptySaleItem()); }
function removePurchaseItem(index) { if (purchaseForm.items.length > 1) purchaseForm.items.splice(index, 1); }
function removeSaleItem(index) { if (saleForm.items.length > 1) saleForm.items.splice(index, 1); }

function resetPurchase() {
    purchaseForm.reset();
    purchaseForm.supplier_id = '';
    purchaseForm.one_time_supplier_name = '';
    purchaseForm.invoice_number = '';
    purchaseForm.purchase_date = yesterday();
    purchaseForm.payment_date = yesterday();
    purchaseForm.payment_method = firstPayment?.name || 'Cash';
    purchaseForm.amount_paid = '';
    purchaseForm.items = [emptyPurchaseItem()];
    purchaseForm.clearErrors();
}

function resetSale() {
    saleForm.reset();
    saleForm.sale_at = yesterdayNoon();
    saleForm.payment_method_id = firstPayment?.id || '';
    saleForm.amount_paid = '';
    saleForm.items = [emptySaleItem()];
    saleForm.clearErrors();
}

function savePurchase() {
    purchaseForm.post('/admin/historical-data/purchases', {
        preserveScroll: true,
        onSuccess: resetPurchase,
    });
}

function saveSale() {
    saleForm.post('/admin/historical-data/sales', {
        preserveScroll: true,
        onSuccess: resetSale,
    });
}

function confirmPurchases() {
    if (!window.confirm('Confirm that all purchases from before the system started have been entered? Historical sales will unlock after this.')) return;
    router.post('/admin/historical-data/confirm-purchases', {}, { preserveScroll: true, onSuccess: () => { tab.value = 'sales'; } });
}

function reopenPurchases() {
    if (!window.confirm('Reopen historical purchases? Historical sales entry will be locked until purchases are confirmed again.')) return;
    router.post('/admin/historical-data/reopen-purchases', {}, { preserveScroll: true, onSuccess: () => { tab.value = 'purchases'; } });
}

function completeSetup() {
    if (!window.confirm('Mark historical data entry as complete? Imported purchases and sales will remain in all reports.')) return;
    router.post('/admin/historical-data/complete', {}, { preserveScroll: true });
}

function removePurchase(id) {
    if (!window.confirm('Remove this imported historical purchase? Current stock will not be affected.')) return;
    router.delete(`/admin/historical-data/purchases/${id}`, { preserveScroll: true });
}

function removeSale(id) {
    if (!window.confirm('Remove this imported historical sale? Current stock and cash register will not be affected.')) return;
    router.delete(`/admin/historical-data/sales/${id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <Head title="Historical data entry" />

        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="section-kicker">Business opening records</p>
                    <h1 class="section-title">Historical data entry</h1>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500 sm:text-sm">Record purchases and sales that happened before this system became your live stock ledger. These records are included in reports and profit calculations, but they never change the physical stock you have today.</p>
                </div>
                <span v-if="state.completed" class="inline-flex w-fit items-center gap-1.5 rounded-[8px] bg-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-700"><Icon name="tick-circle" :size="16" /> Setup complete</span>
                <span v-else class="inline-flex w-fit items-center gap-1.5 rounded-[8px] bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-700"><Icon name="clock" :size="16" /> Temporary setup tool</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Old purchases</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ summary.purchase_count }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Purchase value</p><p class="mt-1 text-sm font-semibold text-slate-900">TZS {{ money(summary.purchase_value) }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Old sales</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ summary.sale_count }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Sales value</p><p class="mt-1 text-sm font-semibold text-slate-900">TZS {{ money(summary.sale_value) }}</p></div>
            </div>

            <section class="mt-4 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                <div class="grid grid-cols-2 border-b border-slate-200 bg-slate-50">
                    <button type="button" :class="['min-h-12 px-3 text-xs font-semibold transition', tab === 'purchases' ? 'bg-white text-prinx-600 shadow-[inset_0_-2px_0_#ff4d0a]' : 'text-slate-500']" @click="tab = 'purchases'">1. Historical purchases</button>
                    <button type="button" :class="['min-h-12 px-3 text-xs font-semibold transition', tab === 'sales' ? 'bg-white text-prinx-600 shadow-[inset_0_-2px_0_#ff4d0a]' : 'text-slate-500', !state.purchases_confirmed && 'opacity-60']" @click="state.purchases_confirmed ? tab = 'sales' : null">2. Historical sales</button>
                </div>

                <div v-if="tab === 'purchases'" class="p-3 sm:p-4">
                    <div class="mb-4 flex flex-col gap-2 rounded-[8px] border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-blue-800 sm:flex-row sm:items-center sm:justify-between">
                        <div><b>Enter purchases before sales.</b> Quantity and cost history will be used to calculate historical cost of goods sold. No imported purchase increases current stock.</div>
                        <button v-if="state.purchases_confirmed || state.completed" type="button" class="btn-light shrink-0" @click="reopenPurchases">Reopen purchases</button>
                    </div>

                    <form v-if="!state.purchases_confirmed && !setupLocked" class="grid gap-4" @submit.prevent="savePurchase">
                        <div v-if="purchaseForm.errors.purchase" class="rounded-[7px] bg-red-50 px-3 py-2 text-xs text-red-700">{{ purchaseForm.errors.purchase }}</div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label class="label">Purchase date</label><input v-model="purchaseForm.purchase_date" type="date" class="input" required></div>
                            <div><label class="label">Saved supplier</label><select v-model="purchaseForm.supplier_id" class="input"><option value="">One-time supplier</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select></div>
                            <div><label class="label">One-time supplier</label><input v-model="purchaseForm.one_time_supplier_name" class="input" :disabled="Boolean(purchaseForm.supplier_id)" placeholder="Supplier name"></div>
                            <div><label class="label">Invoice / receipt no.</label><input v-model="purchaseForm.invoice_number" class="input" placeholder="Optional"></div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2.5"><div><h2 class="text-xs font-semibold text-slate-900">Purchased items</h2><p class="text-[10px] text-slate-500">Use the actual quantity and unit buying cost.</p></div><button type="button" class="btn-light h-8 px-2.5 text-[11px]" @click="addPurchaseItem"><Icon name="add-circle" :size="15" /> Add item</button></div>
                            <div class="divide-y divide-slate-100">
                                <div v-for="(item, index) in purchaseForm.items" :key="index" class="grid gap-2 p-3 sm:grid-cols-[1fr_100px_150px_38px] sm:items-end">
                                    <div><label class="label">Product</label><select v-model="item.product_id" class="input" required @change="choosePurchaseProduct(item)"><option value="">Select product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} · {{ p.sku }}</option></select></div>
                                    <div><label class="label">Qty</label><input v-model.number="item.quantity" type="number" min="1" class="input" required></div>
                                    <div><label class="label">Unit cost</label><input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="input" required></div>
                                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50 disabled:opacity-30" :disabled="purchaseForm.items.length === 1" @click="removePurchaseItem(index)"><Icon name="trash" :size="16" /></button>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label class="label">Additional cost</label><input v-model.number="purchaseForm.additional_cost" type="number" min="0" step="0.01" class="input"></div>
                            <div><label class="label">Discount</label><input v-model.number="purchaseForm.discount" type="number" min="0" step="0.01" class="input"></div>
                            <div><label class="label">Tax</label><input v-model.number="purchaseForm.tax" type="number" min="0" step="0.01" class="input"></div>
                            <div class="rounded-[8px] bg-slate-950 px-3 py-2 text-white"><p class="text-[10px] text-slate-400">Purchase total</p><b class="text-sm">TZS {{ money(purchaseTotal) }}</b></div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label class="label">Amount paid</label><input v-model.number="purchaseForm.amount_paid" type="number" min="0" step="0.01" class="input" :placeholder="`Default ${money(purchaseTotal)}`"><p v-if="purchaseForm.errors.amount_paid" class="error">{{ purchaseForm.errors.amount_paid }}</p></div>
                            <div><label class="label">Payment method</label><select v-model="purchaseForm.payment_method" class="input"><option v-for="method in paymentMethods" :key="method.id" :value="method.name">{{ method.name }}</option><option value="Credit">Credit / unpaid</option><option value="Other">Other</option></select></div>
                            <div><label class="label">Payment date</label><input v-model="purchaseForm.payment_date" type="date" class="input"></div>
                            <div><label class="label">Payment reference</label><input v-model="purchaseForm.transaction_reference" class="input" placeholder="Optional"></div>
                        </div>

                        <div><label class="label">Notes</label><textarea v-model="purchaseForm.notes" class="input" rows="2" placeholder="Anything useful about this old purchase"></textarea></div>

                        <button type="submit" class="btn-primary min-h-11 sm:w-fit sm:min-w-48" :disabled="purchaseForm.processing"><Icon name="tick-circle" :size="17" />{{ purchaseForm.processing ? 'Saving...' : 'Save historical purchase' }}</button>
                    </form>

                    <div v-if="!state.purchases_confirmed && !setupLocked" class="mt-5 border-t border-slate-200 pt-4">
                        <button type="button" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-[8px] bg-slate-950 px-4 text-xs font-semibold text-white sm:w-auto" @click="confirmPurchases"><Icon name="tick-circle" :size="17" /> I have finished entering purchases</button>
                        <p class="mt-2 text-[11px] text-slate-500">Only confirm after all purchases from before the system became live have been entered.</p>
                    </div>
                </div>

                <div v-else class="p-3 sm:p-4">
                    <div v-if="!state.purchases_confirmed" class="rounded-[8px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Historical sales are locked until you confirm that all historical purchases have been entered.</div>
                    <template v-else>
                        <div class="mb-4 rounded-[8px] border border-emerald-200 bg-emerald-50 p-3 text-xs leading-5 text-emerald-800"><b>Purchases confirmed.</b> Historical sales will use purchases dated on or before each sale to calculate cost automatically. These sales affect revenue, COGS and profit reports, but do not reduce current stock or change today's cash register.</div>

                        <form v-if="!setupLocked" class="grid gap-4" @submit.prevent="saveSale">
                            <div v-if="saleForm.errors.sale" class="rounded-[7px] bg-red-50 px-3 py-2 text-xs text-red-700">{{ saleForm.errors.sale }}</div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label">Sale date & time</label><input v-model="saleForm.sale_at" type="datetime-local" class="input" required></div>
                                <div><label class="label">Customer name</label><input v-model="saleForm.customer_name" class="input" placeholder="Walk-in customer"></div>
                                <div><label class="label">Customer phone</label><input v-model="saleForm.customer_phone" class="input" placeholder="Optional"></div>
                                <div><label class="label">Payment method</label><select v-model="saleForm.payment_method_id" class="input" required><option value="">Select</option><option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option></select></div>
                            </div>

                            <div class="rounded-[8px] border border-slate-200">
                                <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2.5"><div><h2 class="text-xs font-semibold text-slate-900">Sold items</h2><p class="text-[10px] text-slate-500">Cost can be left blank to calculate from purchase history.</p></div><button type="button" class="btn-light h-8 px-2.5 text-[11px]" @click="addSaleItem"><Icon name="add-circle" :size="15" /> Add item</button></div>
                                <div class="divide-y divide-slate-100">
                                    <div v-for="(item, index) in saleForm.items" :key="index" class="grid gap-2 p-3 sm:grid-cols-[1fr_85px_130px_130px_38px] sm:items-end">
                                        <div><label class="label">Product</label><select v-model="item.product_id" class="input" required @change="chooseSaleProduct(item)"><option value="">Select product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} · {{ p.sku }}</option></select></div>
                                        <div><label class="label">Qty</label><input v-model.number="item.quantity" type="number" min="1" class="input" required></div>
                                        <div><label class="label">Selling price</label><input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="input" required></div>
                                        <div><label class="label">Cost <span class="text-slate-400">(auto)</span></label><input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="input" placeholder="Auto"><p v-if="saleForm.errors[`items.${index}.unit_cost`]" class="error">{{ saleForm.errors[`items.${index}.unit_cost`] }}</p></div>
                                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50 disabled:opacity-30" :disabled="saleForm.items.length === 1" @click="removeSaleItem(index)"><Icon name="trash" :size="16" /></button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label">Discount</label><input v-model.number="saleForm.discount" type="number" min="0" step="0.01" class="input"></div>
                                <div><label class="label">Amount paid</label><input v-model.number="saleForm.amount_paid" type="number" min="0" step="0.01" class="input" :placeholder="`Default ${money(saleTotal)}`"></div>
                                <div><label class="label">Payment reference</label><input v-model="saleForm.payment_reference" class="input" placeholder="Optional"></div>
                                <div class="rounded-[8px] bg-slate-950 px-3 py-2 text-white"><p class="text-[10px] text-slate-400">Sale total</p><b class="text-sm">TZS {{ money(saleTotal) }}</b></div>
                            </div>

                            <div><label class="label">Notes</label><textarea v-model="saleForm.notes" class="input" rows="2" placeholder="Optional historical sale note"></textarea></div>
                            <button type="submit" class="btn-primary min-h-11 sm:w-fit sm:min-w-44" :disabled="saleForm.processing"><Icon name="tick-circle" :size="17" />{{ saleForm.processing ? 'Saving...' : 'Save historical sale' }}</button>
                        </form>
                    </template>
                </div>
            </section>

            <section class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-[9px] border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2.5"><div><h2 class="text-xs font-semibold text-slate-900">Imported purchases</h2><p class="text-[10px] text-slate-500">{{ historicalPurchases.length }} most recent entries</p></div></div>
                    <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                        <div v-for="row in historicalPurchases" :key="row.id" class="flex items-center justify-between gap-3 p-3">
                            <div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-900">{{ row.supplier_name || 'One-time supplier' }}</p><p class="mt-0.5 text-[10px] text-slate-500">{{ row.purchase_date }} · {{ row.reference }}<span v-if="row.invoice_number"> · {{ row.invoice_number }}</span></p></div>
                            <div class="flex shrink-0 items-center gap-2"><div class="text-right"><p class="text-xs font-semibold">TZS {{ money(row.total) }}</p><p class="text-[9px] uppercase text-slate-400">{{ row.payment_status }}</p></div><button v-if="!state.purchases_confirmed && !setupLocked" type="button" class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50" @click="removePurchase(row.id)"><Icon name="trash" :size="14" /></button></div>
                        </div>
                        <p v-if="!historicalPurchases.length" class="p-6 text-center text-xs text-slate-400">No historical purchases entered yet.</p>
                    </div>
                </div>

                <div class="rounded-[9px] border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2.5"><div><h2 class="text-xs font-semibold text-slate-900">Imported sales</h2><p class="text-[10px] text-slate-500">{{ historicalSales.length }} most recent entries</p></div></div>
                    <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                        <div v-for="row in historicalSales" :key="row.id" class="flex items-center justify-between gap-3 p-3">
                            <div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-900">{{ row.customer_name || 'Walk-in customer' }}</p><p class="mt-0.5 text-[10px] text-slate-500">{{ new Date(row.created_at).toLocaleString() }} · {{ row.reference }}</p></div>
                            <div class="flex shrink-0 items-center gap-2"><div class="text-right"><p class="text-xs font-semibold">TZS {{ money(row.total) }}</p><p class="text-[9px] uppercase text-slate-400">{{ row.payment_method }}</p></div><button v-if="!setupLocked" type="button" class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50" @click="removeSale(row.id)"><Icon name="trash" :size="14" /></button></div>
                        </div>
                        <p v-if="!historicalSales.length" class="p-6 text-center text-xs text-slate-400">No historical sales entered yet.</p>
                    </div>
                </div>
            </section>

            <div v-if="state.purchases_confirmed && !setupLocked" class="mt-4 rounded-[9px] border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">Finish historical setup</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">After checking purchases and sales against your old records, mark this setup complete. The data stays in Reports, Sales, Purchases and Profit & Costing.</p>
                <button type="button" class="mt-3 btn-primary" @click="completeSetup"><Icon name="tick-circle" :size="17" /> Mark setup complete</button>
            </div>
        </div>
    </AdminLayout>
</template>
