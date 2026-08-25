<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    reservationPercent: { type: Number, default: 70 },
});

const form = useForm({
    customer_id: '',
    customer_name: '',
    customer_phone: '',
    sms_language: 'en',
    items: [{ product_id: '', quantity: 1 }],
    discount_type: 'none',
    discount_value: 0,
    initial_payment: 0,
    payment_method_id: props.paymentMethods[0]?.id || '',
    external_reference: '',
    notes: '',
});

const customers = ref([]);
const customersLoading = ref(true);
const customerMode = ref('existing');
const customerSearch = ref('');
const filterForm = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));
const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const statusClass = (status) => ({
    active: 'bg-amber-50 text-amber-700',
    paid: 'bg-emerald-50 text-emerald-700',
    released: 'bg-sky-50 text-sky-700',
    cancelled: 'bg-slate-100 text-slate-500',
}[status] || 'bg-slate-100 text-slate-600');

const selectedCustomer = computed(() => customers.value.find((customer) => Number(customer.id) === Number(form.customer_id)) || null);
const filteredCustomers = computed(() => {
    const query = customerSearch.value.trim().toLowerCase();
    if (!query) return customers.value;
    return customers.value.filter((customer) => [customer.name, customer.phone, customer.email, customer.tin]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(query)));
});

const subtotal = computed(() => form.items.reduce((total, item) => {
    const product = productById(item.product_id);
    return total + (Number(product?.price || 0) * Math.max(1, Number(item.quantity || 1)));
}, 0));
const discountAmount = computed(() => {
    const value = Math.max(0, Number(form.discount_value || 0));
    if (form.discount_type === 'fixed') return Math.min(subtotal.value, value);
    if (form.discount_type === 'percent') return Math.min(subtotal.value, subtotal.value * Math.min(100, value) / 100);
    return 0;
});
const netTotal = computed(() => Math.max(0, subtotal.value - discountAmount.value));
const reservationAmount = computed(() => netTotal.value * (Number(props.reservationPercent || 70) / 100));
const initialPaymentReservesStock = computed(() => netTotal.value > 0 && Number(form.initial_payment || 0) >= reservationAmount.value);

watch(selectedCustomer, (customer) => {
    if (customerMode.value !== 'existing') return;
    form.customer_name = customer?.name || '';
    form.customer_phone = customer?.phone || '';
});

const setCustomerMode = (mode) => {
    customerMode.value = mode;
    form.customer_id = '';
    form.customer_name = '';
    form.customer_phone = '';
    customerSearch.value = '';
    form.clearErrors('customer_id', 'customer_name', 'customer_phone');
};

onMounted(async () => {
    try {
        const response = await fetch('/admin/customers/options', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.ok) {
            const payload = await response.json();
            customers.value = payload.customers || [];
            if (!customers.value.length) customerMode.value = 'new';
        }
    } finally {
        customersLoading.value = false;
    }
});

const addRow = () => form.items.push({ product_id: '', quantity: 1 });
const removeRow = (index) => { if (form.items.length > 1) form.items.splice(index, 1); };
const submit = () => form.post('/admin/installments', { preserveScroll: true });
const applyFilters = () => router.get('/admin/installments', {
    search: filterForm.search || undefined,
    status: filterForm.status || undefined,
}, { preserveState: true, replace: true });
</script>

