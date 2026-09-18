<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    orders: Object,
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    source: props.filters.source || '',
});

const apply = () => router.get('/admin/orders', {
    search: form.search || undefined,
    status: form.status || undefined,
    source: form.source || undefined,
}, { preserveState: true, replace: true });

const clear = () => {
    form.search = '';
    form.status = '';
    form.source = '';
    apply();
};

const sourceLabel = (source) => ({
    google: 'Google',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    whatsapp: 'WhatsApp',
    direct: 'Direct',
    referral: 'Referral',
}[source] || (source ? source : 'Unknown'));
</script>

<template>
    <AdminLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Sales</p>
                <h1 class="section-title">Orders</h1>
                <p class="mt-2 text-sm text-slate-500">Filter online orders by customer, status and acquisition source.</p>
            </div>
        </div>

        <form class="card mt-6 p-4" @submit.prevent="apply">
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <label class="label">Search</label>
                    <input v-model.trim="form.search" class="input" placeholder="Order, customer or phone">
                </div>
                <div>
                    <label class="label">Status</label>
                    <select v-model="form.status" class="input">
                        <option value="">All statuses</option>
                        <option v-for="status in ['pending','confirmed','processing','ready','shipped','completed','cancelled']" :key="status" :value="status">{{ status }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Source</label>
                    <select v-model="form.source" class="input">
                        <option value="">All sources</option>
                        <option value="google">Google</option>
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="direct">Direct</option>
                        <option value="referral">Referral</option>
                        <option value="unknown">Unknown / historical</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">Apply filters</button>
                    <button type="button" class="btn-light" @click="clear">Clear</button>
                </div>
            </div>
        </form>

        <div class="card mt-4 overflow-x-auto">
            <table class="admin-table min-w-[820px]">
                <thead><tr><th>Order</th><th>Customer</th><th>Source</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id">
                        <td><b>{{ o.order_number }}</b><p v-if="o.utm_campaign" class="mt-1 text-[10px] text-slate-400">{{ o.utm_campaign }}</p></td>
                        <td>{{ o.customer_name }}<br><span class="text-xs text-slate-500">{{ o.phone }}</span></td>
                        <td><span class="inline-flex rounded-[6px] bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-700">{{ sourceLabel(o.acquisition_source) }}</span></td>
                        <td>TZS {{ Number(o.grand_total).toLocaleString() }}</td>
                        <td class="capitalize">{{ o.status }}</td>
                        <td><Link :href="`/admin/orders/${o.id}`" class="font-bold text-prinx-600">Open</Link></td>
                    </tr>
                    <tr v-if="!orders.data?.length"><td colspan="6" class="py-12 text-center text-slate-400">No orders match these filters.</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="orders.links" />
    </AdminLayout>
</template>
