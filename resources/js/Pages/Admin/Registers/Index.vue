<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ current: Object, sessions: Array, summary: Object });
const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));

const openForm = useForm({ opening_cash: 0, opening_notes: '' });
const movementForm = useForm({ direction: 'in', amount: '', reason: '', notes: '' });
const closeForm = useForm({ counted_cash: '', closing_notes: '' });

const openRegister = () => openForm.post('/admin/registers/open');
const addMovement = () => movementForm.post('/admin/registers/movement', { preserveScroll: true, onSuccess: () => movementForm.reset('amount', 'reason', 'notes') });
const closeRegister = () => closeForm.post('/admin/registers/close');
</script>

<template>
    <AdminLayout>
        <Head title="Cash Registers" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">POS cash control</p>
                <h1 class="section-title">Cash registers</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Open and close cashier shifts, record drawer cash movements and reconcile the expected drawer balance against the physical cash count.</p>
            </div>
            <Link href="/admin/pos" class="btn-light"><Icon name="shop" :size="16" /> Point of sale</Link>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Open shifts</p><b class="mt-2 block text-2xl">{{ summary.open_sessions }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Closed today</p><b class="mt-2 block text-2xl">{{ summary.today_closed }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Today variance</p><b :class="['mt-2 block text-2xl', Number(summary.today_variance) < 0 ? 'text-red-600' : '']">{{ money(summary.today_variance) }}</b></div>
        </div>

        <div v-if="!current" class="card mt-6 p-5">
            <div class="flex items-start gap-3"><Icon name="wallet" :size="22" class="text-prinx-600" /><div><h2 class="font-semibold">Open your register</h2><p class="mt-1 text-sm text-slate-500">A register must be open before POS sales, returns or voids can be posted.</p></div></div>
            <form class="mt-5 grid gap-3 md:grid-cols-[220px_1fr_auto]" @submit.prevent="openRegister">
                <input v-model="openForm.opening_cash" type="number" min="0" step="0.01" class="input" placeholder="Opening cash">
                <input v-model="openForm.opening_notes" class="input" placeholder="Opening note (optional)">
                <button class="btn-primary" :disabled="openForm.processing"><Icon name="play-circle" :size="16" /> Open register</button>
            </form>
            <p v-if="openForm.errors.register" class="mt-3 text-sm text-red-600">{{ openForm.errors.register }}</p>
        </div>

        <template v-else>
            <div class="card mt-6 p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div><p class="text-xs font-semibold uppercase tracking-[.14em] text-emerald-600">Register open</p><h2 class="mt-1 text-lg font-semibold">{{ current.session.reference }}</h2><p class="mt-1 text-xs text-slate-500">Opened {{ current.session.opened_at }}</p></div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div><p class="text-xs text-slate-500">Opening</p><b>{{ money(current.session.opening_cash) }}</b></div>
                        <div><p class="text-xs text-slate-500">Cash sales</p><b>{{ money(current.summary.cash_sales) }}</b></div>
                        <div><p class="text-xs text-slate-500">Cash refunds</p><b>{{ money(current.summary.cash_refunds) }}</b></div>
                        <div><p class="text-xs text-slate-500">Register expenses</p><b>{{ money(current.summary.expense_cash_out) }}</b></div>
                        <div><p class="text-xs text-slate-500">Expected</p><b>{{ money(current.summary.expected_cash) }}</b></div>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-2">
                <form class="card p-5" @submit.prevent="addMovement">
                    <h2 class="font-semibold">Drawer movement</h2><p class="mt-1 text-xs text-slate-500">POS cash sales/refunds are automatic. Use this form for other drawer movements and expenses paid directly from the register.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2"><select v-model="movementForm.direction" class="input"><option value="in">Cash in</option><option value="out">Cash out</option><option value="expense">Expense paid from register</option></select><input v-model="movementForm.amount" type="number" min="0.01" step="0.01" class="input" placeholder="Amount"></div>
                    <input v-model="movementForm.reason" class="input mt-3" :placeholder="movementForm.direction === 'expense' ? 'Expense title / reason' : 'Reason'">
                    <textarea v-model="movementForm.notes" class="input mt-3 min-h-20" placeholder="Notes"></textarea>
                    <button class="btn-primary mt-3" :disabled="movementForm.processing"><Icon name="add-circle" :size="16" /> Record movement</button>
                    <p v-if="movementForm.errors.movement" class="mt-2 text-sm text-red-600">{{ movementForm.errors.movement }}</p>
                </form>

                <form class="card p-5" @submit.prevent="closeRegister">
                    <h2 class="font-semibold">Close register</h2><p class="mt-1 text-xs text-slate-500">Count the physical drawer cash. The system will preserve the expected balance and calculate the over/short variance.</p>
                    <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50 p-3"><p class="text-xs text-slate-500">Expected closing cash</p><b class="mt-1 block text-xl">{{ money(current.summary.expected_cash) }}</b></div>
                    <input v-model="closeForm.counted_cash" type="number" min="0" step="0.01" class="input mt-3" placeholder="Counted physical cash">
                    <textarea v-model="closeForm.closing_notes" class="input mt-3 min-h-20" placeholder="Closing notes"></textarea>
                    <button class="btn-primary mt-3" :disabled="closeForm.processing"><Icon name="stop-circle" :size="16" /> Close register</button>
                    <p v-if="closeForm.errors.close" class="mt-2 text-sm text-red-600">{{ closeForm.errors.close }}</p>
                </form>
            </div>

            <div class="card mt-5 overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Current drawer movements</h2></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Reference</th><th>Type</th><th>Direction</th><th>Amount</th><th>Reason</th><th>Time</th></tr></thead><tbody><tr v-for="m in current.movements" :key="m.id"><td>{{ m.reference }}</td><td>{{ m.type }}</td><td :class="m.direction === 'in' ? 'text-emerald-600' : 'text-red-600'">{{ m.direction === 'in' ? 'In' : 'Out' }}</td><td class="font-semibold">{{ money(m.amount) }}</td><td>{{ m.reason || '—' }}</td><td>{{ m.created_at }}</td></tr><tr v-if="!current.movements.length"><td colspan="6" class="py-8 text-center text-slate-400">No drawer movements yet.</td></tr></tbody></table></div>
            </div>
        </template>

        <div class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Shift history</h2><p class="mt-1 text-xs text-slate-500">Open and closed cashier register sessions.</p></div>
            <div class="overflow-x-auto"><table class="admin-table min-w-[900px]"><thead><tr><th>Reference</th><th>Cashier</th><th>Opened</th><th>Closed</th><th>Opening cash</th><th>Expected</th><th>Counted</th><th>Variance</th><th>Status</th></tr></thead><tbody><tr v-for="session in sessions" :key="session.id"><td><Link :href="`/admin/registers/${session.id}`" class="font-semibold text-prinx-600 hover:underline">{{ session.reference }}</Link></td><td>{{ session.cashier_name }}</td><td>{{ session.opened_at }}</td><td>{{ session.closed_at || '—' }}</td><td>{{ money(session.opening_cash) }}</td><td>{{ session.expected_cash === null ? '—' : money(session.expected_cash) }}</td><td>{{ session.counted_cash === null ? '—' : money(session.counted_cash) }}</td><td :class="Number(session.variance) < 0 ? 'text-red-600' : Number(session.variance) > 0 ? 'text-amber-600' : ''">{{ session.variance === null ? '—' : money(session.variance) }}</td><td><span class="rounded-[8px] border px-2 py-1 text-xs font-semibold">{{ session.status }}</span></td></tr></tbody></table></div>
        </div>
    </AdminLayout>
</template>
