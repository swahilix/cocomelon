<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    warranties: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    today: { type: String, default: '' },
});

const presets = [
    { label: '3 months', value: 3, unit: 'months' },
    { label: '6 months', value: 6, unit: 'months' },
    { label: '1 year', value: 1, unit: 'years' },
    { label: '2 years', value: 2, unit: 'years' },
];

const defaults = () => ({
    product_id: '',
    serial_number: '',
    duration_value: 1,
    duration_unit: 'years',
    customer_name: '',
    customer_phone: '',
    starts_on: props.today,
    sms_language: 'en',
    notes: '',
});

const form = useForm(defaults());
const filterForm = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const customers = ref([]);
const sending = ref('');

const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));
const selectedProduct = computed(() => productById(form.product_id));

// Same rules as the server: 07xx / 7xx / 2557xx → +2557xx
const normalizePhone = (input) => {
    let digits = String(input || '').replace(/\D+/g, '');
    if (digits.length === 10 && digits.startsWith('0')) digits = `255${digits.slice(1)}`;
    else if (digits.length === 9) digits = `255${digits}`;
    return /^255\d{9}$/.test(digits) ? `+${digits}` : null;
};
const normalizedPhone = computed(() => normalizePhone(form.customer_phone));
const matchedCustomer = computed(() => normalizedPhone.value
    ? customers.value.find((customer) => customer.phone === normalizedPhone.value) || null
    : null);

// Use the product's own warranty period when it has one.
watch(() => form.product_id, (id) => {
    const product = productById(id);
    if (product?.warranty_value && product?.warranty_unit) {
        form.duration_value = product.warranty_value;
        form.duration_unit = product.warranty_unit;
    }
});

watch(matchedCustomer, (customer) => {
    if (customer && !form.customer_name.trim()) form.customer_name = customer.name;
});

const parseDate = (value) => {
    const [year, month, day] = String(value || '').split('-').map(Number);
    return year && month && day ? new Date(Date.UTC(year, month - 1, day)) : null;
};
const addMonthsNoOverflow = (date, months) => {
    const result = new Date(date);
    const day = result.getUTCDate();
    result.setUTCDate(1);
    result.setUTCMonth(result.getUTCMonth() + months);
    const lastDay = new Date(Date.UTC(result.getUTCFullYear(), result.getUTCMonth() + 1, 0)).getUTCDate();
    result.setUTCDate(Math.min(day, lastDay));
    return result;
};
const endsOn = computed(() => {
    const start = parseDate(form.starts_on);
    const value = Number(form.duration_value || 0);
    if (!start || value < 1) return null;
    let end;
    if (form.duration_unit === 'days') end = new Date(start.getTime() + value * 86400000);
    else end = addMonthsNoOverflow(start, form.duration_unit === 'years' ? value * 12 : value);
    return new Date(end.getTime() - 86400000);
});
const formatDate = (value) => {
    const date = value instanceof Date ? value : parseDate(value);
    return date ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }) : '—';
};
const endsInPast = computed(() => endsOn.value && endsOn.value < parseDate(props.today));
const durationLabel = computed(() => {
    const value = Number(form.duration_value || 0);
    const unit = { days: 'day', months: 'month', years: 'year' }[form.duration_unit];
    return `${value} ${value === 1 ? unit : `${unit}s`}`;
});

const isPreset = (preset) => Number(form.duration_value) === preset.value && form.duration_unit === preset.unit;
const usePreset = (preset) => {
    form.duration_value = preset.value;
    form.duration_unit = preset.unit;
};

const refreshSelects = async () => {
    await nextTick();
    const $ = window.jQuery;
    if ($?.fn?.select2) document.querySelectorAll('.warranty-form select.select2-hidden-accessible').forEach((select) => $(select).trigger('change.select2'));
};

