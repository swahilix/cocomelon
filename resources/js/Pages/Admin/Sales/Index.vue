<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ sales: Array, filters: Object, summary: Object });
const page = usePage();
const search = ref(props.filters?.search || '');
const source = ref(props.filters?.source || '');
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const apply = () => router.get('/admin/sales', { search: search.value || undefined, source: source.value || undefined }, { preserveState: true, replace: true });
const badge = (value) => ({ voided: 'bg-red-50 text-red-700', returned: 'bg-amber-50 text-amber-700', partially_returned: 'bg-amber-50 text-amber-700', completed: 'bg-emerald-50 text-emerald-700', paid: 'bg-emerald-50 text-emerald-700', pending: 'bg-slate-100 text-slate-600', refunded: 'bg-amber-50 text-amber-700' }[value] || 'bg-slate-100 text-slate-600');
const isHistorical = (sale) => sale.source === 'pos' && String(sale.reference || '').startsWith('HPOS-');
</script>

<template>
<AdminLayout>
    <Head title="Sales Management" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="section-kicker">Sales & customers</p><h1 class="section-title">Sales management</h1><p class="mt-2 text-sm text-slate-500">Review POS and online sales in one place while preserving each source workflow.</p></div>
        <Link href="/admin/pos" class="btn-primary"><Icon name="shop" :size="17" /> Open POS</Link>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="metric-card"><p class="text-xs text-slate-500">POS sales</p><b class="mt-2 block text-2xl">{{ summary.pos_sales }}</b></div>
        <div class="metric-card"><p class="text-xs text-slate-500">POS revenue</p><b class="mt-2 block text-xl">{{ money(summary.pos_revenue) }}</b></div>
        <div class="metric-card"><p class="text-xs text-slate-500">Online sales</p><b class="mt-2 block text-2xl">{{ summary.online_sales }}</b></div>
        <div class="metric-card"><p class="text-xs text-slate-500">Online revenue</p><b class="mt-2 block text-xl">{{ money(summary.online_revenue) }}</b></div>
        <div class="metric-card"><p class="text-xs text-slate-500">POS refunds</p><b class="mt-2 block text-xl">{{ money(summary.refunds) }}</b></div>
    </div>

    <div class="card mt-6 p-4"><div class="grid gap-3 md:grid-cols-[1fr_180px_auto]"><input v-model="search" class="input" placeholder="Search reference, customer or phone" @keyup.enter="apply"><select v-model="source" class="input" @change="apply"><option value="">All sources</option><option value="pos">POS</option><option value="online">Online</option></select><button class="btn-light" @click="apply"><Icon name="search-normal" :size="16" /> Search</button></div></div>

    <div class="card mt-6 overflow-x-auto">
        <table class="admin-table"><thead><tr><th>Reference</th><th>Source</th><th>Customer</th><th>Date</th><th>Amount</th><th>Payment</th><th>Status</th><th>Staff</th><th></th></tr></thead><tbody>
        <tr v-for="sale in sales" :key="`${sale.source}-${sale.id}`">
            <td><p class="font-semibold">{{ sale.reference }}</p><span v-if="isHistorical(sale)" class="mt-1 inline-flex rounded-[6px] bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-700">Historical import</span></td>
            <td><span class="rounded-[7px] px-2 py-1 text-xs font-semibold" :class="isHistorical(sale) ? 'bg-amber-50 text-amber-700' : sale.source === 'pos' ? 'bg-blue-50 text-blue-700' : 'bg-violet-50 text-violet-700'">{{ isHistorical(sale) ? 'Historical POS' : sale.source === 'pos' ? 'POS' : 'Online' }}</span></td>
            <td><p class="font-medium">{{ sale.customer }}</p><p class="text-xs text-slate-500">{{ sale.phone || '—' }}</p></td>
            <td>{{ new Date(sale.created_at).toLocaleString() }}</td><td class="font-semibold">{{ money(sale.amount) }}</td>
            <td><span :class="['rounded-[7px] px-2 py-1 text-xs font-semibold', badge(sale.payment_status)]">{{ sale.payment_status }}</span></td>
            <td><span :class="['rounded-[7px] px-2 py-1 text-xs font-semibold', badge(sale.status)]">{{ sale.status }}</span></td>
            <td>{{ sale.staff || '—' }}</td><td><Link :href="sale.url" class="btn-light px-3 py-1.5 text-xs">View</Link></td>
        </tr>
        <tr v-if="!sales.length"><td colspan="9" class="py-14 text-center text-slate-400">No sales found.</td></tr>
        </tbody></table>
    </div>
</AdminLayout>
</template>