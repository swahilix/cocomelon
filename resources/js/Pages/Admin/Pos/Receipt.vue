<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ sale: Object, items: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const printReceipt = () => window.print();
</script>

<template>
    <AdminLayout>
        <Head :title="`Receipt ${sale.reference}`" />
        <div class="print:hidden flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="section-kicker">Point of sale</p><h1 class="section-title">Sale receipt</h1><p class="mt-2 text-sm text-slate-500">{{ sale.reference }}</p></div>
            <div class="flex gap-2"><Link href="/admin/pos" class="btn-light"><Icon name="arrow-left" :size="16" /> New sale</Link><button class="btn-primary" @click="printReceipt"><Icon name="printer" :size="17" /> Print receipt</button></div>
        </div>

        <div class="mx-auto mt-6 w-full max-w-[420px] bg-white p-5 shadow-sm print:mt-0 print:max-w-none print:p-0 print:shadow-none">
            <div class="text-center"><img src="/assets/prinx-logo.png" alt="Prinx Electronics" class="mx-auto h-10 w-auto"><h2 class="mt-2 font-semibold">Prinx Electronics</h2><p class="text-xs text-slate-500">Sales receipt</p></div>
            <div class="mt-5 border-y border-dashed border-slate-300 py-3 text-xs"><div class="flex justify-between"><span>Receipt</span><b>{{ sale.reference }}</b></div><div class="mt-1 flex justify-between"><span>Date</span><span>{{ new Date(sale.created_at).toLocaleString() }}</span></div><div class="mt-1 flex justify-between"><span>Cashier</span><span>{{ sale.cashier_name || 'Administrator' }}</span></div><div class="mt-1 flex justify-between"><span>Customer</span><span>{{ sale.customer_name || 'Walk-in customer' }}</span></div><div v-if="sale.customer_phone" class="mt-1 flex justify-between"><span>Phone</span><span>{{ sale.customer_phone }}</span></div></div>
            <div class="divide-y divide-slate-100"><div v-for="item in items" :key="item.id" class="py-3 text-xs"><div class="flex justify-between gap-3"><div><b>{{ item.product_name }}</b><p class="text-slate-500">{{ item.sku || '—' }}</p></div><b>{{ money(item.line_total) }}</b></div><p class="mt-1 text-slate-500">{{ item.quantity }} × {{ money(item.unit_price) }}</p></div></div>
            <div class="border-t border-dashed border-slate-300 pt-3 text-sm"><div class="flex justify-between"><span>Subtotal</span><span>{{ money(sale.subtotal) }}</span></div><div class="mt-1 flex justify-between"><span>Discount</span><span>-{{ money(sale.discount) }}</span></div><div class="mt-2 flex justify-between text-lg font-semibold"><span>Total</span><span>{{ money(sale.total) }}</span></div><div class="mt-3 flex justify-between"><span>Paid</span><span>{{ money(sale.amount_paid) }}</span></div><div class="mt-1 flex justify-between"><span>Change</span><span>{{ money(sale.change_due) }}</span></div><div class="mt-1 flex justify-between"><span>Payment</span><span class="capitalize">{{ String(sale.payment_method).replace('_',' ') }}</span></div><div v-if="sale.payment_reference" class="mt-1 flex justify-between"><span>Reference</span><span>{{ sale.payment_reference }}</span></div></div>
            <p v-if="sale.notes" class="mt-4 border-t border-dashed border-slate-300 pt-3 text-xs text-slate-500">Note: {{ sale.notes }}</p>
            <div class="mt-5 text-center text-xs text-slate-500"><p>Thank you for shopping with us.</p><p class="mt-1">Please keep this receipt for your records.</p></div>
        </div>
    </AdminLayout>
</template>

<style>
@media print {
    aside, header, .print\:hidden { display: none !important; }
    main { padding: 0 !important; }
    .lg\:pl-72 { padding-left: 0 !important; }
    body { background: white !important; }
}
</style>