const loadCustomers = async () => {
    try {
        const response = await fetch('/admin/customers/options', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (response.ok) customers.value = (await response.json()).customers || [];
    } catch {
        customers.value = [];
    }
};

onMounted(loadCustomers);

const submit = () => form.post('/admin/warranties', {
    preserveScroll: true,
    onSuccess: () => {
        form.defaults(defaults());
        form.reset();
        refreshSelects();
        loadCustomers();
    },
});

const resend = (warranty, type) => {
    if (warranty.messages[type]?.status === 'sent' && !window.confirm('This SMS was already sent. Send it again?')) return;
    sending.value = `${warranty.id}-${type}`;
    router.post(`/admin/warranties/${warranty.id}/messages/${type}`, {}, {
        preserveScroll: true,
        onFinish: () => { sending.value = ''; },
    });
};

const applyFilters = () => router.get('/admin/warranties', {
    search: filterForm.search || undefined,
    status: filterForm.status || undefined,
}, { preserveState: true, replace: true });

const messageTypes = [
    ['thank_you', 'Thanks'],
    ['details', 'Details'],
    ['expired', 'Ended'],
];
const statusClass = (status) => ({
    active: 'bg-emerald-50 text-emerald-700',
    scheduled: 'bg-sky-50 text-sky-700',
    expired: 'bg-slate-100 text-slate-500',
}[status] || 'bg-slate-100 text-slate-600');
const chipClass = (message) => {
    if (!message) return 'border-slate-200 bg-white text-slate-400';
    if (message.status === 'sent') return 'border-emerald-100 bg-emerald-50 text-emerald-700';
    if (message.status === 'failed') return 'border-red-100 bg-red-50 text-red-600';
    return 'border-amber-100 bg-amber-50 text-amber-700';
};
const canSend = (warranty, type) => type !== 'expired' || warranty.status === 'expired';
const fieldError = computed(() => form.errors.product_id || form.errors.duration_value || form.errors.duration_unit || form.errors.starts_on || form.errors.serial_number);
const customerError = computed(() => form.errors.customer_name || form.errors.customer_phone || form.errors.sms_language || form.errors.notes);
</script>

<template>
    <AdminLayout>
        <Head title="Warranties" />

        <div class="mx-auto max-w-[1500px] space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[.16em] text-prinx-600">Sales & customers</p>
                    <h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950">Warranties</h1>
                    <p class="mt-1 text-[12px] text-slate-500">Register a product warranty for a customer. They receive a thank-you SMS and the warranty details, and another SMS when the warranty ends.</p>
                </div>
                <Link href="/admin/customers" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3.5 text-[11px] font-semibold text-slate-700 hover:border-prinx-300 hover:text-prinx-600">Manage customers</Link>
            </div>

            <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Warranties</p><strong class="mt-1 block text-xl font-semibold text-slate-950">{{ summary.total || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Active</p><strong class="mt-1 block text-xl font-semibold text-emerald-600">{{ summary.active || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Ending in 30 days</p><strong class="mt-1 block text-xl font-semibold text-amber-600">{{ summary.expiring || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Ended</p><strong class="mt-1 block text-xl font-semibold text-slate-500">{{ summary.expired || 0 }}</strong></div>
            </div>

            <section class="rounded-[9px] border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Add warranty</h2><p class="mt-1 text-[11px] text-slate-500">If the phone number is not already in Customers, the customer is saved there automatically.</p></div>
                <form class="warranty-form grid gap-4 p-4 xl:grid-cols-[1.1fr_1fr_.75fr]" @submit.prevent="submit">
                    <div class="space-y-3">
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Product</span><select v-model="form.product_id" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-[11px] outline-none focus:border-prinx-400" required><option value="">Choose product</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}{{ product.sku ? ` · ${product.sku}` : '' }}</option></select></label>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Serial / IMEI number <span class="font-normal text-slate-400">(optional)</span></span><input v-model="form.serial_number" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="Helps identify the exact unit for claims"></label>
                        <div>
                            <span class="mb-1 block text-[10px] font-semibold text-slate-600">Warranty duration</span>
                            <div class="mb-2 grid grid-cols-4 gap-1 rounded-[7px] bg-slate-100 p-1">
                                <button v-for="preset in presets" :key="preset.label" type="button" :class="['h-8 rounded-[5px] text-[10px] font-semibold transition', isPreset(preset) ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500']" @click="usePreset(preset)">{{ preset.label }}</button>
                            </div>
                            <div class="grid grid-cols-[1fr_1.2fr] gap-2">
                                <input v-model.number="form.duration_value" type="number" min="1" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" aria-label="Duration" required>
                                <select v-model="form.duration_unit" data-native-select class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs outline-none focus:border-prinx-400" aria-label="Duration unit"><option value="days">Days</option><option value="months">Months</option><option value="years">Years</option></select>
                            </div>
                        </div>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Warranty start date</span><input v-model="form.starts_on" type="date" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" required></label>
                        <p v-if="fieldError" class="text-[11px] text-red-600">{{ fieldError }}</p>
                    </div>

                    <div class="space-y-3">
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Customer phone number</span><input v-model="form.customer_phone" list="warranty-customer-phones" inputmode="tel" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="0712 345 678" required></label>
                        <datalist id="warranty-customer-phones"><option v-for="customer in customers" :key="customer.id" :value="customer.phone">{{ customer.name }}</option></datalist>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Customer name</span><input v-model="form.customer_name" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" required></label>
                        <p v-if="matchedCustomer" class="rounded-[6px] bg-emerald-50 px-2.5 py-2 text-[10px] leading-4 text-emerald-700">Existing customer: <strong>{{ matchedCustomer.name }}</strong>. The warranty will be linked to this record.</p>
                        <p v-else-if="normalizedPhone" class="rounded-[6px] bg-sky-50 px-2.5 py-2 text-[10px] leading-4 text-sky-700">New customer. They will be added to Customers when you save.</p>
                        <p v-else-if="form.customer_phone" class="rounded-[6px] bg-amber-50 px-2.5 py-2 text-[10px] leading-4 text-amber-700">Enter a valid Tanzania number, e.g. 0712 345 678.</p>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">SMS language</span><select v-model="form.sms_language" data-native-select class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-xs outline-none focus:border-prinx-400"><option value="en">English</option><option value="sw">Swahili</option></select></label>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Notes <span class="font-normal text-slate-400">(optional)</span></span><textarea v-model="form.notes" rows="2" class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-xs outline-none focus:border-prinx-400" placeholder="Receipt number, conditions, etc."></textarea></label>
                        <p v-if="customerError" class="text-[11px] text-red-600">{{ customerError }}</p>
                    </div>

                    <div class="space-y-3 rounded-[8px] bg-slate-50 p-3">
                        <div class="text-[11px]">
                            <div class="flex justify-between gap-3 py-1"><span class="text-slate-500">Product</span><strong class="text-right">{{ selectedProduct?.name || '—' }}</strong></div>
                            <div class="flex justify-between gap-3 py-1"><span class="text-slate-500">Duration</span><strong>{{ durationLabel }}</strong></div>
                            <div class="flex justify-between gap-3 py-1"><span class="text-slate-500">Starts</span><strong>{{ formatDate(form.starts_on) }}</strong></div>
                            <div class="flex justify-between gap-3 border-t border-slate-200 py-1.5 text-[13px]"><span class="font-semibold text-slate-800">Ends</span><strong :class="endsInPast ? 'text-red-600' : 'text-slate-950'">{{ formatDate(endsOn) }}</strong></div>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-2 text-[10px] leading-4 text-slate-600">
                            <p class="font-semibold text-slate-800">SMS the customer will receive</p>
                            <ol class="mt-1 list-decimal space-y-0.5 pl-4"><li>Thank you for shopping with us</li><li>Warranty started and the date it ends</li><li>Warranty ended (sent automatically the day after it ends)</li></ol>
                        </div>
                        <p v-if="endsInPast" class="rounded-[6px] bg-red-50 px-2.5 py-2 text-[10px] text-red-600">This warranty would already have ended. Check the start date or duration.</p>
                        <button type="submit" class="flex h-10 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="form.processing || !form.product_id || !normalizedPhone || !form.customer_name.trim() || endsInPast">{{ form.processing ? 'Saving & sending SMS…' : 'Save warranty' }}</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold text-slate-950">Registered warranties</h2><p class="mt-1 text-[11px] text-slate-500">Green messages were delivered to the SMS provider. Click a message to send it again.</p></div><form class="flex flex-wrap gap-2" @submit.prevent="applyFilters"><input v-model="filterForm.search" class="h-8 w-48 rounded-[6px] border border-slate-200 px-2 text-[11px]" placeholder="Customer, phone, product, S/N"><select v-model="filterForm.status" data-native-select class="h-8 rounded-[6px] border border-slate-200 bg-white px-2 text-[11px]"><option value="">All</option><option value="active">Active</option><option value="expiring">Ending in 30 days</option><option value="expired">Ended</option></select><button class="h-8 rounded-[6px] bg-slate-950 px-3 text-[11px] font-semibold text-white">Filter</button></form></div>
                <div v-if="warranties.length" class="overflow-x-auto">
                    <table class="min-w-[980px] w-full text-left text-[11px]">
                        <thead class="bg-slate-50 text-[9px] uppercase tracking-[.08em] text-slate-500"><tr><th class="px-4 py-2.5">Reference</th><th class="px-4 py-2.5">Customer</th><th class="px-4 py-2.5">Product</th><th class="px-4 py-2.5">Period</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">SMS</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="warranty in warranties" :key="warranty.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ warranty.reference }}</td>
                                <td class="px-4 py-3"><strong class="block font-medium text-slate-800">{{ warranty.customer_name }}</strong><span class="text-[10px] text-slate-400">{{ warranty.customer_phone }}</span></td>
                                <td class="px-4 py-3"><strong class="block font-medium text-slate-800">{{ warranty.product_name }}</strong><span v-if="warranty.serial_number" class="text-[10px] text-slate-400">S/N {{ warranty.serial_number }}</span></td>
                                <td class="px-4 py-3"><span class="block text-slate-700">{{ formatDate(warranty.starts_on) }} → {{ formatDate(warranty.ends_on) }}</span><span class="text-[10px] text-slate-400">{{ warranty.duration }}<template v-if="warranty.status !== 'expired'"> · {{ warranty.days_left }} days left</template></span></td>
                                <td class="px-4 py-3"><span :class="['rounded-full px-2 py-1 text-[9px] font-semibold capitalize', statusClass(warranty.status)]">{{ warranty.status === 'expired' ? 'ended' : warranty.status }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <button v-for="[type, label] in messageTypes" :key="type" type="button" :class="['h-7 rounded-[5px] border px-2 text-[9px] font-semibold transition disabled:cursor-default', chipClass(warranty.messages[type])]" :disabled="!canSend(warranty, type) || sending === `${warranty.id}-${type}`" :title="warranty.messages[type]?.error || (warranty.messages[type]?.status === 'sent' ? 'Sent — click to send again' : (canSend(warranty, type) ? 'Send now' : 'Sent automatically after the warranty ends'))" @click="resend(warranty, type)">{{ sending === `${warranty.id}-${type}` ? '…' : label }}</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-4 py-10 text-center text-xs text-slate-500">No warranties found.</div>
            </section>
        </div>
    </AdminLayout>
</template>
