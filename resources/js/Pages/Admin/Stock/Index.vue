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
const requestedProduct = Number(params.get('product') || 0);
const requestedSupplier = Number(params.get('supplier') || 0);
const tab = ref(allowedTabs.includes(requestedTab) ? requestedTab : 'overview');
const today = new Date().toISOString().slice(0, 10);
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const initialPurchaseProduct = props.products.some((product) => Number(product.id) === requestedProduct) ? requestedProduct : '';
const initialSupplier = props.suppliers.some((supplier) => Number(supplier.id) === requestedSupplier) ? requestedSupplier : '';
const oneTimeSupplier = ref(false);

const purchaseForm = useForm({ supplier_id: initialSupplier, one_time_supplier_name: '', invoice_number: '', purchase_date: today, additional_cost: 0, payment_method: 'cash', notes: '', items: [{ product_id: initialPurchaseProduct, quantity: 1, unit_cost: 0 }] });
const expenseForm = useForm({ expense_date: today, category: 'Operations', description: '', amount: '', payment_method: 'cash', payee: '', notes: '' });
const posForm = useForm({ customer_name: '', customer_phone: '', discount: 0, amount_paid: '', payment_method: 'cash', notes: '', items: [{ product_id: '', quantity: 1, unit_price: 0 }] });

const addPurchaseItem = () => purchaseForm.items.push({ product_id: '', quantity: 1, unit_cost: 0 });
const addPosItem = () => posForm.items.push({ product_id: '', quantity: 1, unit_price: 0 });
const removePurchaseItem = (index) => purchaseForm.items.length > 1 && purchaseForm.items.splice(index, 1);
const removePosItem = (index) => posForm.items.length > 1 && posForm.items.splice(index, 1);
const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));
const syncPosPrice = (item) => { const product = productById(item.product_id); if (product) item.unit_price = Number(product.price || 0); };
const purchaseSubtotal = computed(() => purchaseForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_cost || 0), 0));
const purchaseTotal = computed(() => purchaseSubtotal.value + Number(purchaseForm.additional_cost || 0));
const posSubtotal = computed(() => posForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));
const posTotal = computed(() => Math.max(0, posSubtotal.value - Number(posForm.discount || 0)));
const posChange = computed(() => Math.max(0, Number(posForm.amount_paid || 0) - posTotal.value));

function setOneTimeSupplier(value) {
    oneTimeSupplier.value = value;
    if (value) purchaseForm.supplier_id = '';
    else purchaseForm.one_time_supplier_name = '';
}

