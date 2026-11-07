<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    customers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const editingId = ref(null);
const form = useForm({ name: '', phone: '', email: '', tin: '', notes: '' });
const isEditing = computed(() => editingId.value !== null);

const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (customer) => {
    editingId.value = customer.id;
    form.name = customer.name || '';
    form.phone = customer.phone || '';
    form.email = customer.email || '';
    form.tin = customer.tin || '';
    form.notes = customer.notes || '';
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = () => {
    if (isEditing.value) {
        form.patch(`/admin/customers/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/admin/customers', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const applySearch = () => router.get('/admin/customers', {
    search: search.value || undefined,
}, { preserveState: true, replace: true });
</script>

<template>
    <AdminLayout>
        <Head title="Customers" />

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[.16em] text-prinx-600">Sales & customers</p>
                    <h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950">Customers</h1>
                    <p class="mt-1 text-[12px] text-slate-500">One customer list for POS, online accounts and installment purchases.</p>
                </div>
                <Link href="/admin/installments" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-slate-950 px-3.5 text-[11px] font-semibold text-white">Open installments</Link>
            </div>

            <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Customers</p><strong class="mt-1 block text-xl font-semibold text-slate-950">{{ summary.customers || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Online accounts</p><strong class="mt-1 block text-xl font-semibold text-emerald-700">{{ summary.registered || 0 }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">POS sales</p><strong class="mt-1 block text-[14px] font-semibold text-slate-950">{{ money(summary.pos_sales) }}</strong></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-[9px] font-semibold uppercase tracking-[.1em] text-slate-400">Installments due</p><strong class="mt-1 block text-[14px] font-semibold text-amber-700">{{ money(summary.outstanding_installments) }}</strong></div>
            </div>

            <section class="grid gap-4 xl:grid-cols-[360px_1fr]">
                <div class="h-fit rounded-[9px] border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div><h2 class="text-sm font-semibold text-slate-950">{{ isEditing ? 'Edit customer' : 'Add customer' }}</h2><p class="mt-0.5 text-[10px] text-slate-500">Phone is required when this customer will use installments.</p></div>
                        <button v-if="isEditing" type="button" class="text-[10px] font-semibold text-slate-500 hover:text-slate-900" @click="resetForm">Cancel</button>
                    </div>
                    <form class="space-y-3 p-4" @submit.prevent="submit">
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Customer name</span><input v-model="form.name" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="Full name" required><span v-if="form.errors.name" class="mt-1 block text-[10px] text-red-600">{{ form.errors.name }}</span></label>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Phone number</span><input v-model="form.phone" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="0712 345 678"><span v-if="form.errors.phone" class="mt-1 block text-[10px] text-red-600">{{ form.errors.phone }}</span></label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Email</span><input v-model="form.email" type="email" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="Optional"><span v-if="form.errors.email" class="mt-1 block text-[10px] text-red-600">{{ form.errors.email }}</span></label>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">TIN</span><input v-model="form.tin" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" placeholder="Optional"><span v-if="form.errors.tin" class="mt-1 block text-[10px] text-red-600">{{ form.errors.tin }}</span></label>
                        </div>
                        <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Notes</span><textarea v-model="form.notes" rows="3" class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-xs outline-none focus:border-prinx-400" placeholder="Optional customer notes"></textarea><span v-if="form.errors.notes" class="mt-1 block text-[10px] text-red-600">{{ form.errors.notes }}</span></label>
                        <button class="flex h-9 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-[11px] font-semibold text-white hover:bg-prinx-600 disabled:bg-slate-300" :disabled="form.processing">{{ form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Add customer' }}</button>
                    </form>
                </div>

                <div class="min-w-0 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><h2 class="text-sm font-semibold text-slate-950">Customer directory</h2><p class="mt-0.5 text-[10px] text-slate-500">Search by name, phone, email or TIN. Showing up to 250 records.</p></div>
                        <form class="flex gap-2" @submit.prevent="applySearch"><input v-model="search" class="h-8 w-full rounded-[6px] border border-slate-200 px-2.5 text-[11px] sm:w-56" placeholder="Search customers"><button class="h-8 rounded-[6px] bg-slate-950 px-3 text-[10px] font-semibold text-white">Search</button></form>
                    </div>

                    <div v-if="customers.length" class="divide-y divide-slate-100 md:hidden">
                        <article v-for="customer in customers" :key="customer.id" class="p-4">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex flex-wrap items-center gap-1.5"><h3 class="truncate text-xs font-semibold text-slate-950">{{ customer.name }}</h3><span v-if="customer.registered" class="rounded-[4px] bg-emerald-50 px-1.5 py-0.5 text-[8px] font-semibold uppercase text-emerald-700">Account</span></div><p class="mt-1 text-[10px] text-slate-500">{{ customer.phone || 'No phone' }}<span v-if="customer.email"> · {{ customer.email }}</span></p></div><button class="shrink-0 text-[10px] font-semibold text-prinx-600" @click="edit(customer)">Edit</button></div>
                            <div class="mt-3 grid grid-cols-3 gap-2 rounded-[7px] bg-slate-50 p-2.5 text-center"><div><span class="block text-[8px] uppercase text-slate-400">Sales</span><strong class="mt-0.5 block text-[10px] text-slate-800">{{ customer.sales_count }}</strong></div><div><span class="block text-[8px] uppercase text-slate-400">Orders</span><strong class="mt-0.5 block text-[10px] text-slate-800">{{ customer.order_count }}</strong></div><div><span class="block text-[8px] uppercase text-slate-400">Installments</span><strong class="mt-0.5 block text-[10px] text-slate-800">{{ customer.installment_count }}</strong></div></div>
                            <div class="mt-2 flex items-center justify-between gap-2"><span class="text-[10px] text-slate-400">Last activity {{ date(customer.last_activity) }}</span><Link v-if="customer.phone" :href="`/admin/installments?search=${encodeURIComponent(customer.phone)}`" class="text-[10px] font-semibold text-prinx-600">Installments →</Link></div>
                        </article>
                    </div>

                    <div v-if="customers.length" class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[940px] text-left text-[11px]">
                            <thead class="bg-slate-50 text-[9px] uppercase tracking-[.07em] text-slate-500"><tr><th class="px-4 py-2.5">Customer</th><th class="px-3 py-2.5">Contact</th><th class="px-3 py-2.5">POS sales</th><th class="px-3 py-2.5">Online orders</th><th class="px-3 py-2.5">Installments</th><th class="px-3 py-2.5">Last activity</th><th class="px-4 py-2.5"></th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="customer in customers" :key="customer.id" class="hover:bg-slate-50">
                                    <td class="px-4 py-3"><div class="flex items-center gap-2"><div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[7px] bg-slate-100 text-[10px] font-semibold text-slate-700">{{ customer.name?.charAt(0)?.toUpperCase() || 'C' }}</div><div class="min-w-0"><div class="flex items-center gap-1.5"><strong class="max-w-44 truncate font-semibold text-slate-900">{{ customer.name }}</strong><span v-if="customer.registered" class="rounded-[4px] bg-emerald-50 px-1.5 py-0.5 text-[8px] font-semibold text-emerald-700">Account</span></div><span v-if="customer.tin" class="mt-0.5 block text-[9px] text-slate-400">TIN {{ customer.tin }}</span></div></div></td>
                                    <td class="px-3 py-3"><span class="block text-slate-700">{{ customer.phone || '—' }}</span><span class="mt-0.5 block max-w-48 truncate text-[9px] text-slate-400">{{ customer.email || 'No email' }}</span></td>
                                    <td class="px-3 py-3"><strong class="block font-semibold text-slate-800">{{ customer.sales_count }}</strong><span class="text-[9px] text-slate-400">{{ money(customer.sales_total) }}</span></td>
                                    <td class="px-3 py-3"><strong class="block font-semibold text-slate-800">{{ customer.order_count }}</strong><span class="text-[9px] text-slate-400">{{ money(customer.order_total) }}</span></td>
                                    <td class="px-3 py-3"><strong class="block font-semibold text-slate-800">{{ customer.installment_count }}</strong><span :class="['text-[9px]', customer.installment_outstanding > 0 ? 'text-amber-600' : 'text-slate-400']">Due {{ money(customer.installment_outstanding) }}</span></td>
                                    <td class="px-3 py-3 text-[10px] text-slate-500">{{ date(customer.last_activity) }}</td>
                                    <td class="px-4 py-3"><div class="flex justify-end gap-3"><Link v-if="customer.phone" :href="`/admin/installments?search=${encodeURIComponent(customer.phone)}`" class="text-[10px] font-semibold text-slate-500 hover:text-prinx-600">Plans</Link><button class="text-[10px] font-semibold text-prinx-600" @click="edit(customer)">Edit</button></div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="!customers.length" class="px-5 py-14 text-center"><h3 class="text-sm font-semibold text-slate-800">No customers found</h3><p class="mt-1 text-[11px] text-slate-500">Add a customer or change your search.</p></div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
