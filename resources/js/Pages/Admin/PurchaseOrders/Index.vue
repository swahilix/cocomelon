<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
});

const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const filters = reactive({ search: props.filters.search || '', status: props.filters.status || '' });
const apply = () => router.get('/admin/purchase-orders', Object.fromEntries(Object.entries(filters).filter(([, value]) => value)), { preserveState: true, replace: true });
const setStatus = (status) => { filters.status = status; apply(); };

const tabs = [['', 'All'], ['draft', 'Draft'], ['sent', 'Sent'], ['converted', 'Purchased'], ['cancelled', 'Cancelled']];
const statusLabel = (status) => ({ converted: 'Purchased' }[status] || status);
const statusClass = (status) => ({
    draft: 'bg-slate-100 text-slate-600',
    sent: 'bg-blue-50 text-blue-700',
    converted: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
}[status] || 'bg-slate-100 text-slate-600');
const total = (status) => (status ? Number(props.counts[status] || 0) : Object.values(props.counts).reduce((sum, value) => sum + Number(value), 0));
</script>

<template>
    <AdminLayout>
        <Head title="Purchase orders" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Inventory</p>
                <h1 class="section-title">Purchase orders</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Lists of items and quantities to buy. Print or download them for a supplier without prices, see the estimated cost here, and turn them into a purchase when the goods arrive.</p>
            </div>
            <div class="flex flex-wrap gap-2"><Link href="/admin/purchases" class="btn-light"><Icon name="shopping-cart" :size="16" /> Purchases</Link><Link href="/admin/purchase-orders/create" class="btn-primary"><Icon name="add-circle" :size="16" /> New purchase order</Link></div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1 overflow-x-auto rounded-[10px] border border-slate-200 bg-white p-1">
                <button v-for="[value, label] in tabs" :key="value" type="button" :class="['shrink-0 rounded-[7px] px-3 py-1.5 text-xs font-semibold', filters.status === value ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100']" @click="setStatus(value)">{{ label }} <span class="opacity-60">{{ total(value) }}</span></button>
            </div>
            <form class="flex gap-2" @submit.prevent="apply"><input v-model="filters.search" class="input w-56" placeholder="Reference, supplier or item"><button class="btn-light">Search</button></form>
        </div>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[820px]">
                    <thead><tr><th>Reference</th><th>Supplier</th><th>Date</th><th>Items</th><th>Estimated cost</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr v-for="order in orders.data" :key="order.id">
                            <td><Link :href="`/admin/purchase-orders/${order.id}`" class="font-semibold text-slate-900 hover:text-prinx-600">{{ order.reference }}</Link><p v-if="order.expected_date" class="text-xs text-slate-500">Needed by {{ order.expected_date }}</p></td>
                            <td>{{ order.display_supplier_name || '—' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ order.order_date }}</td>
                            <td>{{ order.total_quantity }} <span class="text-xs text-slate-400">({{ order.line_count }} line{{ Number(order.line_count) === 1 ? '' : 's' }})</span></td>
                            <td class="font-semibold">{{ Number(order.estimated_total) > 0 ? money(order.estimated_total) : '—' }}</td>
                            <td><span :class="['rounded-[7px] px-2 py-1 text-[11px] font-semibold capitalize', statusClass(order.status)]">{{ statusLabel(order.status) }}</span><Link v-if="order.purchase_reference" :href="`/admin/stock/purchases/${order.stock_purchase_id}`" class="mt-1 block text-[11px] text-prinx-700 hover:underline">{{ order.purchase_reference }}</Link></td>
                        </tr>
                        <tr v-if="!orders.data.length"><td colspan="6" class="py-12 text-center text-slate-400">No purchase orders yet. <Link href="/admin/purchase-orders/create" class="font-semibold text-prinx-600">Create one</Link>.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <Pagination :links="orders.links || []" />
    </AdminLayout>
</template>
