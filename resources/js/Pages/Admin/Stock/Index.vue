<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ products: Array, suppliers: Array, stats: Object, purchases: Array, sales: Array, expenses: Array, movements: Array });
const page = usePage();
const params = new URLSearchParams((page.url.split('?')[1] || '').split('#')[0]);
const allowedTabs = ['overview', 'purchases', 'pos', 'expenses', 'movements'];
const requestedTab = params.get('tab');
const tab = ref(allowedTabs.includes(requestedTab) ? requestedTab : 'overview');
const today = new Date().toISOString().slice(0, 10);
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const expenseForm = useForm({ expense_date: today, category: 'Operations', description: '', amount: '', payment_method: 'cash', payee: '', notes: '' });
const posForm = useForm({ customer_name: '', customer_phone: '', discount: 0, amount_paid: '', payment_method: 'cash', notes: '', items: [{ product_id: '', quantity: 1, unit_price: 0 }] });

const addPosItem = () => posForm.items.push({ product_id: '', quantity: 1, unit_price: 0 });
const removePosItem = (index) => posForm.items.length > 1 && posForm.items.splice(index, 1);
const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));
const syncPosPrice = (item) => { const product = productById(item.product_id); if (product) item.unit_price = Number(product.price || 0); };
const posSubtotal = computed(() => posForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));
const posTotal = computed(() => Math.max(0, posSubtotal.value - Number(posForm.discount || 0)));
const posChange = computed(() => Math.max(0, Number(posForm.amount_paid || 0) - posTotal.value));

const purchaseStatusClass = (value) => ({
    draft: 'bg-slate-50 text-slate-600 border-slate-200',
    ordered: 'bg-blue-50 text-blue-700 border-blue-200',
    partially_received: 'bg-amber-50 text-amber-700 border-amber-200',
    received: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    cancelled: 'bg-red-50 text-red-700 border-red-200',
}[value] || 'bg-slate-50 text-slate-600 border-slate-200');
const purchaseStatusLabel = (value) => String(value || 'draft').replaceAll('_', ' ');

const submitExpense = () => expenseForm.post('/admin/stock/expenses', { preserveScroll: true, onSuccess: () => expenseForm.reset('description', 'amount', 'payee', 'notes') });
const submitPos = () => posForm.post('/admin/stock/pos', { preserveScroll: true, onSuccess: () => posForm.reset('customer_name', 'customer_phone', 'discount', 'amount_paid', 'notes', 'items') });
const tabs = [
    ['overview', 'Overview', 'home-2'],
    ['purchases', 'Purchases', 'shopping-cart'],
    ['pos', 'POS / Sales', 'shop'],
    ['expenses', 'Expenses', 'wallet'],
    ['movements', 'Stock movements', 'box'],
];
</script>

