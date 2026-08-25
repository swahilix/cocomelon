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
const cleanEmail = (value) => value && !value.endsWith('@phone.prinx.local') ? value : null;
const label = (value) => String(value || '').replaceAll('_', ' ');
</script>

<template>
    <AppLayout>
        <Head :title="`Invoice ${invoice.reference}`"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>

        <section class="invoice-page bg-[#fbfbfb] py-5 sm:py-7 print:bg-white print:py-0">
            <div class="container-shell print:max-w-none print:px-0">
                <div class="print:hidden">
                    <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                        <Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span>
                        <Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span>
                        <Link href="/account/invoices" class="hover:text-prinx-600">Invoices</Link><span>/</span>
                        <span class="text-prinx-600">{{ invoice.reference }}</span>
                    </nav>
                    <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h1 class="text-[24px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[28px]">Invoice {{ invoice.reference }}</h1>
                            <p class="mt-1 text-xs text-slate-500">Issued {{ date(invoice.date) }}</p>
                        </div>
                        <button type="button" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white hover:bg-prinx-600" @click="printInvoice">Print invoice</button>
                    </div>
                    <div class="mt-5"><CustomerAccountNav /></div>
                </div>

                <article class="invoice-sheet mx-auto mt-4 max-w-5xl bg-white p-5 shadow-sm sm:p-8 lg:p-10 print:mt-0 print:max-w-none print:p-0 print:shadow-none">
                    <div class="flex flex-col gap-6 border-b border-slate-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <img src="/assets/prinx-logo.png" :alt="store.name" class="h-11 w-auto max-w-[180px] object-contain">
                            <p v-if="store.description" class="mt-3 max-w-md text-sm leading-6 text-slate-500">{{ store.description }}</p>
                            <div class="mt-3 space-y-1 text-xs text-slate-500">
                                <p v-if="store.phone"><span class="font-semibold text-slate-700">Phone:</span> {{ store.phone }}</p>
                                <p v-if="store.email"><span class="font-semibold text-slate-700">Email:</span> {{ store.email }}</p>
                                <p v-if="store.address"><span class="font-semibold text-slate-700">Address:</span> {{ [store.address, store.city, store.region, store.country].filter(Boolean).join(', ') }}</p>
                            </div>
                        </div>

                        <div class="sm:text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-[.18em] text-prinx-600">Invoice</p>
                            <h2 class="mt-1 text-xl font-semibold text-slate-950">{{ invoice.reference }}</h2>
                            <div class="mt-3 space-y-1 text-xs text-slate-500">
                                <p><span class="font-semibold text-slate-700">Date:</span> {{ date(invoice.date) }}</p>
                                <p v-if="invoice.due_date"><span class="font-semibold text-slate-700">Due date:</span> {{ date(invoice.due_date) }}</p>
                                <p v-if="invoice.prepared_by_name"><span class="font-semibold text-slate-700">Prepared by:</span> {{ invoice.prepared_by_name }}</p>
                            </div>
                            <span class="mt-3 inline-flex rounded-[5px] bg-slate-100 px-2 py-1 text-[10px] font-semibold capitalize text-slate-700">{{ label(invoice.status) }}</span>
                        </div>
                    </div>

                    <div class="grid gap-6 border-b border-slate-200 py-6 sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Prepared for</p>
                            <p class="mt-2 text-sm font-semibold text-slate-700">{{ invoice.customer_name || 'Prinx Customer' }}</p>
                            <div class="mt-3 space-y-1 text-xs text-slate-500">
                                <p v-if="invoice.customer_phone"><span class="font-semibold text-slate-700">Phone:</span> {{ invoice.customer_phone }}</p>
                                <p v-if="cleanEmail(invoice.customer_email)"><span class="font-semibold text-slate-700">Email:</span> {{ cleanEmail(invoice.customer_email) }}</p>
                                <p v-if="invoice.customer_address"><span class="font-semibold text-slate-700">Address:</span> {{ invoice.customer_address }}</p>
                                <p v-if="invoice.customer_tin"><span class="font-semibold text-slate-700">TIN:</span> {{ invoice.customer_tin }}</p>
                            </div>
                        </div>

                        <div class="sm:text-right">
                            <template v-if="invoice.payment_method || invoice.order_status">
                                <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Order details</p>
                                <div class="mt-2 space-y-1 text-xs text-slate-500">
                                    <p v-if="invoice.payment_method"><span class="font-semibold text-slate-700">Payment method:</span> <span class="capitalize">{{ label(invoice.payment_method) }}</span></p>
                                    <p v-if="invoice.order_status"><span class="font-semibold text-slate-700">Order status:</span> <span class="capitalize">{{ label(invoice.order_status) }}</span></p>
                                    <p><span class="font-semibold text-slate-700">Payment status:</span> <span class="capitalize">{{ label(invoice.status) }}</span></p>
                                </div>
                            </template>
                            <template v-else-if="invoice.notes">
                                <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Notes</p>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ invoice.notes }}</p>
                            </template>
                        </div>
                    </div>

                    <div v-if="invoice.notes && (invoice.payment_method || invoice.order_status)" class="border-b border-slate-200 py-5">
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Notes</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ invoice.notes }}</p>
                    </div>

                    <div class="mt-6 overflow-x-auto border border-slate-200">
                        <table class="w-full min-w-[620px] border-collapse text-sm">
                            <thead class="bg-slate-950 text-white">
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold">Item</th>
                                    <th class="w-20 px-3 py-2.5 text-center text-xs font-semibold">Qty</th>
                                    <th class="w-36 px-3 py-2.5 text-right text-xs font-semibold">Unit price</th>
                                    <th class="w-36 px-3 py-2.5 text-right text-xs font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(item, index) in invoice.items" :key="`${item.sku}-${index}`">
                                    <td class="px-3 py-3"><p class="font-semibold text-slate-900">{{ item.name }}</p><p class="mt-0.5 text-[11px] text-slate-400">{{ item.sku || '—' }}</p></td>
                                    <td class="px-3 py-3 text-center text-slate-600">{{ item.quantity }}</td>
                                    <td class="px-3 py-3 text-right text-slate-600">{{ money(item.unit_price) }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ money(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 ml-auto w-full max-w-sm space-y-2 text-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Subtotal</span><span class="font-medium text-slate-800">{{ money(invoice.subtotal) }}</span></div>
                        <div v-if="Number(invoice.shipping) > 0" class="flex items-center justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Shipping</span><span class="font-medium text-slate-800">{{ money(invoice.shipping) }}</span></div>
                        <div v-if="Number(invoice.discount) > 0" class="flex items-center justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Discount</span><span class="font-medium text-slate-800">− {{ money(invoice.discount) }}</span></div>
                        <div class="flex items-center justify-between pt-1"><span class="font-semibold text-slate-900">Total</span><span class="text-xl font-semibold text-prinx-600">{{ money(invoice.total) }}</span></div>
                    </div>

                    <div class="mt-10 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500">
                        <p v-if="invoice.source === 'pos'">This invoice records the amount requested from the customer. It does not by itself confirm payment or reduce POS stock.</p>
                        <p v-else>This invoice was generated from your Prinx online order. Payment and fulfilment status are shown above and may update as your order progresses.</p>
                        <p class="mt-2">Thank you for choosing {{ store.name || 'Prinx' }}.</p>
                    </div>
                </article>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
@media print {
    :global(body) { background: #fff !important; }
    :global(header), :global(footer) { display: none !important; }
    .invoice-page { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    .invoice-sheet { width: 100% !important; max-width: none !important; min-height: 0 !important; padding: 12mm !important; box-shadow: none !important; }
    @page { size: A4; margin: 0; }
}
</style>