<template>
    <AdminLayout>
        <Head title="Installments" />

        <div class="mx-auto max-w-[1500px] space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[.16em] text-prinx-600">Sales & customers</p>
                    <h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950">Installment plans</h1>
                    <p class="mt-1 text-[12px] text-slate-500">Create product agreements and collect payments gradually. Stock is reserved only after payments reach {{ reservationPercent }}% of the agreed amount.</p>
                </div>
                <Link href="/admin/customers" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3.5 text-[11px] font-semibold text-slate-700 hover:border-prinx-300 hover:text-prinx-600">Manage customers</Link>
            </div>

            <div class="grid grid-cols-2 gap-2 lg:grid-cols-5">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Plans</p><strong class="mt-1 block text-xl font-semibold text-slate-950">{{ summary.plans || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Active</p><strong class="mt-1 block text-xl font-semibold text-amber-600">{{ summary.active || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Fully paid</p><strong class="mt-1 block text-xl font-semibold text-emerald-600">{{ summary.fully_paid || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Released</p><strong class="mt-1 block text-xl font-semibold text-sky-600">{{ summary.released || 0 }}</strong></div>
                <div class="col-span-2 rounded-[8px] border border-slate-200 bg-white p-3 lg:col-span-1"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Outstanding</p><strong class="mt-1 block text-[15px] font-semibold text-slate-950">{{ money(summary.outstanding) }}</strong></div>
            </div>

            <section class="rounded-[9px] border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Create installment plan</h2><p class="mt-1 text-[11px] text-slate-500">Creating a plan does not remove stock. The selected quantities are automatically reserved when cumulative payments reach {{ reservationPercent }}%.</p></div>
                <form class="grid gap-4 p-4 xl:grid-cols-[.8fr_1.2fr_.7fr]" @submit.prevent="submit">
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-1 rounded-[7px] bg-slate-100 p-1">
                            <button type="button" :class="['h-8 rounded-[5px] text-[10px] font-semibold transition', customerMode === 'existing' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500']" @click="setCustomerMode('existing')">Existing customer</button>
                            <button type="button" :class="['h-8 rounded-[5px] text-[10px] font-semibold transition', customerMode === 'new' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500']" @click="setCustomerMode('new')">New customer</button>
                        </div>

                        <template v-if="customerMode === 'existing'">
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Find customer</span><input v-model="customerSearch" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="Search name, phone, email or TIN"></label>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Customer</span><select v-model="form.customer_id" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-[11px] outline-none focus:border-prinx-400" required><option value="">{{ customersLoading ? 'Loading customers…' : 'Choose existing customer' }}</option><option v-for="customer in filteredCustomers" :key="customer.id" :value="customer.id">{{ customer.name }} · {{ customer.phone }}</option></select></label>
                            <div v-if="selectedCustomer" class="rounded-[7px] border border-emerald-100 bg-emerald-50/60 p-2.5"><strong class="block text-[11px] font-semibold text-slate-900">{{ selectedCustomer.name }}</strong><p class="mt-0.5 text-[10px] text-slate-600">{{ selectedCustomer.phone }}</p><p v-if="selectedCustomer.email || selectedCustomer.tin" class="mt-1 text-[9px] text-slate-500"><span v-if="selectedCustomer.email">{{ selectedCustomer.email }}</span><span v-if="selectedCustomer.email && selectedCustomer.tin"> · </span><span v-if="selectedCustomer.tin">TIN {{ selectedCustomer.tin }}</span></p></div>
                            <p v-if="!customersLoading && !customers.length" class="rounded-[6px] bg-amber-50 px-2.5 py-2 text-[10px] text-amber-700">No saved customers yet. Choose New customer to add the first one.</p>
                        </template>

                        <template v-else>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Customer name</span><input v-model="form.customer_name" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" required></label>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Phone number</span><input v-model="form.customer_phone" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="0712 345 678" required></label>
                            <p class="text-[9px] leading-4 text-slate-400">The new person will also be saved in Customers and available for future POS or installment transactions.</p>
                        </template>

                        <label class="block">
                            <span class="mb-1 block text-[10px] font-semibold text-slate-600">SMS confirmation language</span>
                            <select v-model="form.sms_language" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs outline-none focus:border-prinx-400" required>
                                <option value="en">English</option>
                                <option value="sw">Swahili</option>
                            </select>
                            <span class="mt-1 block text-[9px] leading-4 text-slate-400">All installment payment confirmation messages for this plan will use this language.</span>
                        </label>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Plan notes</span><textarea v-model="form.notes" rows="3" class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-xs outline-none focus:border-prinx-400" placeholder="Optional agreement notes"></textarea></label>
                        <p v-if="form.errors.customer_id || form.errors.customer_name || form.errors.customer_phone || form.errors.sms_language || form.errors.items" class="text-[11px] text-red-600">{{ form.errors.customer_id || form.errors.customer_name || form.errors.customer_phone || form.errors.sms_language || form.errors.items }}</p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between"><span class="text-[10px] font-semibold text-slate-600">Products</span><button type="button" class="text-[10px] font-semibold text-prinx-600" @click="addRow">+ Add product</button></div>
                        <div class="space-y-2">
                            <div v-for="(item, index) in form.items" :key="index" class="grid gap-2 rounded-[7px] border border-slate-200 p-2.5 sm:grid-cols-[1fr_90px_92px_32px] sm:items-center">
                                <select v-model="item.product_id" class="h-9 min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-[11px] outline-none focus:border-prinx-400" required>
                                    <option value="">Choose product</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ money(product.price) }} · {{ product.track_stock ? `${product.stock_quantity} left` : 'unlimited' }}</option>
                                </select>
                                <input v-model.number="item.quantity" type="number" min="1" max="100" class="h-9 rounded-[6px] border border-slate-200 px-2 text-center text-xs outline-none focus:border-prinx-400">
                                <div class="text-right text-[11px] font-semibold text-slate-800">{{ money(Number(productById(item.product_id)?.price || 0) * Number(item.quantity || 1)) }}</div>
                                <button type="button" class="flex h-8 w-8 items-center justify-center rounded-[6px] text-slate-400 hover:bg-red-50 hover:text-red-600" :disabled="form.items.length === 1" @click="removeRow(index)">×</button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-[8px] bg-slate-50 p-3">
                        <div class="grid grid-cols-[1fr_110px] gap-2">
                            <label><span class="mb-1 block text-[10px] font-semibold text-slate-600">Discount</span><select v-model="form.discount_type" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs"><option value="none">No discount</option><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select></label>
                            <label><span class="mb-1 block text-[10px] font-semibold text-slate-600">Value</span><input v-model.number="form.discount_value" type="number" min="0" step="0.01" :disabled="form.discount_type === 'none'" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs disabled:bg-slate-100"></label>
                        </div>
                        <div class="border-y border-slate-200 py-2 text-[11px]">
                            <div class="flex justify-between py-1"><span class="text-slate-500">Subtotal</span><strong>{{ money(subtotal) }}</strong></div>
                            <div class="flex justify-between py-1"><span class="text-slate-500">Discount</span><strong class="text-prinx-600">-{{ money(discountAmount) }}</strong></div>
                            <div class="flex justify-between py-1.5 text-[13px]"><span class="font-semibold text-slate-800">Net total</span><strong>{{ money(netTotal) }}</strong></div>
                            <div class="flex justify-between py-1 text-[10px]"><span class="text-slate-500">Stock reservation at {{ reservationPercent }}%</span><strong class="text-slate-700">{{ money(reservationAmount) }}</strong></div>
                        </div>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Initial payment</span><input v-model.number="form.initial_payment" type="number" min="0" step="0.01" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs"></label>
                        <p v-if="netTotal > 0" :class="['rounded-[6px] px-2.5 py-2 text-[10px] leading-4', initialPaymentReservesStock ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">{{ initialPaymentReservesStock ? `This payment reaches ${reservationPercent}%. Stock will be reserved immediately.` : `Stock remains sellable until cumulative payments reach ${money(reservationAmount)} (${reservationPercent}%).` }}</p>
                        <label v-if="Number(form.initial_payment || 0) > 0" class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Payment method</span><select v-model="form.payment_method_id" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs"><option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option></select></label>
                        <label v-if="Number(form.initial_payment || 0) > 0" class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Payment reference</span><input v-model="form.external_reference" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs" placeholder="Optional M-Pesa/bank reference"></label>
                        <p v-if="form.errors.discount_value || form.errors.initial_payment || form.errors.payment_method_id" class="text-[11px] text-red-600">{{ form.errors.discount_value || form.errors.initial_payment || form.errors.payment_method_id }}</p>
                        <button type="submit" class="flex h-10 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="form.processing || !netTotal || (customerMode === 'existing' && !form.customer_id)">{{ form.processing ? 'Creating…' : 'Create installment plan' }}</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold text-slate-950">Customer plans</h2><p class="mt-1 text-[11px] text-slate-500">Open a plan to record payments, check reservation progress, resend SMS confirmations or release paid items.</p></div><form class="flex gap-2" @submit.prevent="applyFilters"><input v-model="filterForm.search" class="h-8 w-44 rounded-[6px] border border-slate-200 px-2 text-[11px]" placeholder="Search customer/ref"><select v-model="filterForm.status" class="h-8 rounded-[6px] border border-slate-200 bg-white px-2 text-[11px]"><option value="">All statuses</option><option value="active">Active</option><option value="paid">Paid</option><option value="released">Released</option><option value="cancelled">Cancelled</option></select><button class="h-8 rounded-[6px] bg-slate-950 px-3 text-[11px] font-semibold text-white">Filter</button></form></div>
                <div v-if="plans.length" class="overflow-x-auto">
                    <table class="min-w-[850px] w-full text-left text-[11px]"><thead class="bg-slate-50 text-[9px] uppercase tracking-[.08em] text-slate-500"><tr><th class="px-4 py-2.5">Reference</th><th class="px-4 py-2.5">Customer</th><th class="px-4 py-2.5">Net total</th><th class="px-4 py-2.5">Paid</th><th class="px-4 py-2.5">Remaining</th><th class="px-4 py-2.5">Progress</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5"></th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="plan in plans" :key="plan.id" class="hover:bg-slate-50"><td class="px-4 py-3 font-semibold text-slate-900">{{ plan.reference }}</td><td class="px-4 py-3"><strong class="block font-medium text-slate-800">{{ plan.customer_name }}</strong><span class="text-[10px] text-slate-400">{{ plan.customer_phone }}</span></td><td class="px-4 py-3">{{ money(plan.net_total) }}</td><td class="px-4 py-3 text-emerald-700">{{ money(plan.paid_amount) }}</td><td class="px-4 py-3 font-semibold">{{ money(plan.remaining_amount) }}</td><td class="px-4 py-3"><div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-prinx-500" :style="{ width: `${plan.progress}%` }"></div></div><span class="mt-1 block text-[9px] text-slate-400">{{ plan.progress }}%</span></td><td class="px-4 py-3"><span :class="['rounded-full px-2 py-1 text-[9px] font-semibold capitalize', statusClass(plan.status)]">{{ plan.status }}</span></td><td class="px-4 py-3 text-right"><Link :href="`/admin/installments/${plan.reference}`" class="text-[10px] font-semibold text-prinx-600">Open →</Link></td></tr></tbody></table>
                </div>
                <div v-else class="px-4 py-10 text-center text-xs text-slate-500">No installment plans found.</div>
            </section>
        </div>
    </AdminLayout>
</template>