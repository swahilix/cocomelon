<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ order: Object });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const dateTime = (value) => value ? new Date(value).toLocaleString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const stages = ['pending', 'confirmed', 'processing', 'ready', 'shipped', 'completed'];
const stageIndex = stages.indexOf(props.order.status);
const reached = (stage) => props.order.status !== 'cancelled' && stages.indexOf(stage) <= stageIndex;
</script>

<template>
    <AppLayout>
        <Head :title="`Order ${order.order_number}`"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-5 sm:py-7"><div class="container-shell">
            <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span><Link href="/account/orders" class="hover:text-prinx-600">Orders</Link><span>/</span><span class="text-prinx-600">{{ order.order_number }}</span></nav>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-[24px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[28px]">{{ order.order_number }}</h1><p class="mt-1 text-xs text-slate-500">Placed {{ dateTime(order.created_at) }}</p></div><span :class="['rounded-[6px] px-2.5 py-1.5 text-xs font-semibold capitalize', order.status === 'cancelled' ? 'bg-red-50 text-red-700' : 'bg-prinx-50 text-prinx-700']">{{ order.status }}</span></div>
            <div class="mt-5"><CustomerAccountNav /></div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_330px]">
                <div class="space-y-4">
                    <section class="rounded-[9px] border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3"><div><h2 class="text-sm font-semibold text-slate-950">Order progress</h2><p class="mt-1 text-[11px] text-slate-500">Latest progress for this order</p></div></div>
                        <div v-if="order.status !== 'cancelled'" class="mt-4 grid grid-cols-3 gap-y-4 sm:grid-cols-6">
                            <div v-for="stage in stages" :key="stage" class="relative flex flex-col items-center text-center">
                                <span :class="['relative z-10 flex h-7 w-7 items-center justify-center rounded-full border text-[10px] font-semibold', reached(stage) ? 'border-prinx-500 bg-prinx-500 text-white' : 'border-slate-200 bg-white text-slate-400']">✓</span>
                                <span :class="['mt-1.5 text-[10px] font-medium capitalize', reached(stage) ? 'text-slate-800' : 'text-slate-400']">{{ stage }}</span>
                            </div>
                        </div>
                        <div v-else class="mt-4 rounded-[7px] bg-red-50 px-3 py-2.5 text-xs text-red-700">This order was cancelled.</div>
                    </section>

                    <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Order items</h2></div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="item in order.items" :key="item.id" class="grid grid-cols-[1fr_auto] gap-4 px-4 py-3.5">
                                <div><p class="text-xs font-semibold text-slate-900">{{ item.product_name }}</p><p v-if="item.variant_name" class="mt-1 text-[11px] font-medium text-prinx-600">{{ item.variant_name }}</p><p class="mt-1 text-[11px] text-slate-500">{{ item.variant_sku || item.sku }} · Qty {{ item.quantity }} × {{ money(item.unit_price) }}</p></div><strong class="text-xs font-semibold text-slate-900">{{ money(item.line_total) }}</strong>
                            </div>
                        </div>
                    </section>

                    <section v-if="order.status_history?.length" class="rounded-[9px] border border-slate-200 bg-white p-4">
                        <h2 class="text-sm font-semibold text-slate-950">Status history</h2>
                        <div class="mt-3 space-y-3">
                            <div v-for="entry in order.status_history" :key="entry.id" class="flex gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-prinx-500"></span><div><p class="text-xs font-semibold capitalize text-slate-800">{{ entry.status }} · {{ entry.payment_status }}</p><p v-if="entry.note" class="mt-1 text-[11px] leading-5 text-slate-500">{{ entry.note }}</p><p class="mt-1 text-[10px] text-slate-400">{{ dateTime(entry.created_at) }}</p></div></div>
                        </div>
                    </section>
                </div>

                <aside class="h-fit rounded-[9px] border border-slate-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-slate-950">Order summary</h2>
                    <div class="mt-4 space-y-3 text-xs"><div class="flex justify-between gap-3"><span class="text-slate-500">Subtotal</span><strong>{{ money(order.subtotal) }}</strong></div><div class="flex justify-between gap-3"><span class="text-slate-500">Shipping</span><strong>{{ money(order.shipping_total) }}</strong></div><div v-if="Number(order.discount_total)" class="flex justify-between gap-3 text-emerald-700"><span>Discount</span><strong>-{{ money(order.discount_total) }}</strong></div><div class="flex justify-between gap-3 border-t border-slate-100 pt-3"><span class="font-semibold text-slate-900">Total</span><strong class="text-prinx-600">{{ money(order.grand_total) }}</strong></div></div>
                    <div class="mt-4 border-t border-slate-100 pt-4"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Payment</p><p class="mt-1 text-xs font-semibold capitalize text-slate-800">{{ order.payment_method.replaceAll('_', ' ') }}</p><p class="mt-1 text-[11px] capitalize" :class="order.payment_status === 'paid' ? 'text-emerald-600' : 'text-slate-500'">{{ order.payment_status }}</p></div>
                    <Link href="/account/invoices" class="mt-4 flex h-9 items-center justify-center rounded-[6px] border border-prinx-300 text-xs font-semibold text-prinx-600 hover:bg-prinx-50">View invoices</Link>
                </aside>
            </div>
        </div></section>
    </AppLayout>
</template>