<template>
    <AdminLayout>
        <Head title="Stock Management" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Inventory & finance</p>
                <h1 class="section-title">Stock management</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Manage purchases, counter sales, operating expenses and every movement affecting product stock.</p>
            </div>
            <div class="flex gap-2"><Link href="/admin/suppliers" class="btn-light"><Icon name="profile" :size="17" /> Suppliers</Link><button class="btn-primary" @click="tab = 'pos'"><Icon name="shop" :size="17" /> Open POS</button></div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Stock units</p><b class="mt-2 block text-2xl">{{ stats.stock_units }}</b><p class="mt-1 text-xs text-slate-500">Tracked inventory units</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-red-500">Low stock</p><b class="mt-2 block text-2xl">{{ stats.low_stock }}</b><p class="mt-1 text-xs text-slate-500">Uses each product threshold</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Today sales</p><b class="mt-2 block text-xl">{{ money(stats.today_sales) }}</b><p class="mt-1 text-xs text-slate-500">POS only</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Month sales</p><b class="mt-2 block text-xl">{{ money(stats.month_sales) }}</b><p class="mt-1 text-xs text-slate-500">POS revenue</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-blue-600">Purchases</p><b class="mt-2 block text-xl">{{ money(stats.month_purchases) }}</b><p class="mt-1 text-xs text-slate-500">This month</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Expenses</p><b class="mt-2 block text-xl">{{ money(stats.month_expenses) }}</b><p class="mt-1 text-xs text-slate-500">This month</p></div>
        </div>

        <div class="mt-6 flex gap-2 overflow-x-auto rounded-[10px] border border-slate-200 bg-white p-2">
            <button v-for="item in tabs" :key="item[0]" :class="['inline-flex shrink-0 items-center gap-2 rounded-[8px] px-3 py-2 text-sm font-semibold transition', tab === item[0] ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100']" @click="tab = item[0]"><Icon :name="item[2]" :size="16" />{{ item[1] }}</button>
        </div>

        <section v-if="tab === 'overview'" class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Recent POS sales</h2><p class="mt-1 text-xs text-slate-500">Latest counter transactions.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Reference</th><th>Customer</th><th>Total</th><th>Payment</th></tr></thead><tbody><tr v-for="sale in sales.slice(0, 8)" :key="sale.id"><td class="font-semibold">{{ sale.reference }}</td><td>{{ sale.customer_name || 'Walk-in customer' }}</td><td class="font-semibold">{{ money(sale.total) }}</td><td>{{ sale.payment_method }}</td></tr><tr v-if="!sales.length"><td colspan="4" class="py-10 text-center text-slate-400">No POS sales yet.</td></tr></tbody></table></div></div>
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Recent purchases</h2><p class="mt-1 text-xs text-slate-500">Latest purchase orders and receipts.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Reference</th><th>Supplier</th><th>Status</th><th>Total</th></tr></thead><tbody><tr v-for="purchase in purchases.slice(0, 8)" :key="purchase.id"><td><Link :href="`/admin/stock/purchases/${purchase.id}`" class="font-semibold text-slate-900 hover:text-prinx-600">{{ purchase.reference }}</Link></td><td>{{ purchase.display_supplier_name || 'Not specified' }}</td><td><span :class="['inline-flex rounded-[7px] border px-2 py-1 text-[11px] font-semibold capitalize', purchaseStatusClass(purchase.status)]">{{ purchaseStatusLabel(purchase.status) }}</span></td><td class="font-semibold">{{ money(purchase.total) }}</td></tr><tr v-if="!purchases.length"><td colspan="4" class="py-10 text-center text-slate-400">No purchases yet.</td></tr></tbody></table></div></div>
        </section>

        <section v-if="tab === 'purchases'" class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
            <div class="card h-fit p-5">
                <div class="flex items-center justify-between"><div><h2 class="font-semibold">New purchase or opening stock</h2><p class="mt-1 text-xs text-slate-500">Purchases and opening stock are entered on the Add stock page: quantity, purchase price, selling price and customer discount for each product, with an option to mark the purchase as received.</p></div><Icon name="shopping-cart" :size="22" class="text-prinx-600" /></div>
                <div class="mt-4 flex flex-wrap gap-2"><Link href="/admin/stock/entry?mode=purchase" class="btn-primary"><Icon name="add-circle" :size="16" /> New purchase</Link><Link href="/admin/stock/entry?mode=opening" class="btn-light"><Icon name="box-add" :size="16" /> Opening stock</Link></div>
            </div>
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Purchase history</h2><p class="mt-1 text-xs text-slate-500">Open a purchase to receive stock, post payments or return goods.</p></div><div class="max-h-[680px] overflow-auto divide-y divide-slate-100"><Link v-for="purchase in purchases" :key="purchase.id" :href="`/admin/stock/purchases/${purchase.id}`" class="block p-4 transition hover:bg-slate-50"><div class="flex justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold text-slate-900">{{ purchase.reference }}</p><span :class="['inline-flex rounded-[7px] border px-2 py-0.5 text-[10px] font-semibold capitalize', purchaseStatusClass(purchase.status)]">{{ purchaseStatusLabel(purchase.status) }}</span></div><p class="mt-1 text-xs text-slate-500">{{ purchase.display_supplier_name || 'Supplier not specified' }} · {{ purchase.purchase_date }}</p><p class="mt-1 text-[11px] text-slate-400">Payment: {{ purchase.payment_status?.replace('_', ' ') || 'unpaid' }} · Balance {{ money(purchase.balance_due) }}</p></div><div class="text-right"><b>{{ money(purchase.total) }}</b><p class="mt-1 text-xs font-semibold text-prinx-600">Open →</p></div></div></Link><div v-if="!purchases.length" class="p-10 text-center text-sm text-slate-400">No purchases recorded.</div></div></div>
        </section>

        <section v-if="tab === 'pos'" class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
            <form class="card p-5" @submit.prevent="submitPos">
                <div class="flex items-center justify-between"><div><h2 class="font-semibold">Point of sale</h2><p class="mt-1 text-xs text-slate-500">Create an immediate counter sale and deduct stock.</p></div><Icon name="shop" :size="23" class="text-prinx-600" /></div>
                <div v-if="posForm.errors.pos" class="mt-4 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ posForm.errors.pos }}</div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2"><div><label class="label">Customer name</label><input v-model="posForm.customer_name" class="input" placeholder="Walk-in customer"></div><div><label class="label">Phone</label><input v-model="posForm.customer_phone" class="input" placeholder="Optional"></div></div>
                <div class="mt-5 space-y-3"><div v-for="(item, index) in posForm.items" :key="index" class="grid gap-2 rounded-[10px] border border-slate-200 p-3 sm:grid-cols-[1fr_90px_140px_40px]"><select v-model="item.product_id" class="input" @change="syncPosPrice(item)"><option value="">Select product</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }} · Stock {{ product.track_stock ? product.stock_quantity : '∞' }}</option></select><input v-model.number="item.quantity" type="number" min="1" class="input" placeholder="Qty"><input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="input" placeholder="Selling price"><button type="button" class="flex h-10 w-10 items-center justify-center rounded-[8px] text-red-500 hover:bg-red-50" @click="removePosItem(index)"><Icon name="trash" :size="18" /></button></div><button type="button" class="btn-light" @click="addPosItem"><Icon name="add-circle" :size="16" /> Add product</button></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><label class="label">Discount</label><input v-model.number="posForm.discount" type="number" min="0" step="0.01" class="input"></div><div><label class="label">Payment</label><select v-model="posForm.payment_method" class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank/card</option></select></div><div><label class="label">Amount paid</label><input v-model.number="posForm.amount_paid" type="number" min="0" step="0.01" class="input"></div><div><label class="label">Change</label><div class="input bg-slate-50 font-semibold">{{ money(posChange) }}</div></div></div>
                <div class="mt-5 flex items-end justify-between gap-4 border-t border-slate-200 pt-4"><div><p class="text-xs text-slate-500">Subtotal {{ money(posSubtotal) }}</p><p class="mt-1 text-xs text-slate-500">Discount {{ money(posForm.discount) }}</p><b class="mt-1 block text-2xl">{{ money(posTotal) }}</b></div><button class="btn-primary" :disabled="posForm.processing"><Icon name="tick-circle" :size="18" /> Complete sale</button></div>
            </form>
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Recent POS sales</h2></div><div class="max-h-[650px] overflow-auto divide-y divide-slate-100"><div v-for="sale in sales" :key="sale.id" class="p-4"><div class="flex justify-between gap-3"><div><p class="font-semibold">{{ sale.reference }}</p><p class="mt-1 text-xs text-slate-500">{{ sale.customer_name || 'Walk-in' }} · {{ sale.payment_method }}</p></div><b>{{ money(sale.total) }}</b></div></div><div v-if="!sales.length" class="p-10 text-center text-sm text-slate-400">No sales recorded.</div></div></div>
        </section>

        <section v-if="tab === 'expenses'" class="mt-6 grid gap-6 xl:grid-cols-[.85fr_1.15fr]">
            <form class="card p-5" @submit.prevent="submitExpense"><div class="flex items-center justify-between"><div><h2 class="font-semibold">Record expense</h2><p class="mt-1 text-xs text-slate-500">Track operating costs separately from stock purchases.</p></div><Icon name="wallet" :size="23" class="text-prinx-600" /></div><div class="mt-5 grid gap-3 sm:grid-cols-2"><div><label class="label">Date</label><input v-model="expenseForm.expense_date" type="date" class="input"></div><div><label class="label">Category</label><select v-model="expenseForm.category" class="input"><option>Operations</option><option>Transport</option><option>Utilities</option><option>Rent</option><option>Salary</option><option>Marketing</option><option>Repair</option><option>Other</option></select></div><div class="sm:col-span-2"><label class="label">Description</label><input v-model="expenseForm.description" class="input" placeholder="What was paid for?"></div><div><label class="label">Amount</label><input v-model="expenseForm.amount" type="number" min="0.01" step="0.01" class="input"></div><div><label class="label">Payment method</label><select v-model="expenseForm.payment_method" class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option></select></div><div><label class="label">Payee</label><input v-model="expenseForm.payee" class="input" placeholder="Optional"></div><div><label class="label">Notes</label><input v-model="expenseForm.notes" class="input" placeholder="Optional"></div></div><button class="btn-primary mt-5" :disabled="expenseForm.processing"><Icon name="tick-circle" :size="17" /> Save expense</button></form>
            <div class="card overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Reference</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead><tbody><tr v-for="expense in expenses" :key="expense.id"><td>{{ expense.expense_date }}</td><td class="font-semibold">{{ expense.reference }}</td><td>{{ expense.category }}</td><td>{{ expense.description }}</td><td class="font-semibold">{{ money(expense.amount) }}</td></tr><tr v-if="!expenses.length"><td colspan="5" class="py-12 text-center text-slate-400">No expenses recorded.</td></tr></tbody></table></div>
        </section>

        <section v-if="tab === 'movements'" class="card mt-6 overflow-x-auto"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Stock movement ledger</h2><p class="mt-1 text-xs text-slate-500">A permanent audit trail of inventory entering and leaving the store.</p></div><table class="admin-table"><thead><tr><th>Product</th><th>Type</th><th>Movement</th><th>Balance</th><th>Reference</th><th>Date</th></tr></thead><tbody><tr v-for="movement in movements" :key="movement.id"><td><p class="font-semibold">{{ movement.product_name }}</p><p class="text-xs text-slate-500">{{ movement.sku }}</p></td><td>{{ movement.type }}</td><td><span :class="movement.quantity > 0 ? 'font-semibold text-emerald-600' : 'font-semibold text-red-600'">{{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}</span></td><td>{{ movement.balance_after ?? '—' }}</td><td>{{ movement.reference || '—' }}</td><td>{{ new Date(movement.created_at).toLocaleString() }}</td></tr><tr v-if="!movements.length"><td colspan="6" class="py-12 text-center text-slate-400">No stock movements yet.</td></tr></tbody></table></section>
    </AdminLayout>
</template>
