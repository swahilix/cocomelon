<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ filters: Object, summary: Object, products: Array, categories: Array, costHealth: Object });
const page = usePage();
const form = reactive({ from: props.filters.from, to: props.filters.to, source: props.filters.source || 'all' });
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const percent = (value) => `${Number(value || 0).toFixed(1)}%`;
const apply = () => router.get('/admin/profit', form, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <AdminLayout>
        <Head title="Profit & Costing" />

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="section-kicker">Costing & profitability</p>
                <h1 class="section-title">Profit & costing</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Revenue less preserved cost of goods sold and operating expenses, using weighted-average inventory cost.</p>
            </div>
            <form class="grid gap-2 rounded-[10px] border border-slate-200 bg-white p-3 sm:grid-cols-[150px_150px_140px_auto]" @submit.prevent="apply">
                <input v-model="form.from" type="date" class="input">
                <input v-model="form.to" type="date" class="input">
                <select v-model="form.source" class="input">
                    <option value="all">All sales</option>
                    <option value="pos">POS only</option>
                    <option value="online">Online only</option>
                </select>
                <button class="btn-primary"><Icon name="tick-circle" :size="16" /> Apply</button>
            </form>
        </div>

        <div v-if="costHealth.products_without_cost || costHealth.historical_online_lines_without_cost" class="mt-5 rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <div class="flex items-start gap-3">
                <Icon name="info-circle" :size="19" class="mt-0.5 shrink-0" />
                <div>
                    <p class="font-semibold">Costing coverage notice</p>
                    <p class="mt-1 text-xs leading-5">{{ costHealth.products_without_cost }} tracked product(s) currently have no cost baseline. {{ costHealth.historical_online_lines_without_cost }} historical online order line(s) predate COGS snapshots and therefore contribute zero historical COGS until a controlled historical-cost import is performed.</p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Revenue</p><b class="mt-2 block text-2xl">{{ money(summary.revenue) }}</b><p class="mt-1 text-xs text-slate-500">POS + recognized online sales</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">COGS</p><b class="mt-2 block text-2xl">{{ money(summary.cogs) }}</b><p class="mt-1 text-xs text-slate-500">Preserved unit cost × sold units</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Gross profit</p><b class="mt-2 block text-2xl">{{ money(summary.gross_profit) }}</b><p class="mt-1 text-xs text-slate-500">Margin {{ percent(summary.gross_margin) }}</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Net operating result</p><b :class="['mt-2 block text-2xl', Number(summary.net_result) < 0 ? 'text-red-600' : 'text-slate-950']">{{ money(summary.net_result) }}</b><p class="mt-1 text-xs text-slate-500">Gross profit less expenses</p></div>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card p-4"><div class="flex items-center gap-2 text-sm font-semibold"><Icon name="shop" :size="17" />POS revenue</div><b class="mt-2 block text-lg">{{ money(summary.pos_revenue) }}</b></div>
            <div class="card p-4"><div class="flex items-center gap-2 text-sm font-semibold"><Icon name="shopping-cart" :size="17" />Online revenue</div><b class="mt-2 block text-lg">{{ money(summary.online_revenue) }}</b></div>
            <div class="card p-4"><div class="flex items-center gap-2 text-sm font-semibold"><Icon name="wallet" :size="17" />Expenses</div><b class="mt-2 block text-lg">{{ money(summary.expenses) }}</b></div>
            <div class="card p-4"><div class="flex items-center gap-2 text-sm font-semibold"><Icon name="box" :size="17" />POS refunds</div><b class="mt-2 block text-lg">{{ money(summary.refunds) }}</b></div>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Product profitability</h2><p class="mt-1 text-xs text-slate-500">Top products for the selected period. Restocked returns reverse COGS; non-restockable returns retain their cost.</p></div>
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Category</th><th>Units</th><th>Revenue</th><th>COGS</th><th>Gross profit</th><th>Margin</th></tr></thead>
                    <tbody>
                        <tr v-for="product in products" :key="`${product.product_id}-${product.product}`">
                            <td class="font-semibold">{{ product.product }}</td><td>{{ product.category }}</td><td>{{ product.units }}</td><td>{{ money(product.revenue) }}</td><td>{{ money(product.cogs) }}</td><td :class="Number(product.gross_profit) < 0 ? 'font-semibold text-red-600' : 'font-semibold text-emerald-600'">{{ money(product.gross_profit) }}</td><td>{{ percent(product.margin) }}</td>
                        </tr>
                        <tr v-if="!products.length"><td colspan="7" class="py-12 text-center text-slate-400">No recognized sales for this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Category profitability</h2><p class="mt-1 text-xs text-slate-500">Aggregated product revenue and COGS by category.</p></div>
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Category</th><th>Units</th><th>Revenue</th><th>COGS</th><th>Gross profit</th><th>Margin</th></tr></thead>
                    <tbody>
                        <tr v-for="category in categories" :key="category.category"><td class="font-semibold">{{ category.category }}</td><td>{{ category.units }}</td><td>{{ money(category.revenue) }}</td><td>{{ money(category.cogs) }}</td><td class="font-semibold">{{ money(category.gross_profit) }}</td><td>{{ percent(category.margin) }}</td></tr>
                        <tr v-if="!categories.length"><td colspan="6" class="py-12 text-center text-slate-400">No category data for this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
