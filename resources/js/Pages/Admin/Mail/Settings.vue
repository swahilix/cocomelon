<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ accounts: Array, staff: Array, can: Object });

const editorOpen = ref(false);
const editing = ref(null);
const accessAccount = ref(null);
const connectionTest = ref({
    imap: { loading: false, ok: null, message: '' },
    smtp: { loading: false, ok: null, message: '' },
});

const emptyMailbox = () => ({
    name: '', email: '', provider: 'cPanel / Custom',
    imap_host: '', imap_port: 993, imap_encryption: 'ssl', imap_username: '', imap_password: '',
    smtp_host: '', smtp_port: 465, smtp_encryption: 'ssl', smtp_username: '', smtp_password: '',
    from_name: '', reply_to: '', signature_html: '', sync_enabled: true, send_enabled: true, status: 'active',
});

const form = useForm(emptyMailbox());
const accessForm = useForm({ assignments: [] });
const isCreate = computed(() => !editing.value);

function resetConnectionTests() {
    connectionTest.value = {
        imap: { loading: false, ok: null, message: '' },
        smtp: { loading: false, ok: null, message: '' },
    };
}

function fillMailbox(values) {
    Object.assign(form, emptyMailbox(), values, { imap_password: '', smtp_password: '' });
    form.clearErrors();
    resetConnectionTests();
}

function openCreate() {
    editing.value = null;
    fillMailbox(emptyMailbox());
    editorOpen.value = true;
}

function openEdit(account) {
    editing.value = account;
    fillMailbox(account);
    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editing.value = null;
    form.clearErrors();
    resetConnectionTests();
}

function saveMailbox() {
    const options = { preserveScroll: true, onSuccess: closeEditor };
    if (editing.value) form.put(`/admin/mail-settings/${editing.value.id}`, options);
    else form.post('/admin/mail-settings', options);
}

async function testConnection(type) {
    const state = connectionTest.value[type];
    const prefix = type === 'imap' ? 'imap' : 'smtp';
    const password = form[`${prefix}_password`];
    const storedPassword = type === 'imap' ? editing.value?.imap_password_set : editing.value?.smtp_password_set;

    if (!form[`${prefix}_host`] || !form[`${prefix}_port`] || !form[`${prefix}_username`]) {
        state.ok = false;
        state.message = `Enter the ${type.toUpperCase()} host, port and username first.`;
        return;
    }

    if (!password && !storedPassword) {
        state.ok = false;
        state.message = `Enter the ${type.toUpperCase()} password first.`;
        return;
    }

    state.loading = true;
    state.ok = null;
    state.message = '';

    try {
        const response = await axios.post(`/admin/mail-settings/test-${type}`, {
            account_id: editing.value?.id || null,
            host: form[`${prefix}_host`],
            port: form[`${prefix}_port`],
            encryption: form[`${prefix}_encryption`],
            username: form[`${prefix}_username`],
            password: password || null,
        });

        state.ok = true;
        state.message = response.data?.message || `${type.toUpperCase()} connection succeeded.`;
    } catch (error) {
        state.ok = false;
        const errors = error.response?.data?.errors || {};
        state.message = errors.password?.[0]
            || errors.host?.[0]
            || error.response?.data?.message
            || `${type.toUpperCase()} connection test failed.`;
    } finally {
        state.loading = false;
    }
}

function openAccess(account) {
    accessAccount.value = account;
    const existing = new Map((account.assignments || []).map((item) => [item.user_id, item]));
    accessForm.assignments = props.staff.map((member) => {
        const assignment = existing.get(member.id);
        return {
            user_id: member.id,
            enabled: !!assignment,
            can_read: !!assignment?.can_read,
            can_send: !!assignment?.can_send,
            can_delete: !!assignment?.can_delete,
            can_manage: !!assignment?.can_manage,
        };
    });
    accessForm.clearErrors();
}

function closeAccess() {
    accessAccount.value = null;
    accessForm.clearErrors();
}

