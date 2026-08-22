<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({ stats: Object, recentOrders: Array, recentSyncs: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const badge = (status) => status === 'success' || status === 'synced' ? 'bg-emerald-100 text-emerald-700' : status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700';
</script>

<template>
    <AdminLayout>
        <Head title="Admin Dashboard" />

        <div class="overflow-hidden rounded-[10px] bg-slate-950 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div><div class="flex items-center gap-2 text-prinx-400"><Icon name="activity" :size="18" /><p class="text-xs font-semibold uppercase tracking-[.2em]">Store overview</p></div><h1 class="mt-3 text-3xl font-bold tracking-tight">Welcome back, {{ page.props.auth.user?.name?.split(' ')[0] }}.</h1><p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">Monitor sales, profit, stock, purchasing and daily operations from one place.</p></div>
                <div class="flex flex-wrap gap-2"><Link href="/admin/pos" class="btn-primary"><Icon name="shop" :size="17" /> Open POS</Link><Link href="/admin/reports" class="inline-flex items-center gap-2 rounded-[10px] border border-slate-700 px-4 py-2.5 text-sm font-semibold hover:border-prinx-400"><Icon name="chart" :size="17" /> Reports</Link></div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link href="/admin/sales" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Today's sales</p><b class="mt-2 block text-2xl">{{ money(stats.today_sales) }}</b><p class="mt-1 text-xs text-slate-500">POS {{ money(stats.today_pos_revenue) }} · Online {{ money(stats.today_online_revenue) }}</p></Link>
            <Link href="/admin/profit" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Gross profit today</p><b class="mt-2 block text-2xl">{{ money(stats.today_gross_profit) }}</b><p class="mt-1 text-xs text-slate-500">Before operating expenses</p></Link>
            <Link href="/admin/expenses" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Expenses today</p><b class="mt-2 block text-2xl">{{ money(stats.today_expenses) }}</b><p class="mt-1 text-xs text-slate-500">Posted expenses</p></Link>
            <Link href="/admin/profit" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-500">Net result today</p><b :class="['mt-2 block text-2xl', Number(stats.today_net_result) < 0 ? 'text-red-600' : '']">{{ money(stats.today_net_result) }}</b><p class="mt-1 text-xs text-slate-500">Gross profit less expenses</p></Link>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link href="/admin/stock/inventory" class="card p-4"><p class="text-xs text-slate-500">Inventory value</p><b class="mt-1 block text-lg">{{ money(stats.inventory_value) }}</b></Link>
            <Link href="/admin/stock/inventory" class="card p-4"><p class="text-xs text-slate-500">Low / out of stock</p><b class="mt-1 block text-lg">{{ stats.low_stock_products }} / {{ stats.out_of_stock_products }}</b></Link>
            <Link href="/admin/stock" class="card p-4"><p class="text-xs text-slate-500">Purchases today</p><b class="mt-1 block text-lg">{{ money(stats.today_purchases) }}</b></Link>
            <Link href="/admin/suppliers" class="card p-4"><p class="text-xs text-slate-500">Supplier balance</p><b class="mt-1 block text-lg">{{ money(stats.supplier_balance) }}</b></Link>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link href="/admin/products" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Active products</p><b class="mt-2 block text-3xl">{{ stats.active_products }}</b><p class="mt-1 text-xs text-slate-500">{{ stats.products }} current · {{ stats.deleted_products }} trash</p></Link>
            <Link href="/admin/orders" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Pending orders</p><b class="mt-2 block text-3xl">{{ stats.pending_orders }}</b><p class="mt-1 text-xs text-slate-500">Require attention</p></Link>
            <Link href="/admin/inquiries" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Service requests</p><b class="mt-2 block text-3xl">{{ stats.new_inquiries }}</b><p class="mt-1 text-xs text-slate-500">New requests</p></Link>
            <Link href="/admin/merchant" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-500">Merchant alerts</p><b class="mt-2 block text-3xl">{{ stats.failed_syncs }}</b><p class="mt-1 text-xs text-slate-500">Failed syncs</p></Link>
        </div>

        <div class="mt-7 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-semibold">Recent orders</h2><p class="mt-1 text-xs text-slate-500">Latest online customer purchases.</p></div><Link href="/admin/orders" class="text-sm font-semibold text-prinx-600">View all</Link></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody><tr v-for="order in recentOrders" :key="order.id"><td><Link :href="`/admin/orders/${order.id}`" class="font-semibold text-prinx-600">{{ order.order_number }}</Link></td><td>{{ order.customer_name }}</td><td class="font-semibold">{{ money(order.grand_total) }}</td><td><span class="status bg-slate-100 text-slate-700">{{ order.status }}</span></td></tr><tr v-if="!recentOrders.length"><td colspan="4" class="py-12 text-center text-slate-400">No orders yet.</td></tr></tbody></table></div>
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-semibold">Google Merchant activity</h2><p class="mt-1 text-xs text-slate-500">Recent product sync attempts.</p></div><Link href="/admin/merchant" class="text-sm font-semibold text-prinx-600">Open integration</Link></div>
                <div class="divide-y divide-slate-100"><div v-for="log in recentSyncs" :key="log.id" class="flex items-center justify-between gap-3 p-4"><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ log.product?.name || 'Connection test' }}</p><p class="mt-1 text-xs text-slate-500">{{ log.action }} · {{ new Date(log.created_at).toLocaleString() }}</p></div><span :class="['status', badge(log.status)]">{{ log.status }}</span></div><div v-if="!recentSyncs.length" class="p-12 text-center text-sm text-slate-400">No sync activity yet.</div></div>
            </section>
        </div>
    </AdminLayout>
</template>
