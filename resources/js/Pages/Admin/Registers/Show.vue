<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ register: Object });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const session = props.register.session;
const summary = props.register.summary;
</script>

<template>
    <AdminLayout>
        <Head :title="`Register ${session.reference}`" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="section-kicker">Cash register report</p><h1 class="section-title">{{ session.reference }}</h1><p class="mt-2 text-sm text-slate-500">{{ session.cashier_name }} · {{ session.opened_at }} → {{ session.closed_at || 'Open' }}</p></div>
            <Link href="/admin/registers" class="btn-light"><Icon name="arrow-left" :size="16" /> Registers</Link>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Opening cash</p><b class="mt-2 block text-xl">{{ money(session.opening_cash) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Cash sales</p><b class="mt-2 block text-xl">{{ money(summary.cash_sales) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Non-cash sales</p><b class="mt-2 block text-xl">{{ money(summary.non_cash_sales) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-red-600">Cash refunds</p><b class="mt-2 block text-xl">{{ money(summary.cash_refunds) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Manual cash in</p><b class="mt-2 block text-xl">{{ money(summary.manual_cash_in) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Manual cash out</p><b class="mt-2 block text-xl">{{ money(summary.manual_cash_out) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Expected closing</p><b class="mt-2 block text-xl">{{ money(summary.expected_cash) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Variance</p><b :class="['mt-2 block text-xl', Number(summary.variance) < 0 ? 'text-red-600' : Number(summary.variance) > 0 ? 'text-amber-600' : '']">{{ summary.variance === null ? 'Open shift' : money(summary.variance) }}</b></div>
        </div>

        <div v-if="session.status === 'closed'" class="card mt-5 p-5"><div class="grid gap-4 md:grid-cols-3"><div><p class="text-xs text-slate-500">Expected cash</p><b>{{ money(summary.expected_cash) }}</b></div><div><p class="text-xs text-slate-500">Counted cash</p><b>{{ money(summary.counted_cash) }}</b></div><div><p class="text-xs text-slate-500">Variance</p><b>{{ money(summary.variance) }}</b></div></div><p v-if="session.closing_notes" class="mt-4 border-t border-slate-200 pt-4 text-sm text-slate-600">{{ session.closing_notes }}</p></div>

        <section class="card mt-6 overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Drawer ledger</h2></div><div class="overflow-x-auto"><table class="admin-table min-w-[850px]"><thead><tr><th>Reference</th><th>Type</th><th>Direction</th><th>Amount</th><th>Reason</th><th>User</th><th>Time</th></tr></thead><tbody><tr v-for="m in register.movements" :key="m.id"><td>{{ m.reference }}</td><td>{{ m.type }}</td><td :class="m.direction === 'in' ? 'text-emerald-600' : 'text-red-600'">{{ m.direction }}</td><td class="font-semibold">{{ money(m.amount) }}</td><td>{{ m.reason || '—' }}</td><td>{{ m.created_by_name || 'System' }}</td><td>{{ m.created_at }}</td></tr><tr v-if="!register.movements.length"><td colspan="7" class="py-10 text-center text-slate-400">No cash movements.</td></tr></tbody></table></div></section>

        <section class="card mt-6 overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">POS sales in this shift</h2></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Receipt</th><th>Customer</th><th>Method</th><th>Total</th><th>Status</th><th>Time</th></tr></thead><tbody><tr v-for="sale in register.sales" :key="sale.id"><td><Link :href="`/admin/sales/pos/${sale.id}`" class="font-semibold text-prinx-600">{{ sale.reference }}</Link></td><td>{{ sale.customer_name || 'Walk-in customer' }}</td><td>{{ sale.payment_method }}</td><td>{{ money(sale.total) }}</td><td>{{ sale.status }}</td><td>{{ sale.created_at }}</td></tr><tr v-if="!register.sales.length"><td colspan="6" class="py-10 text-center text-slate-400">No completed POS sales in this shift.</td></tr></tbody></table></div></section>
    </AdminLayout>
</template>
