<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PosLayout from '../../../../Layouts/PosLayout.vue';
import Icon from '../../../../Components/Icon.vue';
import Pagination from '../../../../Components/Pagination.vue';

const props = defineProps({
    documents: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    today: { type: String, default: '' },
});

const page = usePage();
const search = ref(props.filters?.search || '');
const type = computed(() => props.filters?.type || '');
const rows = computed(() => props.documents?.data || []);
const filtered = computed(() => Boolean(type.value || props.filters?.search));

const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const shortDate = (value) => (value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
const initials = (name) => String(name || 'W').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

const visit = (params) => router.get('/admin/pos/documents', params, { preserveState: true, preserveScroll: true, replace: true });
const setType = (value) => visit({ type: value || undefined, search: search.value || undefined });
const applySearch = () => visit({ type: type.value || undefined, search: search.value || undefined });
const clearFilters = () => {
    search.value = '';
    visit({});
};

const tabs = computed(() => [
    { value: '', label: 'All', count: Number(props.summary.quotations || 0) + Number(props.summary.invoices || 0) },
    { value: 'quotation', label: 'Quotations', count: Number(props.summary.quotations || 0) },
    { value: 'invoice', label: 'Invoices', count: Number(props.summary.invoices || 0) },
]);

// Validity for quotations, due date for invoices.
const timing = (document) => {
    if (document.type === 'quotation') {
        if (!document.valid_until) return { text: 'No expiry', tone: 'text-slate-400' };
        return document.valid_until >= props.today
            ? { text: `Valid until ${shortDate(document.valid_until)}`, tone: 'text-emerald-600' }
            : { text: `Expired ${shortDate(document.valid_until)}`, tone: 'text-slate-400' };
    }
    if (!document.due_date) return { text: '', tone: '' };
    if (document.due_date === props.today) return { text: 'Due today', tone: 'text-amber-600' };
    return document.due_date > props.today
        ? { text: `Due ${shortDate(document.due_date)}`, tone: 'text-slate-500' }
        : { text: `Due ${shortDate(document.due_date)}`, tone: 'text-slate-400' };
};
const typeStyle = (documentType) => (documentType === 'invoice'
    ? { badge: 'bg-prinx-50 text-prinx-700 ring-prinx-100', icon: 'receipt-item', iconBox: 'bg-prinx-50 text-prinx-600', label: 'Invoice' }
    : { badge: 'bg-blue-50 text-blue-700 ring-blue-100', icon: 'document-text', iconBox: 'bg-blue-50 text-blue-600', label: 'Quotation' });
const open = (document) => router.visit(`/admin/pos/documents/${document.id}`);
</script>

<template>
    <PosLayout>
        <Head title="Quotations & invoices" />

        <div class="mx-auto max-w-6xl px-1 py-3 sm:py-5">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-prinx-600">POS documents</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-[-.02em] text-slate-950">Quotations & invoices</h1>
                    <p class="mt-1 max-w-xl text-sm text-slate-500">Documents you generate from the POS cart. They don't change stock or the cash register.</p>
                </div>
                <Link href="/admin/pos" class="inline-flex h-10 items-center justify-center gap-2 rounded-[9px] bg-prinx-500 px-4 text-sm font-semibold text-white shadow-sm shadow-prinx-500/20 transition hover:bg-prinx-600"><Icon name="add-circle" :size="17" />New document</Link>
            </div>

            <!-- Summary -->
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="relative overflow-hidden rounded-[14px] border border-blue-100 bg-white p-5">
                    <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-blue-50"></div>
                    <div class="relative flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Quotations</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ money(summary.quotation_value) }}</p>
                            <p class="mt-1 text-xs text-slate-500"><b class="text-slate-800">{{ summary.quotations || 0 }}</b> total · <b class="text-emerald-600">{{ summary.quotations_valid || 0 }}</b> still valid</p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] bg-blue-600 text-white"><Icon name="document-text" :size="21" /></span>
                    </div>
                </div>
                <div class="relative overflow-hidden rounded-[14px] border border-prinx-100 bg-white p-5">
                    <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-prinx-50"></div>
                    <div class="relative flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Invoices</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ money(summary.invoice_value) }}</p>
                            <p class="mt-1 text-xs text-slate-500"><b class="text-slate-800">{{ summary.invoices || 0 }}</b> total · <b class="text-prinx-600">{{ money(summary.invoices_this_month) }}</b> this month</p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] bg-prinx-500 text-white"><Icon name="receipt-item" :size="21" /></span>
                    </div>
                </div>
            </div>

            <!-- Tabs + search -->
            <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-full rounded-[11px] border border-slate-200 bg-white p-1 sm:w-auto">
                    <button v-for="tab in tabs" :key="tab.value" type="button" :class="['flex flex-1 items-center justify-center gap-2 rounded-[8px] px-4 py-2 text-sm font-semibold transition sm:flex-none', type === tab.value ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50']" @click="setType(tab.value)">
                        {{ tab.label }}
                        <span :class="['rounded-full px-1.5 py-0.5 text-[10px] leading-none', type === tab.value ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500']">{{ tab.count }}</span>
                    </button>
                </div>
                <form class="flex w-full gap-2 lg:w-[420px]" @submit.prevent="applySearch">
                    <label class="relative min-w-0 flex-1">
                        <Icon name="search-normal" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input v-model="search" type="search" class="h-10 w-full rounded-[9px] border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-prinx-400 focus:ring-2 focus:ring-prinx-100" placeholder="Search reference, customer or phone">
                    </label>
                    <button class="h-10 rounded-[9px] bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Search</button>
                </form>
            </div>

            <!-- List -->
            <section class="mt-3 overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                <template v-if="rows.length">
                    <!-- Desktop table -->
                    <table class="hidden w-full text-sm md:table">
                        <thead class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-[.06em] text-slate-500">
                            <tr><th class="px-5 py-3 text-left">Document</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-right">Items</th><th class="px-4 py-3 text-right">Total</th><th class="w-12 px-4 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="document in rows" :key="document.id" class="group cursor-pointer transition hover:bg-slate-50/80" @click="open(document)">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px]', typeStyle(document.type).iconBox]"><Icon :name="typeStyle(document.type).icon" :size="17" /></span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">{{ document.reference }}</p>
                                            <span :class="['mt-0.5 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset', typeStyle(document.type).badge]">{{ typeStyle(document.type).label }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600">{{ initials(document.customer_name) }}</span>
                                        <div class="min-w-0"><p class="truncate font-medium text-slate-800">{{ document.customer_name || 'Walk-in customer' }}</p><p class="text-xs text-slate-400">{{ document.customer_phone || 'No phone' }}</p></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5"><p class="text-slate-700">{{ shortDate(document.document_date) }}</p><p :class="['text-xs', timing(document).tone]">{{ timing(document).text }}</p></td>
                                <td class="px-4 py-3.5 text-right text-slate-600">{{ document.item_count }}</td>
                                <td class="px-4 py-3.5 text-right"><p class="font-semibold text-slate-950">{{ money(document.total) }}</p><p class="text-xs text-slate-400">by {{ document.created_by_name || 'Staff' }}</p></td>
                                <td class="px-4 py-3.5 text-right"><Link :href="`/admin/pos/documents/${document.id}`" class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] text-slate-400 transition group-hover:bg-white group-hover:text-prinx-600 group-hover:shadow-sm" :aria-label="`Open ${document.reference}`" @click.stop><Icon name="arrow-right-3" :size="16" /></Link></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile cards -->
                    <ul class="divide-y divide-slate-100 md:hidden">
                        <li v-for="document in rows" :key="document.id">
                            <Link :href="`/admin/pos/documents/${document.id}`" class="flex items-start gap-3 px-4 py-3.5 active:bg-slate-50">
                                <span :class="['mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px]', typeStyle(document.type).iconBox]"><Icon :name="typeStyle(document.type).icon" :size="17" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2"><p class="truncate font-semibold text-slate-900">{{ document.customer_name || 'Walk-in customer' }}</p><p class="shrink-0 font-semibold text-slate-950">{{ money(document.total) }}</p></div>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ document.reference }} · {{ shortDate(document.document_date) }}</p>
                                    <div class="mt-1.5 flex items-center gap-2"><span :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset', typeStyle(document.type).badge]">{{ typeStyle(document.type).label }}</span><span :class="['text-[11px]', timing(document).tone]">{{ timing(document).text }}</span></div>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </template>

                <!-- Empty states -->
                <div v-else-if="filtered" class="px-6 py-16 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><Icon name="search-normal" :size="22" /></span>
                    <p class="mt-4 font-semibold text-slate-900">No documents match your search</p>
                    <p class="mt-1 text-sm text-slate-500">Try another reference, customer name or phone number.</p>
                    <button type="button" class="mt-4 inline-flex h-9 items-center rounded-[8px] border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="clearFilters">Clear filters</button>
                </div>
                <div v-else class="px-6 py-16 text-center">
                    <div class="mx-auto flex w-fit items-center">
                        <span class="flex h-14 w-14 -rotate-6 items-center justify-center rounded-[14px] bg-blue-50 text-blue-600 ring-4 ring-white"><Icon name="document-text" :size="26" /></span>
                        <span class="-ml-3 flex h-14 w-14 rotate-6 items-center justify-center rounded-[14px] bg-prinx-50 text-prinx-600 ring-4 ring-white"><Icon name="receipt-item" :size="26" /></span>
                    </div>
                    <p class="mt-5 text-base font-semibold text-slate-900">No quotations or invoices yet</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Add items to the cart in the POS, choose the customer, then tap <b class="text-slate-700">Generate quotation</b> or <b class="text-slate-700">Generate invoice</b>.</p>
                    <Link href="/admin/pos" class="mt-5 inline-flex h-10 items-center gap-2 rounded-[9px] bg-prinx-500 px-4 text-sm font-semibold text-white hover:bg-prinx-600"><Icon name="shopping-cart" :size="16" />Open POS</Link>
                </div>
            </section>

            <Pagination :links="documents.links || []" />
        </div>
    </PosLayout>
</template>
