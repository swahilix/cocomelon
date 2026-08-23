<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import PosLayout from '../../../../Layouts/PosLayout.vue';
import Icon from '../../../../Components/Icon.vue';

const props = defineProps({ documents: Array, filters: Object, summary: Object });
const page = usePage();
const search = ref(props.filters?.search || '');
const type = ref(props.filters?.type || '');
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const applyFilters = () => router.get('/admin/pos/documents', { search: search.value || undefined, type: type.value || undefined }, { preserveState: true, replace: true });
</script>

<template>
    <PosLayout>
        <Head title="POS Documents" />

        <template #header-actions>
            <Link href="/admin/pos" class="flex h-9 items-center gap-1.5 rounded-[7px] px-2.5 text-xs font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white"><Icon name="shopping-cart" :size="16" />New sale</Link>
        </template>

        <div class="mx-auto max-w-6xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">POS documents</p><h1 class="mt-1 text-xl font-semibold text-slate-950">Quotations & invoices</h1><p class="mt-1 text-xs text-slate-500">Commercial documents created from the POS without changing stock or register balances.</p></div>
                <Link href="/admin/pos" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-[8px] bg-prinx-500 px-3 text-xs font-semibold text-white hover:bg-prinx-600"><Icon name="add-circle" :size="15" />Create from POS</Link>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-[.08em] text-slate-400">Quotations</p><b class="mt-1 block text-lg">{{ summary.quotations }}</b></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-[.08em] text-slate-400">Quotation value</p><b class="mt-1 block text-lg">{{ money(summary.quotation_value) }}</b></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-[.08em] text-slate-400">Invoices</p><b class="mt-1 block text-lg">{{ summary.invoices }}</b></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[10px] uppercase tracking-[.08em] text-slate-400">Invoice value</p><b class="mt-1 block text-lg">{{ money(summary.invoice_value) }}</b></div>
            </div>

            <form class="mt-4 flex flex-col gap-2 rounded-[8px] border border-slate-200 bg-white p-3 sm:flex-row" @submit.prevent="applyFilters">
                <div class="relative min-w-0 flex-1"><Icon name="search-normal" :size="15" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="input pl-8" placeholder="Reference, customer or phone"></div>
                <select v-model="type" class="input sm:w-44"><option value="">All documents</option><option value="quotation">Quotations</option><option value="invoice">Invoices</option></select>
                <button class="btn-primary h-9 px-4">Filter</button>
            </form>

            <div class="mt-3 overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-xs">
                        <thead class="bg-slate-950 text-white"><tr><th class="px-3 py-2 text-left">Reference</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Customer</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-left">Created by</th><th class="w-16 px-3 py-2"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="document in documents" :key="document.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2.5 font-semibold text-slate-900">{{ document.reference }}</td>
                                <td class="px-3 py-2.5"><span :class="['rounded-[6px] px-2 py-1 text-[10px] font-semibold capitalize', document.type === 'invoice' ? 'bg-prinx-50 text-prinx-700' : 'bg-blue-50 text-blue-700']">{{ document.type }}</span></td>
                                <td class="px-3 py-2.5"><p class="font-medium text-slate-800">{{ document.customer_name || 'Walk-in customer' }}</p><p class="text-[10px] text-slate-400">{{ document.customer_phone || '—' }}</p></td>
                                <td class="px-3 py-2.5 text-slate-600">{{ document.document_date }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold">{{ money(document.total) }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ document.created_by_name || 'Staff' }}</td>
                                <td class="px-3 py-2.5 text-right"><Link :href="`/admin/pos/documents/${document.id}`" class="inline-flex h-8 w-8 items-center justify-center rounded-[7px] border border-slate-200 text-slate-600 hover:border-prinx-300 hover:text-prinx-600"><Icon name="eye" :size="14" /></Link></td>
                            </tr>
                            <tr v-if="!documents.length"><td colspan="7" class="py-14 text-center text-xs text-slate-400">No quotations or invoices found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </PosLayout>
</template>
