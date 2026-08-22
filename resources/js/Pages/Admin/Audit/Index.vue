<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ logs: Array, users: Array, actions: Array, filters: Object });
const page = usePage();
const filters = reactive({ ...props.filters });
const selected = ref(null);
const apply = () => router.get('/admin/audit', filters, { preserveState: true, preserveScroll: true, replace: true });
const clear = () => {
    filters.user = '';
    filters.action = '';
    filters.search = '';
    apply();
};
const pretty = (value) => value ? JSON.stringify(value, null, 2) : 'No values recorded';
</script>

<template>
    <AdminLayout>
        <Head title="Audit Trail" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Security & accountability</p>
                <h1 class="section-title">Audit trail</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Read-only history of sensitive stock, money, register and access-control actions. Audit records cannot be edited from the admin panel.</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">Showing up to 500 records</div>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[145px_145px_190px_220px_1fr_auto_auto]" @submit.prevent="apply">
            <input v-model="filters.from" type="date" class="input">
            <input v-model="filters.to" type="date" class="input">
            <select v-model="filters.user" class="input"><option value="">All staff</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select>
            <select v-model="filters.action" class="input"><option value="">All actions</option><option v-for="action in actions" :key="action" :value="action">{{ action }}</option></select>
            <div class="relative"><Icon name="search-normal" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="filters.search" class="input pl-9" placeholder="Reference, staff, IP, subject"></div>
            <button class="btn-primary">Apply</button>
            <button type="button" class="btn-light" @click="clear">Clear</button>
        </form>

        <section class="card mt-5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[1100px]">
                    <thead><tr><th>Time</th><th>Action</th><th>Staff</th><th>Subject</th><th>Reference</th><th>IP</th><th class="text-right">Details</th></tr></thead>
                    <tbody>
                        <tr v-for="log in logs" :key="log.id">
                            <td>{{ new Date(log.created_at).toLocaleString() }}</td>
                            <td><span class="rounded-[7px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold">{{ log.action }}</span></td>
                            <td><p class="font-semibold">{{ log.user_name || 'System / deleted user' }}</p><p class="text-[11px] text-slate-500">{{ log.user_email || '—' }}</p></td>
                            <td>{{ log.subject_type || '—' }}<span v-if="log.subject_id"> #{{ log.subject_id }}</span></td>
                            <td class="font-medium">{{ log.reference || '—' }}</td>
                            <td class="text-xs text-slate-500">{{ log.ip_address || '—' }}</td>
                            <td class="text-right"><button class="btn-light px-3 py-1.5 text-xs" @click="selected=log"><Icon name="document-text" :size="14" /> Inspect</button></td>
                        </tr>
                        <tr v-if="!logs.length"><td colspan="7" class="py-12 text-center text-slate-400"><Icon name="document-text" :size="28" class="mx-auto mb-2" />No audit records match the selected filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="selected" class="fixed inset-0 z-[90] bg-slate-950/50 p-4" @click.self="selected=null">
            <div class="ml-auto h-full w-full max-w-2xl overflow-y-auto bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4"><div><p class="section-kicker">Audit record #{{ selected.id }}</p><h2 class="mt-1 text-xl font-semibold">{{ selected.action }}</h2><p class="mt-1 text-xs text-slate-500">{{ new Date(selected.created_at).toLocaleString() }} · {{ selected.user_name || 'System' }}</p></div><button class="flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200" @click="selected=null"><Icon name="close-circle" :size="18" /></button></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2"><div class="rounded-[8px] border border-slate-200 p-3"><p class="text-xs text-slate-500">Subject</p><b class="mt-1 block text-sm">{{ selected.subject_type || '—' }} {{ selected.subject_id ? `#${selected.subject_id}` : '' }}</b></div><div class="rounded-[8px] border border-slate-200 p-3"><p class="text-xs text-slate-500">Reference</p><b class="mt-1 block text-sm">{{ selected.reference || '—' }}</b></div><div class="rounded-[8px] border border-slate-200 p-3"><p class="text-xs text-slate-500">IP address</p><b class="mt-1 block text-sm">{{ selected.ip_address || '—' }}</b></div><div class="rounded-[8px] border border-slate-200 p-3"><p class="text-xs text-slate-500">User agent</p><p class="mt-1 break-all text-xs text-slate-700">{{ selected.user_agent || '—' }}</p></div></div>
                <div class="mt-5"><h3 class="text-sm font-semibold">Before</h3><pre class="mt-2 overflow-x-auto rounded-[8px] bg-slate-950 p-4 text-xs leading-6 text-slate-200">{{ pretty(selected.before_values) }}</pre></div>
                <div class="mt-5"><h3 class="text-sm font-semibold">After</h3><pre class="mt-2 overflow-x-auto rounded-[8px] bg-slate-950 p-4 text-xs leading-6 text-slate-200">{{ pretty(selected.after_values) }}</pre></div>
                <div class="mt-5"><h3 class="text-sm font-semibold">Metadata</h3><pre class="mt-2 overflow-x-auto rounded-[8px] bg-slate-950 p-4 text-xs leading-6 text-slate-200">{{ pretty(selected.metadata) }}</pre></div>
            </div>
        </div>
    </AdminLayout>
</template>
