<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    configured: Boolean,
    credentialsInstalled: Boolean,
    config: Object,
    counts: Object,
    logs: Object,
});

const credentialsForm = useForm({ credentials: null });
const test = () => router.post('/admin/merchant/test');
const sync = () => router.post('/admin/merchant/sync-all');
const badge = (status) => status === 'success' ? 'bg-emerald-100 text-emerald-700' : status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700';

const selectCredentials = (event) => {
    credentialsForm.credentials = event.target.files?.[0] || null;
    credentialsForm.clearErrors('credentials');
};

const uploadCredentials = () => {
    credentialsForm.post('/admin/merchant/credentials', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => credentialsForm.reset('credentials'),
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Google Merchant" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Product distribution</p>
                <h1 class="section-title">Google Merchant Center</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Synchronize active products with Google Merchant.</p>
            </div>
            <div class="flex gap-2">
                <button class="btn-light" @click="test"><Icon name="link-2" :size="17" /> Test connection</button>
                <button class="btn-primary" :disabled="!configured" @click="sync"><Icon name="refresh-2" :size="17" /> Sync all eligible</button>
            </div>
        </div>

        <div :class="['mt-6 rounded-[10px] border p-5', configured ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50']">
            <div class="flex items-start gap-3">
                <div :class="['mt-0.5 flex h-8 w-8 items-center justify-center rounded-[8px] text-white', configured ? 'bg-emerald-600' : 'bg-amber-600']">
                    <Icon :name="configured ? 'tick-circle' : 'warning-2'" :size="18" />
                </div>
                <div>
                    <h2 class="font-semibold">{{ configured ? 'Integration configured' : 'Configuration required' }}</h2>
                    <p class="mt-1 text-sm leading-6" :class="configured ? 'text-emerald-700' : 'text-amber-800'">
                        {{ configured ? 'The service-account file and Merchant account settings were detected.' : 'Merchant settings are incomplete. Upload your service-account JSON and complete the Merchant settings.' }}
                    </p>
                </div>
            </div>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[9px] bg-prinx-50 text-prinx-600">
                        <Icon name="document-upload" :size="21" />
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-900">Google service-account credentials</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Upload the JSON file downloaded from Google Cloud. The contents stay in private server storage and are never displayed in the admin panel.</p>
                    </div>
                </div>
                <span :class="['status shrink-0', credentialsInstalled ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                    <Icon :name="credentialsInstalled ? 'shield-tick' : 'shield-cross'" :size="14" />
                    {{ credentialsInstalled ? 'Credentials installed' : 'Not uploaded' }}
                </span>
            </div>

            <form class="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-end" @submit.prevent="uploadCredentials">
                <div>
                    <label class="label">Service-account JSON file</label>
                    <input type="file" accept=".json,application/json" class="input file:mr-3 file:rounded-[6px] file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700" @change="selectCredentials">
                    <p class="mt-2 text-xs leading-5 text-slate-500">Maximum file size: 1 MB. A valid service-account file must contain project ID, client email and private key.</p>
                    <p v-if="credentialsForm.errors.credentials" class="error">{{ credentialsForm.errors.credentials }}</p>
                </div>
                <button class="btn-primary min-w-40" :disabled="credentialsForm.processing || !credentialsForm.credentials">
                    <Icon name="document-upload" :size="17" />
                    {{ credentialsForm.processing ? 'Uploading...' : credentialsInstalled ? 'Replace credentials' : 'Upload credentials' }}
                </button>
            </form>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Eligible products</p><b class="mt-2 block text-3xl font-semibold">{{ counts.eligible }}</b></div>
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Synced</p><b class="mt-2 block text-3xl font-semibold text-emerald-600">{{ counts.synced }}</b></div>
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Pending</p><b class="mt-2 block text-3xl font-semibold text-amber-600">{{ counts.pending }}</b></div>
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Failed</p><b class="mt-2 block text-3xl font-semibold text-red-600">{{ counts.failed }}</b></div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="card overflow-hidden">
                <div class="border-b p-5"><h2 class="font-semibold">Synchronization log</h2></div>
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Action</th><th>Status</th><th>Date</th><th>Error</th></tr></thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="font-medium">{{ log.product?.name || 'Connection test' }}</td>
                                <td>{{ log.action }}</td>
                                <td><span :class="['status', badge(log.status)]">{{ log.status }}</span></td>
                                <td>{{ new Date(log.created_at).toLocaleString() }}</td>
                                <td><p class="max-w-64 truncate text-xs text-red-600" :title="log.error_message">{{ log.error_message || '—' }}</p></td>
                            </tr>
                            <tr v-if="!logs.data.length"><td colspan="5" class="py-16 text-center text-slate-400">No synchronization activity.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-4"><Pagination :links="logs.links" /></div>
            </section>

            <aside class="space-y-5">
                <section class="card p-5">
                    <h2 class="font-semibold">Current settings</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Enabled</dt><dd class="font-medium">{{ config.enabled ? 'Yes' : 'No' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Account ID</dt><dd class="font-medium">{{ config.account_id || 'Not set' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Data source</dt><dd class="font-medium">{{ config.data_source_id || 'Not set' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Language</dt><dd class="font-medium">{{ config.content_language }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Feed label</dt><dd class="font-medium">{{ config.feed_label }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Currency</dt><dd class="font-medium">{{ config.currency }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-[10px] bg-slate-950 p-5 text-white">
                    <h2 class="font-semibold">Automated refresh</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">Scheduled synchronization status.</p>
                    <code class="mt-4 block rounded-[8px] bg-black/40 p-3 text-xs text-prinx-300">php artisan schedule:work<br>php artisan queue:work</code>
                </section>

                <section class="card p-5">
                    <h2 class="font-semibold">Fallback XML feed</h2>
                    <p class="mt-2 text-sm text-slate-500">XML product feed.</p>
                    <a href="/google-merchant-feed.xml" target="_blank" class="btn-light mt-4 w-full"><Icon name="document-text" :size="17" /> Open product feed</a>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
