<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PosLayout from '../../../../Layouts/PosLayout.vue';
import Icon from '../../../../Components/Icon.vue';

const props = defineProps({ document: Object, items: Array });
const page = usePage();
const store = page.props.store || {};
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const printDocument = () => window.print();
</script>

<template>
    <PosLayout>
        <Head :title="`${document.type === 'quotation' ? 'Quotation' : 'Invoice'} ${document.reference}`" />

        <template #header-actions>
            <Link href="/admin/pos/documents" class="no-print flex h-9 items-center gap-1.5 rounded-[7px] px-2.5 text-xs font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white"><Icon name="document-text" :size="16" />Documents</Link>
        </template>

        <div class="no-print mx-auto mb-3 flex max-w-5xl items-center justify-between gap-3">
            <Link href="/admin/pos/documents" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-prinx-600"><Icon name="arrow-left-2" :size="15" />Back to documents</Link>
            <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-[8px] bg-prinx-500 px-3 text-xs font-semibold text-white hover:bg-prinx-600" @click="printDocument"><Icon name="printer" :size="15" />Print {{ document.type }}</button>
        </div>

        <article class="document-sheet mx-auto max-w-5xl bg-white p-6 shadow-sm sm:p-8 lg:p-10">
            <div class="flex flex-col gap-6 border-b border-slate-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <img src="/assets/prinx-logo.png" :alt="store.name" class="h-11 w-auto object-contain">
                    <p class="mt-3 max-w-md text-sm leading-6 text-slate-500">{{ store.description }}</p>
                    <div class="mt-3 space-y-1 text-xs text-slate-500">
                        <p v-if="store.phone"><span class="font-semibold text-slate-700">Phone:</span> {{ store.phone }}</p>
                        <p v-if="store.email"><span class="font-semibold text-slate-700">Email:</span> {{ store.email }}</p>
                        <p v-if="store.address"><span class="font-semibold text-slate-700">Address:</span> {{ [store.address, store.city, store.region, store.country].filter(Boolean).join(', ') }}</p>
                    </div>
                </div>

                <div class="sm:text-right">
                    <p class="text-[11px] font-semibold uppercase tracking-[.18em] text-prinx-600">{{ document.type === 'quotation' ? 'Quotation' : 'Invoice' }}</p>
                    <h1 class="mt-1 text-xl font-semibold text-slate-950">{{ document.reference }}</h1>
                    <div class="mt-3 space-y-1 text-xs text-slate-500">
                        <p><span class="font-semibold text-slate-700">Date:</span> {{ document.document_date }}</p>
                        <p v-if="document.type === 'quotation' && document.valid_until"><span class="font-semibold text-slate-700">Valid until:</span> {{ document.valid_until }}</p>
                        <p v-if="document.type === 'invoice' && document.due_date"><span class="font-semibold text-slate-700">Due date:</span> {{ document.due_date }}</p>
                        <p><span class="font-semibold text-slate-700">Prepared by:</span> {{ document.created_by_name || 'Staff' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 border-b border-slate-200 py-6 sm:grid-cols-2">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Prepared for</p>
                    <p class="mt-2 text-base font-semibold text-slate-950">{{ document.customer_name || 'Walk-in customer' }}</p>
                    <p v-if="document.customer_phone" class="mt-1 text-sm text-slate-500">{{ document.customer_phone }}</p>
                    <p v-if="document.customer_email" class="mt-1 text-sm text-slate-500">{{ document.customer_email }}</p>
                </div>
                <div v-if="document.notes" class="sm:text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Notes</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ document.notes }}</p>
                </div>
            </div>

            <div class="mt-6 overflow-hidden border border-slate-200">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-slate-950 text-white"><tr><th class="px-3 py-2.5 text-left text-xs font-semibold">Item</th><th class="w-20 px-3 py-2.5 text-center text-xs font-semibold">Qty</th><th class="w-36 px-3 py-2.5 text-right text-xs font-semibold">Unit price</th><th class="w-36 px-3 py-2.5 text-right text-xs font-semibold">Amount</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items" :key="item.id"><td class="px-3 py-3"><p class="font-semibold text-slate-900">{{ item.product_name }}</p><p class="mt-0.5 text-[11px] text-slate-400">{{ item.sku || '—' }}</p></td><td class="px-3 py-3 text-center text-slate-600">{{ item.quantity }}</td><td class="px-3 py-3 text-right text-slate-600">{{ money(item.unit_price) }}</td><td class="px-3 py-3 text-right font-semibold text-slate-900">{{ money(item.line_total) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 ml-auto w-full max-w-sm space-y-2 text-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Subtotal</span><span class="font-medium text-slate-800">{{ money(document.subtotal) }}</span></div>
                <div v-if="Number(document.discount) > 0" class="flex items-center justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Discount</span><span class="font-medium text-slate-800">− {{ money(document.discount) }}</span></div>
                <div class="flex items-center justify-between pt-1"><span class="font-semibold text-slate-900">Total</span><span class="text-xl font-semibold text-prinx-600">{{ money(document.total) }}</span></div>
            </div>

            <div class="mt-10 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500">
                <p v-if="document.type === 'quotation'">This quotation is a commercial offer and does not reserve stock or record payment until a sale is completed.</p>
                <p v-else>This invoice records the amount requested from the customer. It does not by itself confirm payment or reduce POS stock.</p>
                <p class="mt-2">Thank you for choosing {{ store.name }}.</p>
            </div>
        </article>
    </PosLayout>
</template>

<style scoped>
@media print {
    :global(body) { background: #fff !important; }
    :global(.pos-shell > header), .no-print { display: none !important; }
    :global(.pos-shell), :global(.pos-shell main) { background: #fff !important; margin: 0 !important; padding: 0 !important; max-width: none !important; }
    .document-sheet { width: 100% !important; max-width: none !important; min-height: 0 !important; padding: 12mm !important; box-shadow: none !important; }
    @page { size: A4; margin: 0; }
}
</style>
