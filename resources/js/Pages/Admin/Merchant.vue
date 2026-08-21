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

const settingsForm = useForm({
    enabled: Boolean(props.config.enabled),
    account_id: props.config.account_id || '',
    data_source_id: props.config.data_source_id || '',
    content_language: props.config.content_language || 'en',
    feed_label: props.config.feed_label || 'TZ',
    currency: props.config.currency || 'TZS',
});

const credentialsForm = useForm({ credentials: null });
const registrationForm = useForm({ developer_email: '' });

const test = () => router.post('/admin/merchant/test');
const sync = () => router.post('/admin/merchant/sync-all');
const badge = (status) => status === 'success' ? 'bg-emerald-100 text-emerald-700' : status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700';

const saveSettings = () => settingsForm.post('/admin/merchant/settings', { preserveScroll: true });

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

const registerGcp = () => {
    registrationForm.post('/admin/merchant/register-gcp', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Google Merchant" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Product distribution</p>
                <h1 class="section-title">Google Merchant Center</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Configure the Merchant API, register the Google Cloud project, test the connection and synchronize eligible products.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="btn-light" :disabled="!configured" @click="test"><Icon name="link-2" :size="17" /> Test connection</button>
                <button class="btn-primary" :disabled="!configured" @click="sync"><Icon name="refresh-2" :size="17" /> Sync all eligible</button>
            </div>
        </div>

        <div :class="['mt-6 rounded-[10px] border p-4', configured ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50']">
            <div class="flex items-start gap-3">
                <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] text-white', configured ? 'bg-emerald-600' : 'bg-amber-600']">
                    <Icon :name="configured ? 'tick-circle' : 'warning-2'" :size="18" />
                </div>
                <div>
                    <h2 class="font-semibold">{{ configured ? 'Merchant settings are complete' : 'Complete the setup below' }}</h2>
                    <p class="mt-1 text-sm leading-6" :class="configured ? 'text-emerald-700' : 'text-amber-800'">
                        {{ configured ? 'Credentials and Merchant account settings are present. If Google reports that the GCP project is not registered, use the registration section below once.' : 'Upload credentials and complete the Merchant account settings before testing synchronization.' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="flex items-start gap-3 border-b border-slate-200 p-5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[9px] bg-prinx-50 text-prinx-600">
                        <Icon name="setting-2" :size="21" />
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-900">Merchant API settings</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Enter the IDs from your Google Merchant Center account. These settings are saved on the server.</p>
                    </div>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="saveSettings">
                    <label class="flex items-center justify-between gap-4 rounded-[9px] border border-slate-200 bg-slate-50 px-4 py-3">
                        <div>
                            <span class="block text-sm font-semibold text-slate-800">Enable Google Merchant</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Allow API testing and product synchronization.</span>
                        </div>
                        <input v-model="settingsForm.enabled" type="checkbox" class="h-5 w-5 accent-prinx-500">
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Merchant Account ID</label>
                            <input v-model.trim="settingsForm.account_id" inputmode="numeric" class="input" placeholder="123456789">
                            <p v-if="settingsForm.errors.account_id" class="error">{{ settingsForm.errors.account_id }}</p>
                        </div>
                        <div>
                            <label class="label">Data Source ID</label>
                            <input v-model.trim="settingsForm.data_source_id" inputmode="numeric" class="input" placeholder="123456789">
                            <p v-if="settingsForm.errors.data_source_id" class="error">{{ settingsForm.errors.data_source_id }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label">Language</label>
                            <input v-model.trim="settingsForm.content_language" class="input" placeholder="en" maxlength="5">
                            <p v-if="settingsForm.errors.content_language" class="error">{{ settingsForm.errors.content_language }}</p>
                        </div>
                        <div>
                            <label class="label">Feed label</label>
                            <input v-model.trim="settingsForm.feed_label" class="input" placeholder="TZ" maxlength="50">
                            <p v-if="settingsForm.errors.feed_label" class="error">{{ settingsForm.errors.feed_label }}</p>
                        </div>
                        <div>
                            <label class="label">Currency</label>
                            <input v-model.trim="settingsForm.currency" class="input" placeholder="TZS" maxlength="3">
                            <p v-if="settingsForm.errors.currency" class="error">{{ settingsForm.errors.currency }}</p>
                        </div>
                    </div>

                    <button class="btn-primary" :disabled="settingsForm.processing">
                        <Icon name="tick-circle" :size="17" />
                        {{ settingsForm.processing ? 'Saving...' : 'Save Merchant settings' }}
                    </button>
                </form>
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[9px] bg-prinx-50 text-prinx-600">
                            <Icon name="document-upload" :size="21" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-slate-900">Service-account credentials</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Upload the JSON downloaded from Google Cloud. The original filename does not matter.</p>
                        </div>
                    </div>
                    <span :class="['status shrink-0', credentialsInstalled ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                        <Icon :name="credentialsInstalled ? 'shield-tick' : 'shield-cross'" :size="14" />
                        {{ credentialsInstalled ? 'Installed' : 'Missing' }}
                    </span>
                </div>

                <form class="p-5" @submit.prevent="uploadCredentials">
                    <label class="label">Google service-account JSON</label>
                    <input type="file" accept=".json,application/json" class="input file:mr-3 file:rounded-[6px] file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700" @change="selectCredentials">
                    <p class="mt-2 text-xs leading-5 text-slate-500">Maximum 1 MB. The system validates the project ID, client email and private key, then stores the file privately as the configured credentials file.</p>
                    <p v-if="credentialsForm.errors.credentials" class="error">{{ credentialsForm.errors.credentials }}</p>

                    <button class="btn-light mt-4" :disabled="credentialsForm.processing || !credentialsForm.credentials">
                        <Icon name="document-upload" :size="17" />
                        {{ credentialsForm.processing ? 'Uploading...' : credentialsInstalled ? 'Replace credentials' : 'Upload credentials' }}
                    </button>
                </form>
            </section>
        </div>

        <section class="card mt-5 overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_420px] lg:items-center">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[9px] bg-blue-50 text-blue-600">
                        <Icon name="cloud-add" :size="21" />
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-900">Register Google Cloud project</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">Merchant API requires a one-time developer registration for the Google Cloud project used by your service-account JSON. Enter a real Google Account email for API notifications. Do not use the service-account email.</p>
                        <div class="mt-3 inline-flex items-center gap-2 rounded-[8px] bg-slate-100 px-3 py-2 text-xs text-slate-600">
                            <Icon name="info-circle" :size="15" />
                            Run this once. After successful registration, Google may need about 5 minutes before API calls start working.
                        </div>
                    </div>
                </div>

                <form class="space-y-3" @submit.prevent="registerGcp">
                    <div>
                        <label class="label">Developer Google email</label>
                        <input v-model.trim="registrationForm.developer_email" type="email" class="input" placeholder="you@gmail.com" autocomplete="email">
                        <p v-if="registrationForm.errors.developer_email" class="error">{{ registrationForm.errors.developer_email }}</p>
                    </div>
                    <button class="btn-primary w-full" :disabled="registrationForm.processing || !credentialsInstalled || !config.account_id">
                        <Icon name="cloud-add" :size="17" />
                        {{ registrationForm.processing ? 'Registering project...' : 'Register GCP Project' }}
                    </button>
                    <p v-if="!credentialsInstalled || !config.account_id" class="text-xs text-amber-600">Upload credentials and save the Merchant Account ID first.</p>
                </form>
            </div>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Eligible products</p><b class="mt-2 block text-3xl font-semibold">{{ counts.eligible }}</b></div>
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Synced</p><b class="mt-2 block text-3xl font-semibold text-emerald-600">{{ counts.synced }}</b></div>
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Pending</p><b class="mt-2 block text-3xl font-semibold text-amber-600">{{ counts.pending }}</b></div>
            <div class="card p-5"><p class="text-xs font-medium uppercase text-slate-400">Failed</p><b class="mt-2 block text-3xl font-semibold text-red-600">{{ counts.failed }}</b></div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_340px]">
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
                    <h2 class="font-semibold">Setup status</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Credentials</dt><dd :class="credentialsInstalled ? 'font-medium text-emerald-600' : 'font-medium text-amber-600'">{{ credentialsInstalled ? 'Installed' : 'Missing' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Integration</dt><dd :class="config.enabled ? 'font-medium text-emerald-600' : 'font-medium text-slate-500'">{{ config.enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Account ID</dt><dd class="max-w-40 truncate font-medium">{{ config.account_id || 'Not set' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Data source</dt><dd class="max-w-40 truncate font-medium">{{ config.data_source_id || 'Not set' }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-[10px] bg-slate-950 p-5 text-white">
                    <h2 class="font-semibold">Automated refresh</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">For background synchronization, keep the Laravel scheduler and queue worker running.</p>
                    <code class="mt-4 block rounded-[8px] bg-black/40 p-3 text-xs text-prinx-300">php artisan schedule:work<br>php artisan queue:work</code>
                </section>

                <section class="card p-5">
                    <h2 class="font-semibold">Fallback XML feed</h2>
                    <p class="mt-2 text-sm text-slate-500">Google-compatible XML product feed.</p>
                    <a href="/google-merchant-feed.xml" target="_blank" class="btn-light mt-4 w-full"><Icon name="document-text" :size="17" /> Open product feed</a>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
