<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ summary: Object, recentOrders: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store?.currency || 'TZS',
    maximumFractionDigits: 0,
}).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <AppLayout>
        <Head title="My Account"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>

        <section class="bg-[#fbfbfb] py-5 sm:py-7">
            <div class="container-shell">
                <nav class="flex items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><span class="text-prinx-600">My Account</span></nav>
                <div class="mt-3"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Customer portal</p><h1 class="mt-1 text-[26px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[30px]">My Prinx Account</h1><p class="mt-1 text-xs text-slate-500">Track your purchases, payments and invoices without leaving the Prinx store experience.</p></div>

                <div class="mt-5"><CustomerAccountNav /></div>

                <div class="mt-4 grid grid-cols-2 gap-2.5 lg:grid-cols-5">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3.5"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Orders</p><strong class="mt-1.5 block text-xl font-semibold text-slate-950">{{ summary.orders || 0 }}</strong></div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3.5"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Active orders</p><strong class="mt-1.5 block text-xl font-semibold text-prinx-600">{{ summary.active_orders || 0 }}</strong></div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3.5"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Payments</p><strong class="mt-1.5 block text-xl font-semibold text-slate-950">{{ summary.payments || 0 }}</strong></div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3.5"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Paid total</p><strong class="mt-1.5 block text-sm font-semibold text-emerald-700 sm:text-base">{{ money(summary.paid_total) }}</strong></div>
                    <div class="col-span-2 rounded-[8px] border border-slate-200 bg-white p-3.5 lg:col-span-1"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Invoices</p><strong class="mt-1.5 block text-xl font-semibold text-slate-950">{{ summary.invoices || 0 }}</strong></div>
                </div>

                <section class="mt-4 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5"><div><h2 class="text-sm font-semibold text-slate-950">Recent orders</h2><p class="mt-0.5 text-[11px] text-slate-500">Your latest online orders</p></div><Link href="/account/orders" class="text-xs font-semibold text-prinx-600 hover:text-prinx-700">View all</Link></div>
                    <div v-if="recentOrders.length" class="divide-y divide-slate-100">
                        <Link v-for="order in recentOrders" :key="order.id" :href="`/account/orders/${order.order_number}`" class="flex items-center justify-between gap-4 px-4 py-3.5 transition hover:bg-slate-50">
                            <div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-900">{{ order.order_number }}</p><p class="mt-1 text-[11px] text-slate-500">{{ date(order.created_at) }} · <span class="capitalize">{{ order.status }}</span></p></div>
                            <div class="text-right"><strong class="block text-xs font-semibold text-slate-900">{{ money(order.grand_total) }}</strong><span class="mt-1 block text-[10px] capitalize" :class="order.payment_status === 'paid' ? 'text-emerald-600' : 'text-slate-500'">{{ order.payment_status }}</span></div>
                        </Link>
                    </div>
                    <div v-else class="px-4 py-10 text-center"><p class="text-sm font-semibold text-slate-700">No orders yet</p><p class="mt-1 text-xs text-slate-500">Orders placed with this phone number will appear here.</p><Link href="/shop" class="mt-4 inline-flex h-9 items-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white">Start shopping</Link></div>
                </section>
            </div>
        </section>
    </AppLayout>
</template>