function toggleEnabled(item) {
    item.enabled = !item.enabled;
    if (item.enabled && !item.can_read) item.can_read = true;
    if (!item.enabled) {
        item.can_read = false;
        item.can_send = false;
        item.can_delete = false;
        item.can_manage = false;
    }
}

function normalizeRead(item) {
    if (item.can_send || item.can_delete || item.can_manage) item.can_read = true;
}

function saveAccess() {
    accessForm.transform((data) => ({
        assignments: data.assignments
            .filter((item) => item.enabled)
            .map(({ user_id, can_read, can_send, can_delete, can_manage }) => ({ user_id, can_read, can_send, can_delete, can_manage })),
    })).put(`/admin/mail-settings/${accessAccount.value.id}/staff`, {
        preserveScroll: true,
        onSuccess: closeAccess,
    });
}

function removeMailbox(account) {
    if (!confirm(`Remove mailbox configuration for ${account.email}? Mailboxes containing business mail cannot be deleted.`)) return;
    router.delete(`/admin/mail-settings/${account.id}`, { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return 'Never';
    return new Date(value).toLocaleString();
}
</script>

<template>
    <AdminLayout>
        <Head title="Mail Settings" />

        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="section-kicker">Business mail</p>
                <h1 class="section-title">Mailbox settings</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Configure company IMAP and SMTP mailboxes, signatures, service status and staff access. Stored passwords are never displayed back in the browser.</p>
            </div>
            <button v-if="can.create" class="btn-primary" @click="openCreate"><Icon name="add-circle" :size="17" /> Add mailbox</button>
        </div>

        <div v-if="!accounts.length" class="card mt-6 p-8 text-center">
            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-[8px] bg-slate-100"><Icon name="setting-2" :size="21" /></div>
            <h2 class="mt-3 font-semibold">No business mailbox configured</h2>
            <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500">Add the email account your company already hosts with cPanel, Namecheap, Zoho, Google Workspace, Microsoft 365 or another IMAP/SMTP provider.</p>
            <button v-if="can.create" class="btn-primary mt-4" @click="openCreate">Add first mailbox</button>
        </div>

        <section v-else class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Mailbox</th><th>Provider</th><th>Service</th><th>Staff</th><th>Last connection</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="account in accounts" :key="account.id">
                            <td><p class="font-semibold text-slate-900">{{ account.name }}</p><p class="text-xs text-slate-500">{{ account.email }}</p></td>
                            <td><p>{{ account.provider || 'Custom' }}</p><p class="text-[11px] text-slate-400">IMAP {{ account.imap_host }}:{{ account.imap_port }}</p></td>
                            <td><div class="flex flex-wrap gap-1.5"><span :class="['rounded-[6px] px-2 py-1 text-[11px] font-semibold', account.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500']">{{ account.status }}</span><span v-if="account.sync_enabled" class="rounded-[6px] bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700">Sync on</span><span v-if="account.send_enabled" class="rounded-[6px] bg-prinx-50 px-2 py-1 text-[11px] font-semibold text-prinx-700">Send on</span></div></td>
                            <td><p class="font-medium">{{ account.assignments.length }} assigned</p><p class="text-[11px] text-slate-400">{{ account.assignments.filter(a => a.can_send).length }} can send</p></td>
                            <td><p class="text-xs">{{ formatDate(account.last_connection_at) }}</p><p v-if="account.last_error" class="mt-1 max-w-[220px] truncate text-[11px] text-red-500" :title="account.last_error">{{ account.last_error }}</p></td>
                            <td class="text-right"><div class="inline-flex flex-wrap justify-end gap-2"><button v-if="account.can_edit_settings" class="btn-light px-3 py-2 text-xs" @click="openEdit(account)"><Icon name="edit-2" :size="14" /> Settings</button><button v-if="account.can_assign_users" class="btn-light px-3 py-2 text-xs" @click="openAccess(account)"><Icon name="shield-user" :size="14" /> Staff</button><button v-if="account.can_delete" class="rounded-[8px] border border-red-200 px-3 py-2 text-xs font-semibold text-red-600" @click="removeMailbox(account)">Remove</button></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="editorOpen" class="fixed inset-0 z-[95] overflow-y-auto bg-slate-950/55 p-3 sm:p-5" @click.self="closeEditor">
            <form class="mx-auto my-3 w-full max-w-4xl rounded-[10px] bg-white p-4 shadow-2xl sm:p-6" @submit.prevent="saveMailbox">
                <div class="flex items-start justify-between gap-4"><div><p class="section-kicker">{{ isCreate ? 'New mailbox' : editing.email }}</p><h2 class="mt-1 text-xl font-semibold">{{ isCreate ? 'Connect business mailbox' : 'Mailbox configuration' }}</h2><p class="mt-1 text-sm text-slate-500">Use the incoming and outgoing server details supplied by your business email provider.</p></div><button type="button" class="p-1" @click="closeEditor"><Icon name="close-circle" :size="22" /></button></div>

                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-700"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label class="label">Mailbox name</label><input v-model="form.name" class="input" placeholder="Sales" required></div>
                    <div><label class="label">Business email</label><input v-model="form.email" type="email" class="input" placeholder="sales@company.com" required></div>
                    <div><label class="label">Provider</label><select v-model="form.provider" class="input"><option>cPanel / Custom</option><option>Namecheap Private Email</option><option>Zoho Mail</option><option>Google Workspace</option><option>Microsoft 365</option><option>Other</option></select></div>
                    <div><label class="label">From name</label><input v-model="form.from_name" class="input" placeholder="Company Sales"></div>
                    <div><label class="label">Reply-to</label><input v-model="form.reply_to" type="email" class="input" placeholder="Optional"></div>
                    <div><label class="label">Status</label><select v-model="form.status" class="input"><option value="active">Active</option><option value="disabled">Disabled</option></select></div>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h3 class="font-semibold">Incoming mail — IMAP</h3><p class="mt-1 text-xs text-slate-500">Used to synchronize Inbox, Sent, Trash and server folders.</p></div>
                        <div class="flex items-center gap-2"><span v-if="editing?.imap_password_set" class="rounded-[6px] bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">Password stored</span><button type="button" class="btn-light px-3 py-2 text-xs" :disabled="connectionTest.imap.loading" @click="testConnection('imap')">{{ connectionTest.imap.loading ? 'Testing…' : 'Test IMAP' }}</button></div>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="lg:col-span-2"><label class="label">IMAP host</label><input v-model="form.imap_host" class="input" placeholder="mail.company.com" required></div><div><label class="label">Port</label><input v-model.number="form.imap_port" type="number" min="1" max="65535" class="input" required></div><div><label class="label">Encryption</label><select v-model="form.imap_encryption" class="input"><option value="ssl">SSL</option><option value="tls">TLS</option><option value="starttls">STARTTLS</option><option value="none">None</option></select></div><div class="lg:col-span-2"><label class="label">Username</label><input v-model="form.imap_username" class="input" autocomplete="off" required></div><div class="lg:col-span-2"><label class="label">Password <span v-if="editing" class="font-normal text-slate-400">— leave blank to keep stored password</span></label><input v-model="form.imap_password" type="password" class="input" autocomplete="new-password" :required="isCreate"></div></div>
                    <p v-if="connectionTest.imap.message" :class="['mt-3 rounded-[7px] border px-3 py-2 text-xs font-medium', connectionTest.imap.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700']">{{ connectionTest.imap.message }}</p>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h3 class="font-semibold">Outgoing mail — SMTP</h3><p class="mt-1 text-xs text-slate-500">Used for new messages, replies, forwards and attachments.</p></div>
                        <div class="flex items-center gap-2"><span v-if="editing?.smtp_password_set" class="rounded-[6px] bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">Password stored</span><button type="button" class="btn-light px-3 py-2 text-xs" :disabled="connectionTest.smtp.loading" @click="testConnection('smtp')">{{ connectionTest.smtp.loading ? 'Testing…' : 'Test SMTP' }}</button></div>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="lg:col-span-2"><label class="label">SMTP host</label><input v-model="form.smtp_host" class="input" placeholder="mail.company.com" required></div><div><label class="label">Port</label><input v-model.number="form.smtp_port" type="number" min="1" max="65535" class="input" required></div><div><label class="label">Encryption</label><select v-model="form.smtp_encryption" class="input"><option value="ssl">SSL</option><option value="tls">TLS</option><option value="starttls">STARTTLS</option><option value="none">None</option></select></div><div class="lg:col-span-2"><label class="label">Username</label><input v-model="form.smtp_username" class="input" autocomplete="off" required></div><div class="lg:col-span-2"><label class="label">Password <span v-if="editing" class="font-normal text-slate-400">— leave blank to keep stored password</span></label><input v-model="form.smtp_password" type="password" class="input" autocomplete="new-password" :required="isCreate"></div></div>
                    <p v-if="connectionTest.smtp.message" :class="['mt-3 rounded-[7px] border px-3 py-2 text-xs font-medium', connectionTest.smtp.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700']">{{ connectionTest.smtp.message }}</p>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-semibold">Sending identity</h3><div class="mt-3"><label class="label">Default HTML signature</label><textarea v-model="form.signature_html" rows="5" class="input font-mono text-xs" placeholder="<strong>Company Name</strong>..."></textarea><p class="mt-1 text-xs text-slate-400">Signature sanitization and rich signature editing will be added in the dedicated signature step.</p></div><div class="mt-4 flex flex-wrap gap-5"><label class="flex items-center gap-2 text-sm font-medium"><input v-model="form.sync_enabled" type="checkbox" :disabled="form.status === 'disabled'"> Enable synchronization</label><label class="flex items-center gap-2 text-sm font-medium"><input v-model="form.send_enabled" type="checkbox" :disabled="form.status === 'disabled'"> Enable sending</label></div></div>

                <div class="mt-6 flex justify-end gap-2 border-t border-slate-200 pt-4"><button type="button" class="btn-light" @click="closeEditor">Cancel</button><button class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving…' : (isCreate ? 'Create mailbox' : 'Save settings') }}</button></div>
            </form>
        </div>

        <div v-if="accessAccount" class="fixed inset-0 z-[96] overflow-y-auto bg-slate-950/55 p-3 sm:p-5" @click.self="closeAccess">
            <div class="mx-auto my-4 w-full max-w-3xl rounded-[10px] bg-white p-4 shadow-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="section-kicker">Mailbox access</p><h2 class="mt-1 text-xl font-semibold">{{ accessAccount.email }}</h2><p class="mt-1 text-sm text-slate-500">Mailbox capabilities work together with each staff member’s role permissions.</p></div><button @click="closeAccess"><Icon name="close-circle" :size="22" /></button></div><p v-if="accessForm.errors.assignments" class="mt-4 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ accessForm.errors.assignments }}</p><div class="mt-5 overflow-x-auto rounded-[8px] border border-slate-200"><table class="admin-table"><thead><tr><th>Staff</th><th>Assigned</th><th>Read</th><th>Send</th><th>Delete</th><th>Manage</th></tr></thead><tbody><tr v-for="(item, index) in accessForm.assignments" :key="item.user_id"><td><p class="font-semibold">{{ staff[index].name }}</p><p class="text-[11px] text-slate-500">{{ staff[index].email }} · {{ staff[index].role?.name || 'No role' }}</p></td><td><input type="checkbox" :checked="item.enabled" @change="toggleEnabled(item)"></td><td><input v-model="item.can_read" type="checkbox" :disabled="!item.enabled || item.can_send || item.can_delete || item.can_manage"></td><td><input v-model="item.can_send" type="checkbox" :disabled="!item.enabled" @change="normalizeRead(item)"></td><td><input v-model="item.can_delete" type="checkbox" :disabled="!item.enabled" @change="normalizeRead(item)"></td><td><input v-model="item.can_manage" type="checkbox" :disabled="!item.enabled" @change="normalizeRead(item)"></td></tr></tbody></table></div><div class="mt-4 flex justify-end gap-2"><button class="btn-light" @click="closeAccess">Cancel</button><button class="btn-primary" :disabled="accessForm.processing" @click="saveAccess">Save staff access</button></div></div>
        </div>
    </AdminLayout>
</template>
