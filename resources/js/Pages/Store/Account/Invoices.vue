<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ invoices: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <AppLayout>
        <Head title="Invoices"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-5 sm:py-7"><div class="container-shell">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span><span class="text-prinx-600">Invoices</span></nav>
            <div class="mt-3"><h1 class="text-[26px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[30px]">Invoices</h1><p class="mt-1 text-xs text-slate-500">View invoices from online orders and invoices issued at Prinx.</p></div>
            <div class="mt-5"><CustomerAccountNav /></div>

            <section class="mt-4 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                <div class="hidden grid-cols-[1fr_120px_130px_100px_110px] gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-[10px] font-semibold uppercase tracking-[.07em] text-slate-500 sm:grid"><span>Invoice</span><span>Date</span><span>Total</span><span>Status</span><span></span></div>
                <div v-if="invoices.length" class="divide-y divide-slate-100">
                    <div v-for="invoice in invoices" :key="`${invoice.source}-${invoice.id}`" class="p-4 sm:grid sm:grid-cols-[1fr_120px_130px_100px_110px] sm:items-center sm:gap-3">
                        <div class="min-w-0"><div class="flex items-center gap-2"><p class="truncate text-xs font-semibold text-slate-900">{{ invoice.reference }}</p><span class="rounded-[4px] bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-slate-500">{{ invoice.source === 'pos' ? 'Store' : 'Online' }}</span></div></div>
                        <p class="mt-2 text-xs text-slate-500 sm:mt-0">{{ date(invoice.date) }}</p>
                        <strong class="mt-2 block text-sm font-semibold text-slate-950 sm:mt-0 sm:text-xs">{{ money(invoice.total) }}</strong>
                        <span :class="['mt-2 inline-flex w-fit rounded-[5px] px-2 py-1 text-[10px] font-semibold capitalize sm:mt-0', invoice.status === 'paid' || invoice.status === 'issued' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">{{ invoice.status }}</span>
                        <Link :href="invoice.url" class="mt-3 inline-flex h-8 items-center justify-center rounded-[6px] border border-prinx-300 bg-white px-3 text-[11px] font-semibold text-prinx-600 transition hover:bg-prinx-50 sm:mt-0">View invoice</Link>
                    </div>
                </div>
                <div v-else class="px-5 py-12 text-center"><h2 class="text-base font-semibold text-slate-900">No invoices yet</h2><p class="mt-1 text-xs text-slate-500">Invoices linked to your verified phone number will appear here.</p></div>
            </section>
        </div></section>
    </AppLayout>
</template>
