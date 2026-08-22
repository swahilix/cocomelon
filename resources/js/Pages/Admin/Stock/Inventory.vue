<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ products: Array, categories: Array, brands: Array, summary: Object, recentMovements: Array });
const page = usePage();
const search = ref('');
const category = ref('');
const brand = ref('');
const status = ref('');
const tracking = ref('');
const reconciliation = ref('');
const selected = ref(null);
const selectedMovement = ref(null);

const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const reasons = [
    'Opening balance',
    'Physical count correction',
    'Damaged',
    'Lost',
    'Expired',
    'Internal use',
    'Return to supplier',
    'Other',
];

const form = useForm({
    product_id: '',
    mode: 'adjustment',
    target_quantity: 0,
    reason: 'Physical count correction',
    notes: '',
});

const reverseForm = useForm({ notes: '' });

const filtered = computed(() => props.products.filter((product) => {
    const q = search.value.trim().toLowerCase();
    if (q && !`${product.name} ${product.sku}`.toLowerCase().includes(q)) return false;
    if (category.value && product.category !== category.value) return false;
    if (brand.value && product.brand !== brand.value) return false;
    if (status.value && product.status !== status.value) return false;
    if (tracking.value === 'tracked' && !product.track_stock) return false;
    if (tracking.value === 'not_tracked' && product.track_stock) return false;
    if (reconciliation.value && product.reconciliation !== reconciliation.value) return false;
    return true;
}));

function openAdjustment(product) {
    selected.value = product;
    form.product_id = product.id;
    form.mode = product.has_movements ? 'adjustment' : 'opening';
    form.target_quantity = Number(product.stock_quantity || 0);
    form.reason = product.has_movements ? 'Physical count correction' : 'Opening balance';
    form.notes = '';
}

function closeAdjustment() {
    selected.value = null;
    form.clearErrors();
}

function submitAdjustment() {
    form.post('/admin/stock/adjustments', {
        preserveScroll: true,
        onSuccess: () => closeAdjustment(),
    });
}

function openReversal(movement) {
    selectedMovement.value = movement;
    reverseForm.notes = '';
    reverseForm.clearErrors();
}

function closeReversal() {
    selectedMovement.value = null;
    reverseForm.reset();
    reverseForm.clearErrors();
}

function submitReversal() {
    if (!selectedMovement.value) return;
    reverseForm.post(`/admin/stock/movements/${selectedMovement.value.id}/reverse`, {
        preserveScroll: true,
        onSuccess: () => closeReversal(),
    });
}

const statusLabel = (value) => ({
    in_stock: 'In stock',
    low_stock: 'Low stock',
    out_of_stock: 'Out of stock',
    not_tracked: 'Not tracked',
}[value] || value);

const statusClass = (value) => ({
    in_stock: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    low_stock: 'bg-amber-50 text-amber-700 border-amber-200',
    out_of_stock: 'bg-red-50 text-red-700 border-red-200',
    not_tracked: 'bg-slate-50 text-slate-600 border-slate-200',
}[value] || 'bg-slate-50 text-slate-600 border-slate-200');

const reconciliationLabel = (value) => ({
    matched: 'Balanced',
    mismatch: 'Mismatch',
    opening_required: 'Opening required',
    unverifiable: 'Unverifiable',
    not_tracked: 'Not tracked',
}[value] || value);

const reconciliationClass = (value) => ({
    matched: 'text-emerald-700',
    mismatch: 'text-red-700',
    opening_required: 'text-amber-700',
    unverifiable: 'text-amber-700',
    not_tracked: 'text-slate-500',
}[value] || 'text-slate-500');
</script>

