<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ suppliers: Array, filters: Object, summary: Object });
const page = usePage();
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const editing = ref(null);
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));

const form = useForm({ name: '', contact_person: '', phone: '', email: '', address: '', tax_number: '', opening_balance: 0, notes: '', is_active: true });

const filtered = computed(() => props.suppliers.filter((supplier) => {
    const q = search.value.trim().toLowerCase();
    if (q && !`${supplier.name} ${supplier.contact_person || ''} ${supplier.phone || ''} ${supplier.email || ''}`.toLowerCase().includes(q)) return false;
    if (status.value === 'active' && !supplier.is_active) return false;
    if (status.value === 'inactive' && supplier.is_active) return false;
    return true;
}));

function newSupplier() {
    editing.value = 'new';
    form.reset();
    form.opening_balance = 0;
    form.is_active = true;
}

function editSupplier(supplier) {
    editing.value = supplier;
    form.name = supplier.name || '';
    form.contact_person = supplier.contact_person || '';
    form.phone = supplier.phone || '';
    form.email = supplier.email || '';
    form.address = supplier.address || '';
    form.tax_number = supplier.tax_number || '';
    form.opening_balance = Number(supplier.opening_balance || 0);
    form.notes = supplier.notes || '';
    form.is_active = !!supplier.is_active;
    form.clearErrors();
}

function closeModal() { editing.value = null; form.clearErrors(); }

function save() {
    if (editing.value === 'new') {
        form.post('/admin/suppliers', { preserveScroll: true, onSuccess: closeModal });
        return;
    }
    form.post(`/admin/suppliers/${editing.value.id}`, { preserveScroll: true, onSuccess: closeModal });
}

function deactivate(supplier) {
    if (!confirm(`Mark ${supplier.name} as inactive? Existing purchase history will remain.`)) return;
    router.delete(`/admin/suppliers/${supplier.id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <Head title="Suppliers" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Stock management</p>
                <h1 class="section-title">Suppliers</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Manage supplier contacts, purchase history and current supplier balances.</p>
            </div>
            <button class="btn-primary" @click="newSupplier"><Icon name="add-circle" :size="17" /> Add supplier</button>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Suppliers</p><b class="mt-2 block text-2xl">{{ summary.total }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-emerald-600">Active</p><b class="mt-2 block text-2xl">{{ summary.active }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-blue-600">Linked purchases</p><b class="mt-2 block text-xl">{{ money(summary.total_purchases) }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-amber-600">Outstanding</p><b class="mt-2 block text-xl">{{ money(summary.outstanding) }}</b></div>
        </div>

        <div class="card mt-6 p-4">
            <div class="grid gap-3 md:grid-cols-[1fr_220px]">
                <div class="relative"><Icon name="search-normal" :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="input pl-9" placeholder="Search supplier, contact, phone or email"></div>
                <select v-model="status" class="input"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
        </div>

        <div class="card mt-5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[1050px]">
                    <thead><tr><th>Supplier</th><th>Contact</th><th>Purchases</th><th>Paid</th><th>Outstanding</th><th>Last purchase</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="supplier in filtered" :key="supplier.id">
                            <td><Link :href="`/admin/suppliers/${supplier.id}`" class="font-semibold text-slate-900 hover:text-prinx-600">{{ supplier.name }}</Link><p class="mt-0.5 text-xs text-slate-500">{{ supplier.tax_number || 'No tax/TIN reference' }}</p></td>
                            <td><p>{{ supplier.contact_person || '—' }}</p><p class="mt-0.5 text-xs text-slate-500">{{ supplier.phone || supplier.email || 'No contact details' }}</p></td>
                            <td><b>{{ money(supplier.total_purchases) }}</b><p class="mt-0.5 text-xs text-slate-500">{{ supplier.purchase_count }} purchases</p></td>
                            <td>{{ money(supplier.paid_amount) }}</td>
                            <td><b :class="Number(supplier.outstanding_amount) > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ money(supplier.outstanding_amount) }}</b></td>
                            <td>{{ supplier.last_purchase_at || '—' }}</td>
                            <td><span :class="['inline-flex rounded-[8px] border px-2 py-1 text-[11px] font-semibold', supplier.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500']">{{ supplier.is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-right"><div class="inline-flex gap-2"><Link :href="`/admin/suppliers/${supplier.id}`" class="btn-light px-3 py-2"><Icon name="eye" :size="15" /> View</Link><button class="btn-light px-3 py-2" @click="editSupplier(supplier)"><Icon name="edit-2" :size="15" /> Edit</button><button v-if="supplier.is_active" class="btn-light px-3 py-2 text-red-600" @click="deactivate(supplier)"><Icon name="close-circle" :size="15" /> Inactive</button></div></td>
                        </tr>
                        <tr v-if="!filtered.length"><td colspan="8" class="py-12 text-center text-sm text-slate-400">No suppliers match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4" @click.self="closeModal">
            <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[10px] bg-white p-5 shadow-2xl" @submit.prevent="save">
                <div class="flex items-start justify-between"><div><p class="section-kicker">Supplier record</p><h2 class="mt-1 text-xl font-semibold">{{ editing === 'new' ? 'Add supplier' : 'Edit supplier' }}</h2></div><button type="button" class="flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200" @click="closeModal"><Icon name="close-circle" :size="18" /></button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label">Supplier / business name</label><input v-model="form.name" class="input" required><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                    <div><label class="label">Contact person</label><input v-model="form.contact_person" class="input"></div>
                    <div><label class="label">Phone</label><input v-model="form.phone" class="input"></div>
                    <div><label class="label">Email</label><input v-model="form.email" type="email" class="input"></div>
                    <div><label class="label">Tax / TIN / reference</label><input v-model="form.tax_number" class="input"><p v-if="form.errors.tax_number" class="mt-1 text-xs text-red-600">{{ form.errors.tax_number }}</p></div>
                    <div class="sm:col-span-2"><label class="label">Address</label><textarea v-model="form.address" rows="2" class="input"></textarea></div>
                    <div><label class="label">Opening balance owed</label><input v-model.number="form.opening_balance" type="number" min="0" step="0.01" class="input"></div>
                    <div><label class="label">Status</label><select v-model="form.is_active" class="input"><option :value="true">Active</option><option :value="false">Inactive</option></select></div>
                    <div class="sm:col-span-2"><label class="label">Notes</label><textarea v-model="form.notes" rows="3" class="input"></textarea></div>
                </div>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="btn-light" @click="closeModal">Cancel</button><button class="btn-primary" :disabled="form.processing"><Icon name="tick-circle" :size="16" /> Save supplier</button></div>
            </form>
        </div>
    </AdminLayout>
</template>
