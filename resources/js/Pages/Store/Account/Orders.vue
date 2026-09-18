<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ orders: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statusClass = (status) => ({ completed: 'bg-emerald-50 text-emerald-700', cancelled: 'bg-red-50 text-red-700', shipped: 'bg-blue-50 text-blue-700', ready: 'bg-amber-50 text-amber-700' }[status] || 'bg-slate-100 text-slate-700');
</script>

<template>
    <AppLayout>
        <Head title="Track Orders"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-5 sm:py-7"><div class="container-shell">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span><span class="text-prinx-600">Track Orders</span></nav>
            <div class="mt-3"><h1 class="text-[26px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[30px]">Track Orders</h1><p class="mt-1 text-xs text-slate-500">Follow your order status from confirmation through delivery.</p></div>
            <div class="mt-5"><CustomerAccountNav /></div>

            <div v-if="orders.length" class="mt-4 space-y-3">
                <article v-for="order in orders" :key="order.id" class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                        <div><p class="text-xs font-semibold text-slate-950">{{ order.order_number }}</p><p class="mt-1 text-[11px] text-slate-500">Placed {{ date(order.created_at) }}</p></div>
                        <span :class="['rounded-[5px] px-2 py-1 text-[10px] font-semibold capitalize', statusClass(order.status)]">{{ order.status }}</span>
                    </div>
                    <div class="grid gap-3 px-4 py-3.5 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                        <div class="min-w-0"><p class="text-[11px] font-semibold uppercase tracking-[.08em] text-slate-400">Items</p><p class="mt-1 line-clamp-2 text-xs text-slate-700">{{ order.items?.map(item => `${item.product_name}${item.variant_name ? ' — ' + item.variant_name : ''} × ${item.quantity}`).join(', ') || 'Order items' }}</p></div>
                        <div class="sm:text-right"><p class="text-[11px] text-slate-400">Total</p><strong class="mt-1 block text-sm font-semibold text-slate-950">{{ money(order.grand_total) }}</strong></div>
                        <Link :href="`/account/orders/${order.order_number}`" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-prinx-300 bg-white px-4 text-xs font-semibold text-prinx-600 transition hover:bg-prinx-50">View tracking</Link>
                    </div>
                </article>
            </div>
            <div v-else class="mt-4 rounded-[9px] border border-slate-200 bg-white px-5 py-12 text-center"><h2 class="text-base font-semibold text-slate-900">No orders found</h2><p class="mt-1 text-xs text-slate-500">Orders associated with your verified phone number will appear here.</p><Link href="/shop" class="mt-4 inline-flex h-9 items-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white">Shop products</Link></div>
        </div></section>
    </AppLayout>
</template>
