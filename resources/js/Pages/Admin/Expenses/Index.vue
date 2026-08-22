<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ expenses: Array, categories: Array, filters: Object, summary: Object, categoryBreakdown: Array, monthly: Array, largestExpenses: Array });
const page = usePage();
const today = new Date().toISOString().slice(0, 10);
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));

const filters = reactive({
    from: props.filters.from,
    to: props.filters.to,
    category: props.filters.category || '',
    search: props.filters.search || '',
    status: props.filters.status || '',
});
const applyFilters = () => router.get('/admin/expenses', filters, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => {
    filters.category = '';
    filters.search = '';
    filters.status = '';
    applyFilters();
};

const activeCategories = computed(() => props.categories.filter((category) => category.is_active));
const expenseForm = useForm({
    expense_date: today,
    expense_category_id: activeCategories.value[0]?.id || '',
    title: '',
    amount: '',
    payment_method: 'cash',
    payment_reference: '',
    payee: '',
    receipt: null,
    is_recurring: false,
    recurring_interval: 'monthly',
    recurring_until: '',
    notes: '',
});
const receiptInput = ref(null);
const submitExpense = () => expenseForm.post('/admin/expenses', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
        expenseForm.reset('title', 'amount', 'payment_reference', 'payee', 'receipt', 'is_recurring', 'recurring_until', 'notes');
        expenseForm.recurring_interval = 'monthly';
        if (receiptInput.value) receiptInput.value.value = '';
    },
});

