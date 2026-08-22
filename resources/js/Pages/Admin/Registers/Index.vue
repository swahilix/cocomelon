<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ current: Object, sessions: Array, summary: Object, paymentMethods: Array, activePaymentMethods: Array });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));
const cashMethod = computed(() => props.paymentMethods.find((method) => Boolean(method.is_cash)) || null);
const currentMethods = computed(() => props.current?.method_balances || []);

const openForm = useForm({
    opening_balances: Object.fromEntries((props.activePaymentMethods || []).map((method) => [method.id, 0])),
    opening_notes: '',
});
const movementForm = useForm({
    payment_method_id: currentMethods.value[0]?.payment_method_id || cashMethod.value?.id || '',
    direction: 'in', amount: '', reason: '', notes: '',
});
const closeForm = useForm({
    counted_balances: Object.fromEntries(currentMethods.value.map((balance) => [balance.payment_method_id, ''])),
    closing_notes: '',
});
const editingMethod = ref(null);
const methodForm = useForm({ name: '', type: 'mobile_money', account_name: '', account_number: '', instructions: '', is_active: true });

const openRegister = () => openForm.post('/admin/registers/open');
const addMovement = () => movementForm.post('/admin/registers/movement', { preserveScroll: true, onSuccess: () => movementForm.reset('amount', 'reason', 'notes') });
const closeRegister = () => closeForm.post('/admin/registers/close');
const beginEditMethod = (method) => {
    if (method.is_system || method.is_cash) return;
    editingMethod.value = method.id;
    methodForm.name = method.name;
    methodForm.type = method.type;
    methodForm.account_name = method.account_name || '';
    methodForm.account_number = method.account_number || '';
    methodForm.instructions = method.instructions || '';
    methodForm.is_active = Boolean(method.is_active);
};
const resetMethodForm = () => {
    editingMethod.value = null;
    methodForm.reset();
    methodForm.type = 'mobile_money';
    methodForm.is_active = true;
};
const saveMethod = () => {
    if (editingMethod.value) {
        methodForm.patch(`/admin/registers/payment-methods/${editingMethod.value}`, { preserveScroll: true, onSuccess: resetMethodForm });
    } else {
        methodForm.post('/admin/registers/payment-methods', { preserveScroll: true, onSuccess: resetMethodForm });
    }
};
</script>

