<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ filters: Object, summary: Object, inventory: Object, purchases: Object, sales: Object });
const page = usePage();
const form = reactive({ from: props.filters.from, to: props.filters.to });
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const apply = () => router.get('/admin/reports', form, { preserveState: true, preserveScroll: true, replace: true });
const methodLabel = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
    <AdminLayout>
        <Head title="Reports" />

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="section-kicker">Stock management</p>
                <h1 class="section-title">Reports & analytics</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Inventory, purchasing, supplier balances, sales and operating performance from the same source transactions used by stock and accounting.</p>
            </div>
            <form class="grid gap-2 sm:grid-cols-[150px_150px_auto]" @submit.prevent="apply">
                <input v-model="form.from" type="date" class="input">
                <input v-model="form.to" type="date" class="input">
                <button class="btn-primary"><Icon name="tick-circle" :size="16" /> Apply</button>
            </form>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <Link href="/admin/stock/inventory" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Inventory value</p><b class="mt-2 block text-xl">{{ money(summary.inventory_value) }}</b><p class="mt-1 text-xs text-slate-500">{{ summary.stock_units }} units</p></Link>
            <Link href="/admin/sales" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Revenue</p><b class="mt-2 block text-xl">{{ money(summary.revenue) }}</b><p class="mt-1 text-xs text-slate-500">Selected period</p></Link>
            <Link href="/admin/profit" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-500">Gross profit</p><b class="mt-2 block text-xl">{{ money(summary.gross_profit) }}</b><p class="mt-1 text-xs text-slate-500">COGS {{ money(summary.cogs) }}</p></Link>
            <Link href="/admin/expenses" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Expenses</p><b class="mt-2 block text-xl">{{ money(summary.expenses) }}</b><p class="mt-1 text-xs text-slate-500">Posted only</p></Link>
            <Link href="/admin/profit" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Net result</p><b :class="['mt-2 block text-xl', Number(summary.net_result) < 0 ? 'text-red-600' : '']">{{ money(summary.net_result) }}</b><p class="mt-1 text-xs text-slate-500">Gross profit less expenses</p></Link>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link href="/admin/stock/inventory" class="card p-4"><p class="text-xs text-slate-500">Low stock</p><b class="mt-1 block text-2xl">{{ summary.low_stock }}</b></Link>
            <Link href="/admin/stock/inventory" class="card p-4"><p class="text-xs text-slate-500">Out of stock</p><b class="mt-1 block text-2xl">{{ summary.out_of_stock }}</b></Link>
            <Link href="/admin/stock" class="card p-4"><p class="text-xs text-slate-500">Purchases</p><b class="mt-1 block text-lg">{{ money(summary.purchases) }}</b></Link>
            <Link href="/admin/suppliers" class="card p-4"><p class="text-xs text-slate-500">Supplier balance</p><b class="mt-1 block text-lg">{{ money(summary.supplier_balance) }}</b></Link>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Fast-moving products</h2><p class="mt-1 text-xs text-slate-500">Highest units sold in the selected period.</p></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Product</th><th>Category</th><th>Sold</th><th>Stock</th></tr></thead><tbody><tr v-for="item in inventory.fastMoving" :key="item.id"><td><p class="font-semibold">{{ item.name }}</p><p class="text-xs text-slate-500">{{ item.sku }}</p></td><td>{{ item.category }}</td><td class="font-semibold">{{ item.units_sold }}</td><td>{{ item.stock }}</td></tr><tr v-if="!inventory.fastMoving.length"><td colspan="4" class="py-10 text-center text-slate-400">No sales activity.</td></tr></tbody></table></div>
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Slow / dead stock</h2><p class="mt-1 text-xs text-slate-500">Products still in stock with the lowest movement.</p></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Product</th><th>Category</th><th>Sold</th><th>Stock</th></tr></thead><tbody><tr v-for="item in inventory.slowMoving" :key="item.id"><td><p class="font-semibold">{{ item.name }}</p><p class="text-xs text-slate-500">{{ item.sku }}</p></td><td>{{ item.category }}</td><td>{{ item.units_sold }}</td><td class="font-semibold">{{ item.stock }}</td></tr></tbody></table></div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Purchases by supplier</h2></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Supplier</th><th>Purchases</th><th>Amount</th></tr></thead><tbody><tr v-for="row in purchases.bySupplier" :key="row.supplier"><td class="font-semibold">{{ row.supplier }}</td><td>{{ row.count }}</td><td>{{ money(row.amount) }}</td></tr><tr v-if="!purchases.bySupplier.length"><td colspan="3" class="py-10 text-center text-slate-400">No purchases in this period.</td></tr></tbody></table></div>
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Outstanding supplier balances</h2></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Supplier</th><th>Balance</th></tr></thead><tbody><tr v-for="row in purchases.supplierBalances" :key="row.id"><td><Link :href="`/admin/suppliers/${row.id}`" class="font-semibold text-prinx-600">{{ row.name }}</Link></td><td class="font-semibold">{{ money(row.balance) }}</td></tr></tbody></table></div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Sales by payment method</h2><p class="mt-1 text-xs text-slate-500">POS payments only.</p></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Method</th><th>Sales</th><th>Amount</th></tr></thead><tbody><tr v-for="row in sales.paymentMethods" :key="row.method"><td class="font-semibold">{{ methodLabel(row.method) }}</td><td>{{ row.count }}</td><td>{{ money(row.amount) }}</td></tr></tbody></table></div>
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Sales by cashier</h2><p class="mt-1 text-xs text-slate-500">POS transactions in the selected period.</p></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Cashier</th><th>Sales</th><th>Amount</th></tr></thead><tbody><tr v-for="row in sales.cashiers" :key="row.cashier"><td class="font-semibold">{{ row.cashier }}</td><td>{{ row.count }}</td><td>{{ money(row.amount) }}</td></tr></tbody></table></div>
            </section>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Daily sales trend</h2><p class="mt-1 text-xs text-slate-500">POS + recognized online sales less POS refunds.</p></div>
            <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Net sales</th></tr></thead><tbody><tr v-for="row in sales.daily" :key="row.date"><td>{{ row.date }}</td><td class="font-semibold">{{ money(row.sales) }}</td></tr></tbody></table></div>
        </section>
    </AdminLayout>
</template>
