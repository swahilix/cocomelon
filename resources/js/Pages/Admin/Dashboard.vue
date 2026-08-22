<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({ stats: Object, recentOrders: Array, recentSyncs: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(value || 0);
const badge = (status) => status === 'success' || status === 'synced' ? 'bg-emerald-100 text-emerald-700' : status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700';
</script>

<template>
    <AdminLayout>
        <Head title="Admin Dashboard" />

        <div class="overflow-hidden rounded-[10px] bg-slate-950 p-6 text-white shadow-xl sm:p-8">
            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-prinx-400">
                        <Icon name="activity" :size="18" />
                        <p class="text-xs font-black uppercase tracking-[.2em]">Store overview</p>
                    </div>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Welcome back, {{ page.props.auth.user?.name?.split(' ')[0] }}.</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">Review your products, service requests, orders, categories and Google Merchant activity from one place.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/admin/products/create" class="btn-primary"><Icon name="add-circle" :size="17" /> Add product</Link>
                    <Link href="/admin/services/create" class="inline-flex items-center gap-2 rounded-[10px] border border-slate-700 px-4 py-2.5 text-sm font-extrabold hover:border-prinx-400"><Icon name="setting-add" :size="17" /> Add service</Link>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link href="/admin/products" class="metric-card transition hover:-translate-y-1 hover:border-prinx-200"><div class="flex items-start justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-prinx-600">Active products</p><b class="mt-2 block text-3xl font-black">{{ stats.active_products }}</b></div><div class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-prinx-50 text-prinx-600"><Icon name="box" :size="22" /></div></div><p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><Icon name="box-1" :size="14" /> {{ stats.products }} current · {{ stats.deleted_products }} in Trash</p></Link>
            <Link href="/admin/orders" class="metric-card transition hover:-translate-y-1 hover:border-prinx-200"><div class="flex items-start justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-amber-600">Pending orders</p><b class="mt-2 block text-3xl font-black">{{ stats.pending_orders }}</b></div><div class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-amber-50 text-amber-600"><Icon name="receipt-item" :size="22" /></div></div><p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><Icon name="clock" :size="14" /> Orders currently requiring attention</p></Link>
            <Link href="/admin/inquiries" class="metric-card transition hover:-translate-y-1 hover:border-prinx-200"><div class="flex items-start justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-emerald-600">New service requests</p><b class="mt-2 block text-3xl font-black">{{ stats.new_inquiries }}</b></div><div class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-emerald-50 text-emerald-600"><Icon name="message-question" :size="22" /></div></div><p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><Icon name="setting-2" :size="14" /> {{ stats.services }} services · {{ stats.deleted_services }} in Trash</p></Link>
            <Link href="/admin/catalog" class="metric-card transition hover:-translate-y-1 hover:border-prinx-200"><div class="flex items-start justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-slate-500">Categories</p><b class="mt-2 block text-3xl font-black">{{ stats.categories }}</b></div><div class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-slate-100 text-slate-600"><Icon name="category" :size="22" /></div></div><p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><Icon name="element-3" :size="14" /> Product and service navigation</p></Link>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-5">
                <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-emerald-700">Paid revenue</p><b class="mt-2 block text-2xl font-black text-emerald-950">{{ money(stats.revenue) }}</b><p class="mt-1 flex items-center gap-1.5 text-xs text-emerald-700"><Icon name="receipt" :size="14" /> Total recorded paid orders</p></div><span class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-white/80 text-emerald-700"><Icon name="wallet-money" :size="22" /></span></div>
            </div>
            <div :class="['rounded-[10px] border p-5', stats.failed_syncs ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white']">
                <div class="flex items-start justify-between gap-4"><div><p :class="['text-[10px] font-black uppercase tracking-[.15em]', stats.failed_syncs ? 'text-red-600' : 'text-slate-500']">Google Merchant alerts</p><b class="mt-2 block text-2xl font-black">{{ stats.failed_syncs }}</b><p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"><Icon name="refresh-circle" :size="14" /> Products with failed synchronization</p></div><span :class="['flex h-11 w-11 items-center justify-center rounded-[10px]', stats.failed_syncs ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-600']"><Icon :name="stats.failed_syncs ? 'danger' : 'tick-circle'" :size="22" /></span></div>
            </div>
        </div>

        <div class="mt-7 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div class="flex items-start gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-amber-50 text-amber-600"><Icon name="receipt-item" :size="19" /></span><div><h2 class="font-black">Recent orders</h2><p class="mt-1 text-xs text-slate-500">Latest customer purchases.</p></div></div>
                    <Link href="/admin/orders" class="inline-flex items-center gap-1.5 text-sm font-black text-prinx-600">View all <Icon name="arrow-right-3" :size="15" /></Link>
                </div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody><tr v-for="order in recentOrders" :key="order.id"><td><Link :href="`/admin/orders/${order.id}`" class="inline-flex items-center gap-1.5 font-black text-prinx-600"><Icon name="document-text" :size="15" /> {{ order.order_number }}</Link></td><td><span class="inline-flex items-center gap-1.5"><Icon name="user" :size="15" class="text-slate-400" /> {{ order.customer_name }}</span></td><td class="font-bold">{{ money(order.grand_total) }}</td><td><span class="status bg-slate-100 text-slate-700">{{ order.status }}</span></td></tr><tr v-if="!recentOrders.length"><td colspan="4" class="py-12 text-center text-slate-400"><div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-[10px] bg-slate-100"><Icon name="receipt-item" :size="23" /></div>No orders yet.</td></tr></tbody></table></div>
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div class="flex items-start gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="shop" :size="19" /></span><div><h2 class="font-black">Google Merchant activity</h2><p class="mt-1 text-xs text-slate-500">Recent product sync attempts.</p></div></div>
                    <Link href="/admin/merchant" class="inline-flex items-center gap-1.5 text-sm font-black text-prinx-600">Open integration <Icon name="arrow-right-3" :size="15" /></Link>
                </div>
                <div class="divide-y divide-slate-100"><div v-for="log in recentSyncs" :key="log.id" class="flex items-center justify-between gap-3 p-4"><div class="flex min-w-0 items-start gap-3"><span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-slate-100 text-slate-600"><Icon name="refresh-circle" :size="17" /></span><div class="min-w-0"><p class="truncate text-sm font-black">{{ log.product?.name || 'Connection test' }}</p><p class="mt-1 text-xs text-slate-500">{{ log.action }} · {{ new Date(log.created_at).toLocaleString() }}</p></div></div><span :class="['status', badge(log.status)]">{{ log.status }}</span></div><div v-if="!recentSyncs.length" class="p-12 text-center text-sm text-slate-400"><div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-[10px] bg-slate-100"><Icon name="refresh-circle" :size="23" /></div>No sync activity yet.</div></div>
            </section>
        </div>
    </AdminLayout>
</template>