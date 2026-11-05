<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    purchases: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({}) },
    suppliers: { type: Array, default: () => [] },
    openOrders: { type: Number, default: 0 },
});

const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const filters = reactive({ ...props.filters });

const apply = () => router.get('/admin/purchases', Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null)), { preserveState: true, replace: true });
const clear = () => {
    Object.keys(filters).forEach((key) => { filters[key] = ''; });
    apply();
};

const statusLabel = (status) => ({ partially_received: 'Partly received' }[status] || String(status || '').replaceAll('_', ' '));
const statusClass = (status) => ({
    received: 'bg-emerald-50 text-emerald-700',
    partially_received: 'bg-amber-50 text-amber-700',
    ordered: 'bg-blue-50 text-blue-700',
    draft: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-red-50 text-red-700',
}[status] || 'bg-slate-100 text-slate-600');
const paymentClass = (status) => ({
    paid: 'text-emerald-700',
    partially_paid: 'text-amber-700',
    unpaid: 'text-red-600',
}[status] || 'text-slate-500');
</script>

<template>
    <AdminLayout>
        <Head title="Purchases" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Inventory</p>
                <h1 class="section-title">Purchases</h1>
                <p class="mt-2 text-sm text-slate-500">Everything bought from suppliers: what was received, what is still expected and what is still owed.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link href="/admin/purchase-orders" class="btn-light"><Icon name="document-text-2" :size="16" /> Purchase orders<span v-if="openOrders" class="ml-1 rounded-full bg-prinx-100 px-1.5 text-[10px] font-bold text-prinx-700">{{ openOrders }}</span></Link>
                <Link href="/admin/stock/entry?mode=purchase" class="btn-primary"><Icon name="add-circle" :size="16" /> New purchase</Link>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Purchases</p><b class="mt-2 block text-2xl">{{ totals.count }}</b><p class="mt-1 text-xs text-slate-500">Matching the filters, excluding cancelled</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Total value</p><b class="mt-2 block text-xl">{{ money(totals.total) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Paid</p><b class="mt-2 block text-xl text-emerald-700">{{ money(totals.paid) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Still owed</p><b :class="['mt-2 block text-xl', totals.balance > 0 ? 'text-red-600' : '']">{{ money(totals.balance) }}</b></div>
        </div>

        <form class="card mt-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_.8fr_.8fr_auto]" @submit.prevent="apply">
            <input v-model="filters.search" class="input" placeholder="Reference, invoice or supplier">
            <select v-model="filters.supplier_id" class="input"><option value="">All suppliers</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select>
            <select v-model="filters.status" data-native-select class="input"><option value="">Any status</option><option value="ordered">Ordered</option><option value="partially_received">Partly received</option><option value="received">Received</option><option value="draft">Draft</option><option value="cancelled">Cancelled</option></select>
            <select v-model="filters.payment_status" data-native-select class="input"><option value="">Any payment</option><option value="unpaid">Unpaid</option><option value="partially_paid">Partly paid</option><option value="paid">Paid</option></select>
            <input v-model="filters.from" type="date" class="input" title="From date">
            <input v-model="filters.to" type="date" class="input" title="To date">
            <div class="flex gap-2"><button class="btn-primary">Filter</button><button type="button" class="btn-light" @click="clear">Clear</button></div>
        </form>

        <section class="card mt-5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[900px]">
                    <thead><tr><th>Reference</th><th>Supplier</th><th>Date</th><th>Items</th><th>Status</th><th>Total</th><th>Payment</th></tr></thead>
                    <tbody>
                        <tr v-for="purchase in purchases.data" :key="purchase.id">
                            <td><Link :href="`/admin/stock/purchases/${purchase.id}`" class="font-semibold text-slate-900 hover:text-prinx-600">{{ purchase.reference }}</Link><p v-if="purchase.invoice_number" class="text-xs text-slate-500">Invoice {{ purchase.invoice_number }}</p><p v-if="purchase.purchase_order_reference" class="text-xs text-prinx-700">From {{ purchase.purchase_order_reference }}</p></td>
                            <td>{{ purchase.display_supplier_name || '—' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ purchase.purchase_date }}</td>
                            <td>{{ purchase.total_quantity }}</td>
                            <td><span :class="['rounded-[7px] px-2 py-1 text-[11px] font-semibold capitalize', statusClass(purchase.status)]">{{ statusLabel(purchase.status) }}</span></td>
                            <td class="font-semibold">{{ money(purchase.total) }}</td>
                            <td><p :class="['text-xs font-semibold capitalize', paymentClass(purchase.payment_status)]">{{ String(purchase.payment_status || '').replaceAll('_', ' ') }}</p><p v-if="Number(purchase.balance_due) > 0 && purchase.status !== 'cancelled'" class="text-[11px] text-slate-500">Owed {{ money(purchase.balance_due) }}</p></td>
                        </tr>
                        <tr v-if="!purchases.data.length"><td colspan="7" class="py-12 text-center text-slate-400">No purchases found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <Pagination :links="purchases.links || []" />
    </AdminLayout>
</template>
