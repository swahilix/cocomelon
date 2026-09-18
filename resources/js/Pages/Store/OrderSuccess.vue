<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Icon.vue';

defineProps({ order: Object });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(value || 0);
</script>

<template>
    <AppLayout>
        <Head title="Order Received"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="container-shell max-w-3xl py-16">
            <div class="card overflow-hidden">
                <div class="bg-emerald-600 p-8 text-center text-white">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-[10px] bg-white/20"><Icon name="tick-circle" :size="34" /></div>
                    <h1 class="mt-4 text-3xl font-black">Order received</h1>
                    <p class="mt-2 text-emerald-50">Thank you. The Prinx Tech team will contact you to confirm the order.</p>
                </div>
                <div class="p-6 sm:p-8">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div><span class="text-xs font-bold uppercase text-slate-400">Order number</span><b class="mt-1 block">{{ order.order_number }}</b></div>
                        <div><span class="text-xs font-bold uppercase text-slate-400">Total</span><b class="mt-1 block">{{ money(order.grand_total) }}</b></div>
                        <div><span class="text-xs font-bold uppercase text-slate-400">Status</span><b class="mt-1 block capitalize">{{ order.status }}</b></div>
                        <div><span class="text-xs font-bold uppercase text-slate-400">Fulfillment</span><b class="mt-1 block capitalize">{{ order.fulfillment_method === 'pickup' ? 'Store pickup' : 'Delivery' }}</b></div>
                    </div>
                    <div v-if="order.fulfillment_method === 'pickup'" class="mt-5 rounded-[7px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Your order is for <strong>store pickup</strong>. We will contact you when it is ready to collect.
                    </div>

                    <div v-if="Number(order.discount_total || 0) > 0" class="mt-5 flex items-center justify-between gap-4 rounded-[7px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                        <span class="text-emerald-800">Coupon discount <strong v-if="order.coupon_code">({{ order.coupon_code }})</strong></span>
                        <strong class="text-emerald-700">-{{ money(order.discount_total) }}</strong>
                    </div>

                    <div class="mt-7 border-t border-slate-200 pt-6">
                        <h2 class="font-black">Products</h2>
                        <div class="mt-4 space-y-3"><div v-for="item in order.items" :key="item.id" class="flex justify-between gap-4 text-sm"><span>{{ item.product_name }} × {{ item.quantity }}</span><b>{{ money(item.line_total) }}</b></div></div>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-3"><Link href="/shop" class="btn-primary">Continue shopping</Link><Link href="/pages/contact" class="btn-light">Contact support</Link></div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
