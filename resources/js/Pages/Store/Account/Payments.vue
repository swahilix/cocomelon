<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ payments: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <AppLayout>
        <Head title="Payment Records"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-5 sm:py-7"><div class="container-shell">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span><span class="text-prinx-600">Payment Records</span></nav>
            <div class="mt-3"><h1 class="text-[26px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[30px]">Payment Records</h1><p class="mt-1 text-xs text-slate-500">Your online order payments and payments recorded at Prinx.</p></div>
            <div class="mt-5"><CustomerAccountNav /></div>

            <section class="mt-4 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                <div class="hidden grid-cols-[1fr_1fr_1fr_1fr_130px_90px] gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-[10px] font-semibold uppercase tracking-[.07em] text-slate-500 md:grid"><span>Reference</span><span>Source</span><span>Method</span><span>Payment ref.</span><span>Amount</span><span>Status</span></div>
                <div v-if="payments.length" class="divide-y divide-slate-100">
                    <div v-for="payment in payments" :key="`${payment.source}-${payment.reference}-${payment.date}`" class="p-4 md:grid md:grid-cols-[1fr_1fr_1fr_1fr_130px_90px] md:items-center md:gap-3">
                        <div><p class="text-xs font-semibold text-slate-900">{{ payment.reference }}</p><p class="mt-1 text-[10px] text-slate-400 md:hidden">{{ date(payment.date) }}</p></div>
                        <p class="mt-2 text-xs text-slate-600 md:mt-0">{{ payment.source }}</p>
                        <p class="mt-1 text-xs capitalize text-slate-600 md:mt-0">{{ payment.method }}</p>
                        <p class="mt-1 break-all text-xs text-slate-500 md:mt-0">{{ payment.payment_reference || '—' }}</p>
                        <strong class="mt-2 block text-sm font-semibold text-slate-950 md:mt-0 md:text-xs">{{ money(payment.amount) }}</strong>
                        <span :class="['mt-2 inline-flex w-fit rounded-[5px] px-2 py-1 text-[10px] font-semibold capitalize md:mt-0', payment.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : payment.status === 'failed' || payment.status === 'refunded' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700']">{{ payment.status }}</span>
                    </div>
                </div>
                <div v-else class="px-5 py-12 text-center"><h2 class="text-base font-semibold text-slate-900">No payment records yet</h2><p class="mt-1 text-xs text-slate-500">Payments linked to your verified phone number will appear here.</p></div>
            </section>
        </div></section>
    </AppLayout>
</template>
