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
            <div><p class="section-kicker">Register shift report</p><h1 class="section-title">{{ session.reference }}</h1><p class="mt-2 text-sm text-slate-500">{{ session.cashier_name }} · {{ session.opened_at }} → {{ session.closed_at || 'Open' }}</p></div>
            <Link href="/admin/registers" class="btn-light"><Icon name="arrow-left" :size="16" /> Registers</Link>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Cash sales</p><b class="mt-2 block text-xl">{{ money(summary.cash_sales) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Non-cash sales</p><b class="mt-2 block text-xl">{{ money(summary.non_cash_sales) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-red-600">Cash refunds</p><b class="mt-2 block text-xl">{{ money(summary.cash_refunds) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Total variance</p><b :class="['mt-2 block text-xl', Number(summary.total_variance) < 0 ? 'text-red-600' : Number(summary.total_variance) > 0 ? 'text-amber-600' : '']">{{ session.status === 'open' ? 'Open shift' : money(summary.total_variance) }}</b></div>
        </div>

        <section class="card mt-5 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Payment method reconciliation</h2><p class="mt-1 text-xs text-slate-500">Opening, expected and counted balances for every method included when the register was opened.</p></div>
            <div class="overflow-x-auto"><table class="admin-table min-w-[850px]"><thead><tr><th>Payment method</th><th>Account / Lipa number</th><th>Opening</th><th>Expected closing</th><th>Counted closing</th><th>Variance</th></tr></thead><tbody><tr v-for="balance in register.method_balances" :key="balance.id"><td class="font-semibold">{{ balance.payment_method_name }}</td><td>{{ balance.account_number || balance.account_name || '—' }}</td><td>{{ money(balance.opening_balance) }}</td><td class="font-semibold">{{ money(balance.expected_closing_balance) }}</td><td>{{ balance.counted_closing_balance === null ? (session.status === 'open' ? 'Not closed' : '—') : money(balance.counted_closing_balance) }}</td><td :class="Number(balance.variance) < 0 ? 'text-red-600' : Number(balance.variance) > 0 ? 'text-amber-600' : ''">{{ balance.variance === null ? '—' : money(balance.variance) }}</td></tr></tbody></table></div>
        </section>

        <div v-if="session.status === 'closed' && session.closing_notes" class="card mt-5 p-5"><p class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Closing notes</p><p class="mt-2 text-sm text-slate-600">{{ session.closing_notes }}</p></div>

        <section class="card mt-6 overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Register ledger</h2></div><div class="overflow-x-auto"><table class="admin-table min-w-[900px]"><thead><tr><th>Reference</th><th>Method</th><th>Type</th><th>Direction</th><th>Amount</th><th>Reason</th><th>User</th><th>Time</th></tr></thead><tbody><tr v-for="m in register.movements" :key="m.id"><td>{{ m.reference }}</td><td class="font-semibold">{{ m.payment_method_name || 'Cash' }}</td><td>{{ m.type }}</td><td :class="m.direction === 'in' ? 'text-emerald-600' : 'text-red-600'">{{ m.direction }}</td><td class="font-semibold">{{ money(m.amount) }}</td><td>{{ m.reason || '—' }}</td><td>{{ m.created_by_name || 'System' }}</td><td>{{ m.created_at }}</td></tr><tr v-if="!register.movements.length"><td colspan="8" class="py-10 text-center text-slate-400">No register movements.</td></tr></tbody></table></div></section>

        <section class="card mt-6 overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">POS sales in this shift</h2></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Receipt</th><th>Customer</th><th>Method</th><th>Total</th><th>Status</th><th>Time</th></tr></thead><tbody><tr v-for="sale in register.sales" :key="sale.id"><td><Link :href="`/admin/sales/pos/${sale.id}`" class="font-semibold text-prinx-600">{{ sale.reference }}</Link></td><td>{{ sale.customer_name || 'Walk-in customer' }}</td><td>{{ sale.payment_method }}</td><td>{{ money(sale.total) }}</td><td>{{ sale.status }}</td><td>{{ sale.created_at }}</td></tr><tr v-if="!register.sales.length"><td colspan="6" class="py-10 text-center text-slate-400">No completed POS sales in this shift.</td></tr></tbody></table></div></section>
    </AdminLayout>
</template>
