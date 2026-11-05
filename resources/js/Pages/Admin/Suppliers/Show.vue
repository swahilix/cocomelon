<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ supplier: Object, purchases: Array, products: Array, payments: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
</script>

<template>
    <AdminLayout>
        <Head :title="supplier.name" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link href="/admin/suppliers" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-900"><Icon name="arrow-left" :size="15" /> Suppliers</Link>
                <h1 class="mt-3 section-title">{{ supplier.name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ supplier.contact_person || 'No contact person' }} · {{ supplier.phone || supplier.email || 'No contact details' }}</p>
            </div>
            <Link :href="`/admin/stock/entry?mode=purchase&supplier=${supplier.id}`" class="btn-primary"><Icon name="shopping-cart" :size="17" /> New purchase</Link>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Total purchases</p><b class="mt-2 block text-xl">{{ money(supplier.total_purchases) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Paid amount</p><b class="mt-2 block text-xl">{{ money(supplier.paid_amount) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Outstanding</p><b class="mt-2 block text-xl">{{ money(supplier.outstanding_amount) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Purchases</p><b class="mt-2 block text-2xl">{{ supplier.purchase_count }}</b></div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
            <div class="card p-5">
                <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-[8px] bg-slate-100 text-slate-700"><Icon name="profile" :size="20" /></span><div><h2 class="font-semibold">Supplier details</h2><p class="text-xs text-slate-500">Contact and account information</p></div></div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Contact person</dt><dd class="mt-1 font-medium">{{ supplier.contact_person || '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Phone</dt><dd class="mt-1 font-medium">{{ supplier.phone || '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Email</dt><dd class="mt-1 font-medium">{{ supplier.email || '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Tax / TIN / reference</dt><dd class="mt-1 font-medium">{{ supplier.tax_number || '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Opening balance</dt><dd class="mt-1 font-medium">{{ money(supplier.opening_balance) }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Address</dt><dd class="mt-1 whitespace-pre-line">{{ supplier.address || '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">Notes</dt><dd class="mt-1 whitespace-pre-line text-slate-600">{{ supplier.notes || '—' }}</dd></div>
                </dl>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Recent purchases</h2><p class="mt-1 text-xs text-slate-500">Purchases directly linked to this supplier.</p></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Reference</th><th>Invoice</th><th>Payment</th><th>Total</th></tr></thead><tbody><tr v-for="purchase in purchases" :key="purchase.id"><td>{{ purchase.purchase_date }}</td><td class="font-semibold">{{ purchase.reference }}</td><td>{{ purchase.invoice_number || '—' }}</td><td>{{ purchase.payment_method }}</td><td class="font-semibold">{{ money(purchase.total) }}</td></tr><tr v-if="!purchases.length"><td colspan="5" class="py-12 text-center text-slate-400">No linked purchases yet.</td></tr></tbody></table></div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Products commonly supplied</h2></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Product</th><th>Quantity</th><th>Purchase value</th></tr></thead><tbody><tr v-for="product in products" :key="product.id"><td><p class="font-semibold">{{ product.name }}</p><p class="text-xs text-slate-500">{{ product.sku }}</p></td><td>{{ product.quantity_supplied }}</td><td class="font-semibold">{{ money(product.purchase_value) }}</td></tr><tr v-if="!products.length"><td colspan="3" class="py-12 text-center text-slate-400">No supplied products yet.</td></tr></tbody></table></div></div>
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Payment history</h2><p class="mt-1 text-xs text-slate-500">Current Phase 2 view treats non-credit purchases as paid at purchase time.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Reference</th><th>Method</th><th>Amount</th></tr></thead><tbody><tr v-for="payment in payments" :key="payment.id"><td>{{ payment.date }}</td><td class="font-semibold">{{ payment.reference }}</td><td>{{ payment.method }}</td><td class="font-semibold">{{ money(payment.amount) }}</td></tr><tr v-if="!payments.length"><td colspan="4" class="py-12 text-center text-slate-400">No paid purchases yet.</td></tr></tbody></table></div></div>
        </div>
    </AdminLayout>
</template>