<template>
    <AdminLayout>
        <Head title="Cash Registers" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">POS payment control</p>
                <h1 class="section-title">Cash registers</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Track the opening and closing balance of Cash, mobile-money Lipa numbers, bank accounts and other payment methods used during each cashier shift.</p>
            </div>
            <Link href="/admin/pos" class="btn-light"><Icon name="shop" :size="16" /> Point of sale</Link>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Open shifts</p><b class="mt-2 block text-2xl">{{ summary.open_sessions }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Closed today</p><b class="mt-2 block text-2xl">{{ summary.today_closed }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Today total variance</p><b :class="['mt-2 block text-2xl', Number(summary.today_variance) < 0 ? 'text-red-600' : Number(summary.today_variance) > 0 ? 'text-amber-600' : '']">{{ money(summary.today_variance) }}</b></div>
        </div>

        <div v-if="!current" class="card mt-6 p-5">
            <div class="flex items-start gap-3"><Icon name="wallet" :size="22" class="text-prinx-600" /><div><h2 class="font-semibold">Open your register</h2><p class="mt-1 text-sm text-slate-500">Enter the balance already available in each payment method before the shift starts.</p></div></div>
            <form class="mt-5" @submit.prevent="openRegister">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label v-for="method in activePaymentMethods" :key="method.id" class="rounded-[9px] border border-slate-200 bg-slate-50/60 p-3">
                        <span class="flex items-center justify-between gap-2"><b class="text-sm">{{ method.name }}</b><span v-if="method.is_cash" class="rounded-[6px] bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700">Built-in</span></span>
                        <span v-if="method.account_number || method.account_name" class="mt-1 block text-[11px] text-slate-500">{{ method.account_number || method.account_name }}<span v-if="method.account_number && method.account_name"> · {{ method.account_name }}</span></span>
                        <span class="mt-3 block text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Opening balance</span>
                        <input v-model="openForm.opening_balances[method.id]" type="number" min="0" step="0.01" class="input mt-1" placeholder="0">
                    </label>
                </div>
                <input v-model="openForm.opening_notes" class="input mt-3" placeholder="Opening note (optional)">
                <button class="btn-primary mt-3" :disabled="openForm.processing"><Icon name="play-circle" :size="16" /> Open register</button>
            </form>
            <p v-if="openForm.errors.register" class="mt-3 text-sm text-red-600">{{ openForm.errors.register }}</p>
        </div>

        <template v-else>
            <div class="card mt-6 p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div><p class="text-xs font-semibold uppercase tracking-[.14em] text-emerald-600">Register open</p><h2 class="mt-1 text-lg font-semibold">{{ current.session.reference }}</h2><p class="mt-1 text-xs text-slate-500">Opened {{ current.session.opened_at }}</p></div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><div><p class="text-xs text-slate-500">Cash sales</p><b>{{ money(current.summary.cash_sales) }}</b></div><div><p class="text-xs text-slate-500">Non-cash sales</p><b>{{ money(current.summary.non_cash_sales) }}</b></div><div><p class="text-xs text-slate-500">Cash refunds</p><b>{{ money(current.summary.cash_refunds) }}</b></div></div>
                </div>
                <div class="mt-4 overflow-x-auto rounded-[9px] border border-slate-200"><table class="admin-table"><thead><tr><th>Payment method</th><th>Opening</th><th>Expected now</th><th>Account</th></tr></thead><tbody><tr v-for="balance in current.method_balances" :key="balance.id"><td class="font-semibold">{{ balance.payment_method_name }}</td><td>{{ money(balance.opening_balance) }}</td><td class="font-semibold text-prinx-600">{{ money(balance.expected_closing_balance) }}</td><td>{{ balance.account_number || balance.account_name || '—' }}</td></tr></tbody></table></div>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-2">
                <form class="card p-5" @submit.prevent="addMovement">
                    <h2 class="font-semibold">Register movement</h2><p class="mt-1 text-xs text-slate-500">Sales and refunds are posted automatically. Use this for manual deposits, withdrawals or payments made directly from one of the register accounts.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3"><select v-model="movementForm.payment_method_id" class="input"><option v-for="balance in current.method_balances" :key="balance.payment_method_id" :value="balance.payment_method_id">{{ balance.payment_method_name }}</option></select><select v-model="movementForm.direction" class="input"><option value="in">Money in</option><option value="out">Money out</option><option value="expense">Expense paid</option></select><input v-model="movementForm.amount" type="number" min="0.01" step="0.01" class="input" placeholder="Amount"></div>
                    <input v-model="movementForm.reason" class="input mt-3" placeholder="Reason">
                    <textarea v-model="movementForm.notes" class="input mt-3 min-h-20" placeholder="Notes"></textarea>
                    <button class="btn-primary mt-3" :disabled="movementForm.processing"><Icon name="add-circle" :size="16" /> Record movement</button>
                    <p v-if="movementForm.errors.movement" class="mt-2 text-sm text-red-600">{{ movementForm.errors.movement }}</p>
                </form>

                <form class="card p-5" @submit.prevent="closeRegister">
                    <h2 class="font-semibold">Close register</h2><p class="mt-1 text-xs text-slate-500">Enter the actual closing balance visible in each payment method. The system compares it with the expected balance.</p>
                    <div class="mt-4 space-y-2">
                        <div v-for="balance in current.method_balances" :key="balance.id" class="grid gap-2 rounded-[8px] border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[1fr_150px_150px] sm:items-center">
                            <div><b class="text-sm">{{ balance.payment_method_name }}</b><p v-if="balance.account_number || balance.account_name" class="mt-0.5 text-[10px] text-slate-500">{{ balance.account_number || balance.account_name }}</p></div>
                            <div><p class="text-[10px] uppercase text-slate-400">Expected</p><b>{{ money(balance.expected_closing_balance) }}</b></div>
                            <div><p class="text-[10px] uppercase text-slate-400">Counted / actual</p><input v-model="closeForm.counted_balances[balance.payment_method_id]" type="number" min="0" step="0.01" class="input mt-1" placeholder="0"></div>
                        </div>
                    </div>
                    <textarea v-model="closeForm.closing_notes" class="input mt-3 min-h-20" placeholder="Closing notes"></textarea>
                    <button class="btn-primary mt-3" :disabled="closeForm.processing"><Icon name="stop-circle" :size="16" /> Close register</button>
                    <p v-if="closeForm.errors.close" class="mt-2 text-sm text-red-600">{{ closeForm.errors.close }}</p>
                </form>
            </div>

            <div class="card mt-5 overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Current register movements</h2></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Reference</th><th>Method</th><th>Type</th><th>Direction</th><th>Amount</th><th>Reason</th><th>Time</th></tr></thead><tbody><tr v-for="m in current.movements" :key="m.id"><td>{{ m.reference }}</td><td class="font-semibold">{{ m.payment_method_name || 'Cash' }}</td><td>{{ m.type }}</td><td :class="m.direction === 'in' ? 'text-emerald-600' : 'text-red-600'">{{ m.direction === 'in' ? 'In' : 'Out' }}</td><td class="font-semibold">{{ money(m.amount) }}</td><td>{{ m.reason || '—' }}</td><td>{{ m.created_at }}</td></tr><tr v-if="!current.movements.length"><td colspan="7" class="py-8 text-center text-slate-400">No register movements yet.</td></tr></tbody></table></div>
            </div>
        </template>

        <section class="card mt-6 p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="font-semibold">Payment methods</h2><p class="mt-1 text-xs text-slate-500">Cash is built in. Add the actual accounts used by the business, such as M-Pesa Lipa Namba, TigoPesa Lipa Namba or NMB Bank Account.</p></div><span class="rounded-[7px] bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">Changes apply to newly opened shifts</span></div>

            <form class="mt-4 grid gap-2 lg:grid-cols-6" @submit.prevent="saveMethod">
                <input v-model="methodForm.name" class="input lg:col-span-2" placeholder="Method name, e.g. M-Pesa Lipa Namba">
                <select v-model="methodForm.type" class="input"><option value="mobile_money">Mobile money</option><option value="bank">Bank account</option><option value="card">Card terminal</option><option value="other">Other</option></select>
                <input v-model="methodForm.account_number" class="input" placeholder="Lipa / account number">
                <input v-model="methodForm.account_name" class="input" placeholder="Account name">
                <label class="flex items-center gap-2 rounded-[8px] border border-slate-200 px-3 text-xs"><input v-model="methodForm.is_active" type="checkbox"> Active</label>
                <input v-model="methodForm.instructions" class="input lg:col-span-5" placeholder="Instructions or notes (optional)">
                <div class="flex gap-2"><button class="btn-primary flex-1" :disabled="methodForm.processing"><Icon :name="editingMethod ? 'edit-2' : 'add-circle'" :size="15" /> {{ editingMethod ? 'Update' : 'Add' }}</button><button v-if="editingMethod" type="button" class="btn-light" @click="resetMethodForm">Cancel</button></div>
            </form>
            <p v-if="methodForm.errors.payment_method" class="mt-2 text-sm text-red-600">{{ methodForm.errors.payment_method }}</p>

            <div class="mt-4 overflow-x-auto rounded-[9px] border border-slate-200"><table class="admin-table"><thead><tr><th>Name</th><th>Type</th><th>Account / Lipa number</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="method in paymentMethods" :key="method.id"><td><div class="flex items-center gap-2"><b>{{ method.name }}</b><span v-if="method.is_cash" class="rounded-[6px] bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700">Built-in</span></div></td><td>{{ method.type }}</td><td><p>{{ method.account_number || '—' }}</p><p v-if="method.account_name" class="text-[10px] text-slate-500">{{ method.account_name }}</p></td><td><span :class="['status', method.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500']">{{ method.is_active ? 'Active' : 'Inactive' }}</span></td><td class="text-right"><button v-if="!method.is_system && !method.is_cash" type="button" class="btn-light min-h-8 px-2 py-1 text-[11px]" @click="beginEditMethod(method)"><Icon name="edit-2" :size="14" /> Edit</button></td></tr></tbody></table></div>
        </section>

        <div class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Shift history</h2><p class="mt-1 text-xs text-slate-500">Open a shift to view all payment-method balances in detail.</p></div>
            <div class="overflow-x-auto"><table class="admin-table min-w-[900px]"><thead><tr><th>Reference</th><th>Cashier</th><th>Opened</th><th>Closed</th><th>Opening cash</th><th>Expected cash</th><th>Counted cash</th><th>Cash variance</th><th>Status</th></tr></thead><tbody><tr v-for="session in sessions" :key="session.id"><td><Link :href="`/admin/registers/${session.id}`" class="font-semibold text-prinx-600 hover:underline">{{ session.reference }}</Link></td><td>{{ session.cashier_name }}</td><td>{{ session.opened_at }}</td><td>{{ session.closed_at || '—' }}</td><td>{{ money(session.opening_cash) }}</td><td>{{ session.expected_cash === null ? '—' : money(session.expected_cash) }}</td><td>{{ session.counted_cash === null ? '—' : money(session.counted_cash) }}</td><td :class="Number(session.variance) < 0 ? 'text-red-600' : Number(session.variance) > 0 ? 'text-amber-600' : ''">{{ session.variance === null ? '—' : money(session.variance) }}</td><td><span class="rounded-[8px] border px-2 py-1 text-xs font-semibold">{{ session.status }}</span></td></tr></tbody></table></div>
        </div>
    </AdminLayout>
</template>
