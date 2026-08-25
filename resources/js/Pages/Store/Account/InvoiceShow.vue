<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ invoice: Object });
const page = usePage();
const store = page.props.store || {};
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: store.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const printInvoice = () => window.print();
</script>

<template>
    <AppLayout>
        <Head :title="`Invoice ${invoice.reference}`"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-5 sm:py-7 print:bg-white print:py-0"><div class="container-shell print:max-w-none print:px-0">
            <div class="print:hidden">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span><Link href="/account/invoices" class="hover:text-prinx-600">Invoices</Link><span>/</span><span class="text-prinx-600">{{ invoice.reference }}</span></nav>
                <div class="mt-3 flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-[24px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[28px]">Invoice {{ invoice.reference }}</h1><p class="mt-1 text-xs text-slate-500">Issued {{ date(invoice.date) }}</p></div><button type="button" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white hover:bg-prinx-600" @click="printInvoice">Print invoice</button></div>
                <div class="mt-5"><CustomerAccountNav /></div>
            </div>

            <article class="mx-auto mt-4 max-w-4xl overflow-hidden rounded-[9px] border border-slate-200 bg-white print:mt-0 print:max-w-none print:rounded-none print:border-0">
                <div class="flex flex-wrap items-start justify-between gap-5 border-b border-slate-100 p-5 sm:p-6">
                    <div><img src="/assets/prinx-logo.png" :alt="store.name" class="h-10 w-auto max-w-[160px] object-contain"><p class="mt-3 max-w-xs text-[11px] leading-5 text-slate-500">{{ store.address || 'Tanzania' }}<br v-if="store.address"><span v-if="store.phone">{{ store.phone }}</span><span v-if="store.email"> · {{ store.email }}</span></p></div>
                    <div class="text-right"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Invoice</p><h2 class="mt-1 text-lg font-semibold text-slate-950">{{ invoice.reference }}</h2><p class="mt-2 text-[11px] text-slate-500">Date: {{ date(invoice.date) }}</p><p v-if="invoice.due_date" class="mt-1 text-[11px] text-slate-500">Due: {{ date(invoice.due_date) }}</p><span class="mt-2 inline-flex rounded-[5px] bg-slate-100 px-2 py-1 text-[10px] font-semibold capitalize text-slate-700">{{ invoice.status }}</span></div>
                </div>

                <div class="grid gap-5 border-b border-slate-100 p-5 sm:grid-cols-2 sm:p-6">
                    <div><p class="text-[10px] font-semibold uppercase tracking-[.1em] text-slate-400">Billed to</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ invoice.customer_name || 'Prinx Customer' }}</p><p v-if="invoice.customer_phone" class="mt-1 text-xs text-slate-500">{{ invoice.customer_phone }}</p><p v-if="invoice.customer_email && !invoice.customer_email.endsWith('@phone.prinx.local')" class="mt-1 text-xs text-slate-500">{{ invoice.customer_email }}</p></div>
                    <div v-if="invoice.payment_method" class="sm:text-right"><p class="text-[10px] font-semibold uppercase tracking-[.1em] text-slate-400">Payment method</p><p class="mt-2 text-sm font-semibold capitalize text-slate-900">{{ invoice.payment_method.replaceAll('_', ' ') }}</p></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[.07em] text-slate-500"><tr><th class="px-5 py-3 sm:px-6">Item</th><th class="px-3 py-3">Qty</th><th class="px-3 py-3">Unit price</th><th class="px-5 py-3 text-right sm:px-6">Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><tr v-for="(item, index) in invoice.items" :key="`${item.sku}-${index}`"><td class="px-5 py-3.5 sm:px-6"><p class="font-semibold text-slate-900">{{ item.name }}</p><p class="mt-1 text-[10px] text-slate-400">{{ item.sku || '—' }}</p></td><td class="px-3 py-3.5 text-slate-600">{{ item.quantity }}</td><td class="px-3 py-3.5 text-slate-600">{{ money(item.unit_price) }}</td><td class="px-5 py-3.5 text-right font-semibold text-slate-900 sm:px-6">{{ money(item.line_total) }}</td></tr></tbody>
                    </table>
                </div>

                <div class="flex justify-end border-t border-slate-100 p-5 sm:p-6"><div class="w-full max-w-[310px] space-y-2.5 text-xs"><div class="flex justify-between gap-4"><span class="text-slate-500">Subtotal</span><strong>{{ money(invoice.subtotal) }}</strong></div><div v-if="Number(invoice.shipping)" class="flex justify-between gap-4"><span class="text-slate-500">Shipping</span><strong>{{ money(invoice.shipping) }}</strong></div><div v-if="Number(invoice.discount)" class="flex justify-between gap-4 text-emerald-700"><span>Discount</span><strong>-{{ money(invoice.discount) }}</strong></div><div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-sm"><span class="font-semibold text-slate-950">Total</span><strong class="text-prinx-600">{{ money(invoice.total) }}</strong></div></div></div>
            </article>
        </div></section>
    </AppLayout>
</template>