const categoryForm = useForm({ name: '', description: '' });
const submitCategory = () => categoryForm.post('/admin/expenses/categories', { preserveScroll: true, onSuccess: () => categoryForm.reset() });
const editingCategory = ref(null);
const editCategoryForm = useForm({ name: '', description: '', is_active: true });
function startCategoryEdit(category) {
    editingCategory.value = category;
    editCategoryForm.name = category.name;
    editCategoryForm.description = category.description || '';
    editCategoryForm.is_active = Boolean(category.is_active);
}
function saveCategory() {
    if (!editingCategory.value) return;
    editCategoryForm.patch(`/admin/expenses/categories/${editingCategory.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingCategory.value = null; editCategoryForm.reset(); },
    });
}

const voiding = ref(null);
const voidForm = useForm({ reason: '' });
function openVoid(expense) {
    voiding.value = expense;
    voidForm.reason = '';
    voidForm.clearErrors();
}
function closeVoid() {
    voiding.value = null;
    voidForm.reset();
    voidForm.clearErrors();
}
function submitVoid() {
    if (!voiding.value) return;
    voidForm.post(`/admin/expenses/${voiding.value.id}/void`, { preserveScroll: true, onSuccess: closeVoid });
}

const statusClass = (status) => status === 'voided' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700';
</script>

<template>
    <AdminLayout>
        <Head title="Expenses" />

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="section-kicker">Finance & operations</p>
                <h1 class="section-title">Expense management</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Record operating costs, attach receipts, manage categories and review where business money is being spent.</p>
            </div>
            <a href="/admin/profit" class="btn-light"><Icon name="wallet" :size="17" /> Profit & costing</a>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Total expenses</p><b class="mt-2 block text-xl">{{ money(summary.total) }}</b><p class="mt-1 text-xs text-slate-500">Selected period</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Entries</p><b class="mt-2 block text-2xl">{{ summary.count }}</b><p class="mt-1 text-xs text-slate-500">Posted expenses</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Average</p><b class="mt-2 block text-xl">{{ money(summary.average) }}</b><p class="mt-1 text-xs text-slate-500">Per expense</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Largest</p><b class="mt-2 block text-xl">{{ money(summary.largest) }}</b><p class="mt-1 text-xs text-slate-500">Single posted expense</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Recurring</p><b class="mt-2 block text-2xl">{{ summary.recurring }}</b><p class="mt-1 text-xs text-slate-500">Marked recurring</p></div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[.9fr_1.1fr]">
            <form class="card p-5" enctype="multipart/form-data" @submit.prevent="submitExpense">
                <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Record expense</h2><p class="mt-1 text-xs text-slate-500">Financial records remain auditable after posting.</p></div><Icon name="wallet" :size="23" class="text-prinx-600" /></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div><label class="label">Date</label><input v-model="expenseForm.expense_date" type="date" class="input"></div>
                    <div><label class="label">Category</label><select v-model="expenseForm.expense_category_id" class="input"><option value="">Select category</option><option v-for="category in activeCategories" :key="category.id" :value="category.id">{{ category.name }}</option></select><p v-if="expenseForm.errors.expense_category_id" class="mt-1 text-xs text-red-600">{{ expenseForm.errors.expense_category_id }}</p></div>
                    <div class="sm:col-span-2"><label class="label">Title</label><input v-model="expenseForm.title" class="input" placeholder="e.g. Office internet for August"><p v-if="expenseForm.errors.title" class="mt-1 text-xs text-red-600">{{ expenseForm.errors.title }}</p></div>
                    <div><label class="label">Amount</label><input v-model="expenseForm.amount" type="number" min="0.01" step="0.01" class="input" placeholder="0"><p v-if="expenseForm.errors.amount" class="mt-1 text-xs text-red-600">{{ expenseForm.errors.amount }}</p></div>
                    <div><label class="label">Payee</label><input v-model="expenseForm.payee" class="input" placeholder="Person or company"></div>
                    <div><label class="label">Payment method</label><select v-model="expenseForm.payment_method" class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank transfer</option><option value="card">Card</option><option value="other">Other</option></select></div>
                    <div><label class="label">Payment reference</label><input v-model="expenseForm.payment_reference" class="input" placeholder="Optional transaction ID"></div>
                    <div class="sm:col-span-2"><label class="label">Receipt / attachment</label><input ref="receiptInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-semibold" @change="expenseForm.receipt = $event.target.files[0] || null"><p class="mt-1 text-[11px] text-slate-400">PDF or image, maximum 5 MB. Stored privately.</p><p v-if="expenseForm.errors.receipt" class="mt-1 text-xs text-red-600">{{ expenseForm.errors.receipt }}</p></div>
                    <label class="sm:col-span-2 flex items-center gap-2 rounded-[8px] border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700"><input v-model="expenseForm.is_recurring" type="checkbox" class="mr-[10px]">Recurring expense</label>
                    <template v-if="expenseForm.is_recurring">
                        <div><label class="label">Frequency</label><select v-model="expenseForm.recurring_interval" class="input"><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></select></div>
                        <div><label class="label">Recurring until</label><input v-model="expenseForm.recurring_until" type="date" class="input"><p class="mt-1 text-[11px] text-slate-400">Optional tracking end date. It does not auto-post future expenses yet.</p></div>
                    </template>
                    <div class="sm:col-span-2"><label class="label">Notes</label><textarea v-model="expenseForm.notes" class="input min-h-20" placeholder="Optional internal notes"></textarea></div>
                </div>
                <button class="btn-primary mt-5" :disabled="expenseForm.processing"><Icon name="tick-circle" :size="17" /> Save expense</button>
            </form>

            <section class="card p-5">
                <div class="flex items-center justify-between"><div><h2 class="font-semibold">Expense categories</h2><p class="mt-1 text-xs text-slate-500">Keep reporting categories consistent instead of free-text labels.</p></div><Icon name="category" :size="21" class="text-prinx-600" /></div>
                <form class="mt-5 grid gap-2 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="submitCategory"><input v-model="categoryForm.name" class="input" placeholder="Category name"><input v-model="categoryForm.description" class="input" placeholder="Description (optional)"><button class="btn-primary" :disabled="categoryForm.processing"><Icon name="add-circle" :size="16" /> Add</button></form>
                <p v-if="categoryForm.errors.name" class="mt-1 text-xs text-red-600">{{ categoryForm.errors.name }}</p>
                <div class="mt-4 max-h-[460px] divide-y divide-slate-100 overflow-auto border-t border-slate-100">
                    <div v-for="category in categories" :key="category.id" class="flex items-center justify-between gap-3 py-3"><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ category.name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ category.description || (category.is_active ? 'Active category' : 'Inactive category') }}</p></div><div class="flex items-center gap-2"><span :class="['rounded-[7px] border px-2 py-1 text-[10px] font-semibold', category.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500']">{{ category.is_active ? 'Active' : 'Inactive' }}</span><button class="flex h-8 w-8 items-center justify-center rounded-[8px] border border-slate-200" @click="startCategoryEdit(category)"><Icon name="edit-2" :size="15" /></button></div></div>
                </div>
            </section>
        </div>

        <section class="card mt-6 p-4">
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[150px_150px_180px_1fr_150px_auto_auto]" @submit.prevent="applyFilters">
                <input v-model="filters.from" type="date" class="input"><input v-model="filters.to" type="date" class="input">
                <select v-model="filters.category" class="input"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                <div class="relative"><Icon name="search-normal" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="filters.search" class="input pl-9" placeholder="Search title, payee, reference"></div>
                <select v-model="filters.status" class="input"><option value="">All statuses</option><option value="posted">Posted</option><option value="voided">Voided</option></select>
                <button class="btn-primary">Apply</button><button type="button" class="btn-light" @click="clearFilters">Clear</button>
            </form>
        </section>

        <section class="card mt-5 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Expense ledger</h2><p class="mt-1 text-xs text-slate-500">Posted records are never deleted; mistakes are voided with a reason.</p></div>
            <div class="overflow-x-auto"><table class="admin-table min-w-[1150px]"><thead><tr><th>Date</th><th>Reference</th><th>Expense</th><th>Category</th><th>Payee</th><th>Payment</th><th>Amount</th><th>Status</th><th>Receipt</th><th class="text-right">Action</th></tr></thead><tbody>
                <tr v-for="expense in expenses" :key="expense.id"><td>{{ expense.expense_date }}</td><td class="font-semibold">{{ expense.reference }}</td><td><p class="font-semibold">{{ expense.title || expense.description }}</p><p v-if="expense.is_recurring" class="mt-0.5 text-[11px] text-prinx-600">Recurring · {{ expense.recurring_interval }}</p></td><td>{{ expense.display_category }}</td><td>{{ expense.payee || '—' }}</td><td><p class="capitalize">{{ String(expense.payment_method).replaceAll('_',' ') }}</p><p v-if="expense.payment_reference" class="text-[11px] text-slate-500">{{ expense.payment_reference }}</p></td><td class="font-semibold">{{ money(expense.amount) }}</td><td><span :class="['rounded-[7px] border px-2 py-1 text-[10px] font-semibold capitalize', statusClass(expense.status)]">{{ expense.status }}</span><p v-if="expense.status === 'voided'" class="mt-1 max-w-40 text-[11px] text-slate-500">{{ expense.void_reason }}</p></td><td><a v-if="expense.receipt_path" :href="`/admin/expenses/${expense.id}/receipt`" class="inline-flex items-center gap-1 text-xs font-semibold text-prinx-600"><Icon name="receipt-item" :size="14" /> View</a><span v-else class="text-xs text-slate-400">—</span></td><td class="text-right"><button v-if="expense.status === 'posted'" class="rounded-[8px] border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50" @click="openVoid(expense)">Void</button></td></tr>
                <tr v-if="!expenses.length"><td colspan="10" class="py-12 text-center text-slate-400">No expenses found for this period.</td></tr>
            </tbody></table></div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Category breakdown</h2><p class="mt-1 text-xs text-slate-500">Posted expenses in the selected range.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Category</th><th>Entries</th><th>Total</th></tr></thead><tbody><tr v-for="row in categoryBreakdown" :key="row.category_name"><td class="font-semibold">{{ row.category_name }}</td><td>{{ row.expense_count }}</td><td class="font-semibold">{{ money(row.total) }}</td></tr><tr v-if="!categoryBreakdown.length"><td colspan="3" class="py-10 text-center text-slate-400">No category data.</td></tr></tbody></table></div></section>
            <section class="card overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Largest expenses</h2><p class="mt-1 text-xs text-slate-500">Highest posted expenses in the selected range.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Expense</th><th>Category</th><th>Total</th></tr></thead><tbody><tr v-for="row in largestExpenses" :key="row.id"><td>{{ row.expense_date }}</td><td class="font-semibold">{{ row.title || row.description }}</td><td>{{ row.display_category }}</td><td class="font-semibold">{{ money(row.amount) }}</td></tr><tr v-if="!largestExpenses.length"><td colspan="4" class="py-10 text-center text-slate-400">No expenses.</td></tr></tbody></table></div></section>
        </div>

        <section class="card mt-6 overflow-hidden"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">12-month comparison</h2><p class="mt-1 text-xs text-slate-500">Monthly posted operating expenses.</p></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Month</th><th>Total expenses</th></tr></thead><tbody><tr v-for="row in monthly" :key="row.month_key"><td class="font-semibold">{{ row.month_key }}</td><td>{{ money(row.total) }}</td></tr><tr v-if="!monthly.length"><td colspan="2" class="py-10 text-center text-slate-400">No monthly expense history yet.</td></tr></tbody></table></div></section>

        <div v-if="editingCategory" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-4" @click.self="editingCategory=null">
            <form class="w-full max-w-md rounded-[10px] bg-white p-5 shadow-2xl" @submit.prevent="saveCategory"><div class="flex items-center justify-between"><h3 class="font-semibold">Edit category</h3><button type="button" @click="editingCategory=null"><Icon name="close-circle" :size="20" /></button></div><div class="mt-4 grid gap-3"><div><label class="label">Name</label><input v-model="editCategoryForm.name" class="input"></div><div><label class="label">Description</label><textarea v-model="editCategoryForm.description" class="input min-h-20"></textarea></div><label class="flex items-center gap-2 text-sm font-medium"><input v-model="editCategoryForm.is_active" type="checkbox" class="mr-[10px]">Active category</label><button class="btn-primary" :disabled="editCategoryForm.processing">Save changes</button></div></form>
        </div>

        <div v-if="voiding" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-4" @click.self="closeVoid">
            <form class="w-full max-w-md rounded-[10px] bg-white p-5 shadow-2xl" @submit.prevent="submitVoid"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-[.13em] text-red-500">Void expense</p><h3 class="mt-1 font-semibold">{{ voiding.reference }}</h3></div><button type="button" @click="closeVoid"><Icon name="close-circle" :size="20" /></button></div><p class="mt-3 text-sm text-slate-500">This does not delete the record. It removes the expense from active financial totals while preserving the original entry and audit reason.</p><textarea v-model="voidForm.reason" class="input mt-4 min-h-24" placeholder="Reason for voiding"></textarea><p v-if="voidForm.errors.reason || voidForm.errors.void" class="mt-1 text-xs text-red-600">{{ voidForm.errors.reason || voidForm.errors.void }}</p><div class="mt-4 flex justify-end gap-2"><button type="button" class="btn-light" @click="closeVoid">Cancel</button><button class="rounded-[8px] bg-red-600 px-4 py-2.5 text-sm font-semibold text-white" :disabled="voidForm.processing">Void expense</button></div></form>
        </div>
    </AdminLayout>
</template>