<template>
    <AdminLayout>
        <Head title="Inventory" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Stock management</p>
                <h1 class="section-title">Inventory</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Review live stock, low-stock risk and ledger reconciliation. Stock corrections are posted here as permanent movements instead of silently editing product balances.</p>
            </div>
            <a href="/admin/stock" class="btn-light"><Icon name="arrow-left" :size="16" /> Stock dashboard</a>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Tracked products</p><b class="mt-2 block text-2xl">{{ summary.tracked_products }}</b><p class="mt-1 text-xs text-slate-500">{{ summary.units }} total units</p></div>
            <button class="metric-card text-left transition hover:border-amber-300" @click="status = 'low_stock'"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Low stock</p><b class="mt-2 block text-2xl">{{ summary.low_stock }}</b><p class="mt-1 text-xs text-slate-500">Click to filter</p></button>
            <button class="metric-card text-left transition hover:border-red-300" @click="status = 'out_of_stock'"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-red-600">Out of stock</p><b class="mt-2 block text-2xl">{{ summary.out_of_stock }}</b><p class="mt-1 text-xs text-slate-500">Click to filter</p></button>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Stock value</p><b class="mt-2 block text-xl">{{ money(summary.value) }}</b><p class="mt-1 text-xs text-slate-500">Using latest purchase cost</p></div>
            <button class="metric-card text-left transition hover:border-red-300" @click="reconciliation = 'mismatch'"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-red-600">Ledger mismatches</p><b class="mt-2 block text-2xl">{{ summary.reconciliation_mismatches }}</b><p class="mt-1 text-xs text-slate-500">Must be investigated</p></button>
            <button class="metric-card text-left transition hover:border-amber-300" @click="reconciliation = 'opening_required'"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Opening required</p><b class="mt-2 block text-2xl">{{ summary.opening_required }}</b><p class="mt-1 text-xs text-slate-500">Legacy stock without ledger history</p></button>
        </div>

        <div v-if="summary.reconciliation_mismatches" class="mt-5 flex items-start gap-3 rounded-[10px] border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <Icon name="danger" :size="20" class="mt-0.5 shrink-0" />
            <div><b>Inventory reconciliation needs attention.</b><p class="mt-1">One or more product balances do not equal their calculated movement history. Do not correct them by editing the product; review the ledger and post a controlled adjustment only after confirming the physical count.</p></div>
        </div>

        <div class="mt-6 card p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <div class="relative xl:col-span-2"><Icon name="search-normal" :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="input pl-9" placeholder="Search product or SKU"></div>
                <select v-model="category" class="input"><option value="">All categories</option><option v-for="item in categories" :key="item" :value="item">{{ item }}</option></select>
                <select v-model="brand" class="input"><option value="">All brands</option><option v-for="item in brands" :key="item" :value="item">{{ item }}</option></select>
                <select v-model="status" class="input"><option value="">All stock statuses</option><option value="in_stock">In stock</option><option value="low_stock">Low stock</option><option value="out_of_stock">Out of stock</option><option value="not_tracked">Not tracked</option></select>
                <select v-model="reconciliation" class="input"><option value="">All reconciliation</option><option value="matched">Balanced</option><option value="mismatch">Mismatch</option><option value="opening_required">Opening required</option><option value="unverifiable">Unverifiable</option></select>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button :class="['rounded-[8px] border px-3 py-2 text-xs font-semibold', tracking === '' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600']" @click="tracking = ''">All</button>
                <button :class="['rounded-[8px] border px-3 py-2 text-xs font-semibold', tracking === 'tracked' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600']" @click="tracking = 'tracked'">Stock tracked</button>
                <button :class="['rounded-[8px] border px-3 py-2 text-xs font-semibold', tracking === 'not_tracked' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600']" @click="tracking = 'not_tracked'">Not tracked</button>
                <button v-if="search || category || brand || status || tracking || reconciliation" class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="search=''; category=''; brand=''; status=''; tracking=''; reconciliation=''">Clear filters</button>
            </div>
        </div>

        <div class="mt-5 card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[1250px]">
                    <thead><tr><th>Product</th><th>Category / Brand</th><th>Current stock</th><th>Status</th><th>Ledger check</th><th>Latest cost</th><th>Stock value</th><th>Last movement</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="product in filtered" :key="product.id">
                            <td><div class="flex items-center gap-3"><img :src="product.image_url" :alt="product.name" class="h-11 w-11 rounded-[8px] border border-slate-200 object-cover"><div><p class="font-semibold text-slate-900">{{ product.name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ product.sku }}</p></div></div></td>
                            <td><p class="text-sm">{{ product.category || '—' }}</p><p class="mt-0.5 text-xs text-slate-500">{{ product.brand || 'No brand' }}</p></td>
                            <td><b>{{ product.track_stock ? product.stock_quantity : '—' }}</b><p v-if="product.track_stock" class="mt-0.5 text-xs text-slate-500">Low at {{ product.low_stock_threshold }}</p></td>
                            <td><span :class="['inline-flex rounded-[8px] border px-2 py-1 text-[11px] font-semibold', statusClass(product.status)]">{{ statusLabel(product.status) }}</span></td>
                            <td><p :class="['text-xs font-semibold', reconciliationClass(product.reconciliation)]">{{ reconciliationLabel(product.reconciliation) }}</p><p v-if="product.reconciliation === 'mismatch'" class="mt-1 text-[11px] text-slate-500">Ledger {{ product.expected_balance }} · Actual {{ product.stock_quantity }}</p><p v-else-if="product.movement_count" class="mt-1 text-[11px] text-slate-400">{{ product.movement_count }} movements</p></td>
                            <td>{{ money(product.last_cost) }}</td>
                            <td class="font-semibold">{{ money(product.stock_value) }}</td>
                            <td><template v-if="product.last_movement"><p class="text-sm font-medium">{{ product.last_movement.type }}</p><p class="mt-0.5 text-xs text-slate-500">{{ product.last_movement.quantity > 0 ? '+' : '' }}{{ product.last_movement.quantity }} → {{ product.last_movement.balance_after }}</p></template><span v-else class="text-xs text-slate-400">No movement yet</span></td>
                            <td class="text-right"><div class="flex justify-end gap-2"><a v-if="product.track_stock && ['low_stock','out_of_stock'].includes(product.status)" :href="`/admin/stock?tab=purchases&product=${product.id}`" class="btn-light px-3 py-2"><Icon name="shopping-cart" :size="15" /> Purchase</a><button v-if="product.track_stock" class="btn-light px-3 py-2" @click="openAdjustment(product)"><Icon name="edit-2" :size="15" /> {{ product.has_movements ? 'Adjust' : 'Opening stock' }}</button></div></td>
                        </tr>
                        <tr v-if="!filtered.length"><td colspan="9" class="py-12 text-center text-sm text-slate-400">No products match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4"><div><h2 class="font-semibold">Recent inventory ledger</h2><p class="mt-1 text-xs text-slate-500">Manual corrections can be reversed without deleting history. Purchase and sale movements must later be reversed from their original transaction workflow.</p></div><Icon name="box" :size="21" class="text-prinx-600" /></div>
            <div v-if="reverseForm.errors.reversal" class="m-4 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ reverseForm.errors.reversal }}</div>
            <div class="overflow-x-auto"><table class="admin-table min-w-[1000px]"><thead><tr><th>Product</th><th>Type / Reason</th><th>Change</th><th>Balance</th><th>Reference</th><th>Performed by</th><th>Date</th><th class="text-right">Action</th></tr></thead><tbody><tr v-for="movement in recentMovements" :key="movement.id"><td><p class="font-semibold">{{ movement.product_name }}</p><p class="text-xs text-slate-500">{{ movement.sku }}</p></td><td><p class="text-sm">{{ movement.type }}</p><p class="text-xs text-slate-500">{{ movement.reason || '—' }}</p></td><td><b :class="movement.quantity > 0 ? 'text-emerald-600' : movement.quantity < 0 ? 'text-red-600' : 'text-slate-600'">{{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}</b></td><td>{{ movement.balance_before ?? '—' }} → {{ movement.balance_after ?? '—' }}</td><td>{{ movement.reference || '—' }}</td><td>{{ movement.performed_by || 'System' }}</td><td>{{ new Date(movement.created_at).toLocaleString() }}</td><td class="text-right"><span v-if="movement.is_reversed" class="text-xs font-semibold text-slate-400">Reversed</span><button v-else-if="movement.can_reverse" class="btn-light px-3 py-2 text-red-600" @click="openReversal(movement)"><Icon name="refresh" :size="15" /> Reverse</button><span v-else class="text-xs text-slate-400">Source controlled</span></td></tr><tr v-if="!recentMovements.length"><td colspan="8" class="py-12 text-center text-sm text-slate-400">No stock movements yet.</td></tr></tbody></table></div>
        </section>

        <div v-if="selected" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4" @click.self="closeAdjustment">
            <form class="w-full max-w-lg rounded-[10px] bg-white p-5 shadow-2xl" @submit.prevent="submitAdjustment">
                <div class="flex items-start justify-between gap-4"><div><p class="section-kicker">Inventory adjustment</p><h2 class="mt-1 text-xl font-semibold text-slate-950">{{ selected.name }}</h2><p class="mt-1 text-xs text-slate-500">Current stock: {{ selected.stock_quantity }} · {{ selected.sku }}</p></div><button type="button" class="flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200" @click="closeAdjustment"><Icon name="close-circle" :size="18" /></button></div>

                <div v-if="form.errors.adjustment" class="mt-4 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ form.errors.adjustment }}</div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Mode</label><select v-model="form.mode" class="input" :disabled="selected.has_movements"><option value="opening">Opening balance</option><option value="adjustment">Stock adjustment</option></select><p v-if="selected.has_movements" class="mt-1 text-[11px] text-slate-400">Opening balance is unavailable after movement history exists.</p></div>
                    <div><label class="label">New physical quantity</label><input v-model.number="form.target_quantity" type="number" min="0" class="input" required></div>
                    <div class="sm:col-span-2"><label class="label">Reason</label><select v-model="form.reason" class="input" required><option v-for="reason in reasons" :key="reason" :value="reason">{{ reason }}</option></select></div>
                    <div class="sm:col-span-2"><label class="label">Note</label><textarea v-model="form.notes" rows="3" class="input" placeholder="Explain why this balance is being changed"></textarea></div>
                </div>

                <div class="mt-5 rounded-[8px] border border-slate-200 bg-slate-50 p-3 text-sm"><div class="flex justify-between"><span class="text-slate-500">Before</span><b>{{ selected.stock_quantity }}</b></div><div class="mt-2 flex justify-between"><span class="text-slate-500">After</span><b>{{ form.target_quantity }}</b></div><div class="mt-2 flex justify-between border-t border-slate-200 pt-2"><span class="text-slate-500">Difference</span><b :class="Number(form.target_quantity) - Number(selected.stock_quantity) < 0 ? 'text-red-600' : 'text-emerald-600'">{{ Number(form.target_quantity) - Number(selected.stock_quantity) > 0 ? '+' : '' }}{{ Number(form.target_quantity) - Number(selected.stock_quantity) }}</b></div></div>

                <div class="mt-5 flex justify-end gap-2"><button type="button" class="btn-light" @click="closeAdjustment">Cancel</button><button class="btn-primary" :disabled="form.processing"><Icon name="tick-circle" :size="16" /> Post adjustment</button></div>
            </form>
        </div>

        <div v-if="selectedMovement" class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/50 p-4" @click.self="closeReversal">
            <form class="w-full max-w-md rounded-[10px] bg-white p-5 shadow-2xl" @submit.prevent="submitReversal">
                <div class="flex items-start justify-between gap-4"><div><p class="section-kicker">Reverse movement</p><h2 class="mt-1 text-xl font-semibold">{{ selectedMovement.product_name }}</h2><p class="mt-1 text-xs text-slate-500">{{ selectedMovement.type }} · {{ selectedMovement.quantity > 0 ? '+' : '' }}{{ selectedMovement.quantity }} units</p></div><button type="button" class="flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200" @click="closeReversal"><Icon name="close-circle" :size="18" /></button></div>
                <div class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800">This does not delete the original record. A new opposite movement will be posted and linked to it. Use this only when the original manual adjustment itself was incorrect.</div>
                <div class="mt-4"><label class="label">Reversal reason</label><textarea v-model="reverseForm.notes" rows="4" class="input" placeholder="Explain why this movement must be reversed" required></textarea><p v-if="reverseForm.errors.notes" class="mt-1 text-xs text-red-600">{{ reverseForm.errors.notes }}</p></div>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="btn-light" @click="closeReversal">Cancel</button><button class="btn-primary" :disabled="reverseForm.processing"><Icon name="refresh" :size="16" /> Confirm reversal</button></div>
            </form>
        </div>
    </AdminLayout>
</template>