const submitPurchase = () => purchaseForm.post('/admin/stock/purchases', {
    preserveScroll: true,
    onSuccess: () => {
        purchaseForm.reset('supplier_id', 'one_time_supplier_name', 'invoice_number', 'additional_cost', 'notes', 'items');
        oneTimeSupplier.value = false;
    },
});
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
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Recent purchases</h2><p class="mt-1 text-xs text-slate-500">Stock received from suppliers.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Reference</th><th>Supplier</th><th>Date</th><th>Total</th></tr></thead><tbody><tr v-for="purchase in purchases.slice(0, 8)" :key="purchase.id"><td class="font-semibold">{{ purchase.reference }}</td><td><Link v-if="purchase.supplier_id" :href="`/admin/suppliers/${purchase.supplier_id}`" class="font-medium hover:text-prinx-600">{{ purchase.display_supplier_name }}</Link><span v-else>{{ purchase.display_supplier_name || 'Not specified' }}</span></td><td>{{ purchase.purchase_date }}</td><td class="font-semibold">{{ money(purchase.total) }}</td></tr><tr v-if="!purchases.length"><td colspan="4" class="py-10 text-center text-slate-400">No purchases yet.</td></tr></tbody></table></div></div>
        </section>

        <section v-if="tab === 'purchases'" class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
            <form class="card p-5" @submit.prevent="submitPurchase">
                <div class="flex items-center justify-between"><div><h2 class="font-semibold">Record purchase</h2><p class="mt-1 text-xs text-slate-500">Received quantities are added to tracked stock immediately.</p></div><Icon name="shopping-cart" :size="22" class="text-prinx-600" /></div>
                <div v-if="initialPurchaseProduct" class="mt-4 flex items-center gap-2 rounded-[8px] border border-prinx-200 bg-prinx-50 px-3 py-2 text-xs font-semibold text-prinx-700"><Icon name="box" :size="15" /> Opened from low-stock inventory shortcut.</div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between"><label class="label">Supplier</label><button type="button" class="text-xs font-semibold text-prinx-600 hover:text-prinx-700" @click="setOneTimeSupplier(!oneTimeSupplier)">{{ oneTimeSupplier ? 'Choose saved supplier' : 'Use one-time supplier' }}</button></div>
                        <select v-if="!oneTimeSupplier" v-model="purchaseForm.supplier_id" class="input"><option value="">Select supplier</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}{{ supplier.contact_person ? ` · ${supplier.contact_person}` : '' }}</option></select>
                        <input v-else v-model="purchaseForm.one_time_supplier_name" class="input" placeholder="One-time supplier/business name">
                        <p v-if="purchaseForm.errors.supplier_id || purchaseForm.errors.one_time_supplier_name" class="mt-1 text-xs text-red-600">{{ purchaseForm.errors.supplier_id || purchaseForm.errors.one_time_supplier_name }}</p>
                        <div class="mt-2 flex items-center gap-3 text-xs text-slate-500"><Link href="/admin/suppliers" class="inline-flex items-center gap-1 font-semibold text-prinx-600"><Icon name="add-circle" :size="14" /> Manage suppliers</Link><span>Saved suppliers provide purchase history and balance tracking.</span></div>
                    </div>
                    <div><label class="label">Invoice number</label><input v-model="purchaseForm.invoice_number" class="input" placeholder="Optional"></div>
                    <div><label class="label">Purchase date</label><input v-model="purchaseForm.purchase_date" type="date" class="input"></div>
                    <div><label class="label">Payment method</label><select v-model="purchaseForm.payment_method" class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="credit">Credit / unpaid</option></select></div>
                </div>
                <div class="mt-5 space-y-3"><div v-for="(item, index) in purchaseForm.items" :key="index" class="grid gap-2 rounded-[10px] border border-slate-200 p-3 sm:grid-cols-[1fr_90px_140px_40px]"><select v-model="item.product_id" class="input"><option value="">Select product</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }} · Stock {{ product.stock_quantity }}</option></select><input v-model.number="item.quantity" type="number" min="1" class="input" placeholder="Qty"><input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="input" placeholder="Unit cost"><button type="button" class="flex h-10 w-10 items-center justify-center rounded-[8px] text-red-500 hover:bg-red-50" @click="removePurchaseItem(index)"><Icon name="trash" :size="18" /></button></div><button type="button" class="btn-light" @click="addPurchaseItem"><Icon name="add-circle" :size="16" /> Add item</button></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2"><div><label class="label">Additional cost</label><input v-model.number="purchaseForm.additional_cost" type="number" min="0" step="0.01" class="input"></div><div><label class="label">Notes</label><input v-model="purchaseForm.notes" class="input" placeholder="Transport, remarks..."></div></div>
                <div class="mt-5 flex items-center justify-between border-t border-slate-200 pt-4"><div><p class="text-xs text-slate-500">Purchase total</p><b class="text-xl">{{ money(purchaseTotal) }}</b></div><button class="btn-primary" :disabled="purchaseForm.processing"><Icon name="tick-circle" :size="17" /> Save purchase</button></div>
            </form>
            <div class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Purchase history</h2></div><div class="max-h-[650px] overflow-auto divide-y divide-slate-100"><div v-for="purchase in purchases" :key="purchase.id" class="p-4"><div class="flex justify-between gap-3"><div><p class="font-semibold">{{ purchase.reference }}</p><p class="mt-1 text-xs text-slate-500"><Link v-if="purchase.supplier_id" :href="`/admin/suppliers/${purchase.supplier_id}`" class="hover:text-prinx-600">{{ purchase.display_supplier_name }}</Link><span v-else>{{ purchase.display_supplier_name || 'Supplier not specified' }}</span> · {{ purchase.purchase_date }}</p></div><b>{{ money(purchase.total) }}</b></div></div><div v-if="!purchases.length" class="p-10 text-center text-sm text-slate-400">No purchases recorded.</div></div></div>
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
