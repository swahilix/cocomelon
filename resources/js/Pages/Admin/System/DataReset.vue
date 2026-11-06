<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    preview: { type: Object, required: true },
    backups: { type: Array, default: () => [] },
    recentBackup: { type: String, default: null },
    backupValidMinutes: { type: Number, default: 60 },
});

const number = (value) => new Intl.NumberFormat('en-TZ').format(Number(value || 0));
const size = (bytes) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const when = (value) => new Date(value).toLocaleString();

const creatingBackup = ref(false);
const createBackup = () => {
    creatingBackup.value = true;
    router.post('/admin/system/reset/backup', {}, { preserveScroll: true, onFinish: () => { creatingBackup.value = false; } });
};

const trash = computed(() => props.preview.trash || { items: [], total: 0, in_use: 0 });
const form = useForm({ confirmation: '', password: '', reset_transactions: true, empty_trash: (props.preview.trash?.total || 0) > 0 });
const downloaded = ref(false);
const reset = computed(() => props.preview.reset || {});
const transactionsEmpty = computed(() => props.preview.total_rows === 0 && !reset.value.products_with_stock && !reset.value.variants_with_stock && !reset.value.suppliers_with_opening_balance && !reset.value.coupons_used);
const nothingToReset = computed(() => (!form.reset_transactions || transactionsEmpty.value) && (!form.empty_trash || trash.value.total === 0));
const ready = computed(() => Boolean(props.recentBackup) && downloaded.value && (form.reset_transactions || form.empty_trash) && form.confirmation === 'RESET' && form.password.length > 0);
const submit = () => {
    const parts = [];
    if (form.reset_transactions) parts.push('all sales, orders, stock, purchases, expenses, installments and payments');
    if (form.empty_trash) parts.push('everything in the Trash that is not still in use');
    if (!window.confirm(`This permanently deletes ${parts.join(' and ')}. Continue?`)) return;
    form.post('/admin/system/reset', { preserveScroll: true, onSuccess: () => form.reset() });
};
</script>

<template>
    <AdminLayout>
        <Head title="Reset data" />

        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Settings · Administrators only</p>
                <h1 class="section-title">Reset data</h1>
                <p class="mt-1 max-w-2xl text-[12px] text-slate-500">Clear transactions to start entering data again. Products, categories, brands, customers, suppliers, warranties, staff accounts, settings, website content and current selling prices are kept.</p>
            </div>
            <Link href="/admin/settings" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-50">All settings</Link>
        </div>

        <div class="mt-4 grid max-w-5xl gap-4 lg:grid-cols-[1fr_360px]">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">What will be deleted</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ number(preview.total_rows) }} records in total.</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="group in preview.groups" :key="group.key" class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="text-slate-700">{{ group.label }}</span>
                        <span :class="['font-semibold', group.rows ? 'text-red-600' : 'text-slate-400']">{{ number(group.rows) }}</span>
                    </li>
                </ul>
                <div class="border-t border-slate-200 bg-slate-50 px-5 py-4 text-xs text-slate-600">
                    <p class="font-semibold text-slate-900">Also set to zero</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        <li>Stock of every product ({{ number(reset.products_with_stock) }} with stock now) and variant ({{ number(reset.variants_with_stock) }}), plus average and last purchase cost</li>
                        <li>Supplier opening balances ({{ number(reset.suppliers_with_opening_balance) }} suppliers)</li>
                        <li>Coupon usage counts ({{ number(reset.coupons_used) }} coupons used)</li>
                    </ul>
                    <p class="mt-3">Unit barcode labels are deleted with the stock. Print new labels after you enter purchases.</p>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">
                    <h2 class="flex items-center justify-between font-semibold text-slate-900"><span>Trash</span><span :class="['text-sm', trash.total ? 'text-red-600' : 'text-slate-400']">{{ number(trash.total) }}</span></h2>
                    <p class="mt-1 text-xs text-slate-500">Items you deleted earlier. Emptying the Trash deletes them permanently; they can no longer be restored.</p>
                    <ul v-if="trash.items.length" class="mt-3 space-y-1.5 text-sm">
                        <li v-for="item in trash.items" :key="item.key" class="flex items-center justify-between gap-3"><span class="text-slate-700">{{ item.label }}</span><span class="font-semibold text-slate-900">{{ number(item.count) }}</span></li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-slate-400">The Trash is empty.</p>
                    <p v-if="trash.in_use" class="mt-3 rounded-[8px] bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ number(trash.in_use) }} of these {{ trash.in_use === 1 ? 'is' : 'are' }} still used by sales, purchases, warranties, products or other records, so {{ trash.in_use === 1 ? 'it stays' : 'they stay' }} in the Trash. Resetting transactions at the same time frees most of them.</p>
                </div>
            </section>

            <div class="space-y-4">
                <section class="card p-5">
                    <h2 class="flex items-center gap-2 font-semibold text-slate-900"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white">1</span> Back up first</h2>
                    <p class="mt-2 text-xs text-slate-500">Saves every record that will be deleted to a file. Download it and keep it safe. For a complete copy of the site, also take a full backup in cPanel.</p>
                    <button type="button" class="btn-light mt-3 w-full justify-center" :disabled="creatingBackup" @click="createBackup"><Icon name="export-1" :size="16" /> {{ creatingBackup ? 'Creating backup…' : 'Create backup' }}</button>
                    <div v-if="recentBackup" class="mt-3 rounded-[8px] border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                        <p class="font-semibold">Backup ready</p>
                        <a :href="`/admin/system/reset/backups/${recentBackup}`" class="mt-1 inline-flex items-center gap-1 font-semibold underline" @click="downloaded = true"><Icon name="export-1" :size="14" /> Download {{ recentBackup }}</a>
                        <p class="mt-1 text-emerald-700">Valid for {{ backupValidMinutes }} minutes.</p>
                    </div>
                    <details v-if="backups.length" class="mt-3 text-xs text-slate-500">
                        <summary class="cursor-pointer font-semibold text-slate-600">Earlier backups</summary>
                        <ul class="mt-2 space-y-1">
                            <li v-for="file in backups" :key="file.name" class="flex justify-between gap-2"><a :href="`/admin/system/reset/backups/${file.name}`" class="truncate text-prinx-700 hover:underline">{{ when(file.created_at) }}</a><span>{{ size(file.size) }}</span></li>
                        </ul>
                    </details>
                </section>

                <form class="card border-red-200 p-5" @submit.prevent="submit">
                    <h2 class="flex items-center gap-2 font-semibold text-red-700"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-[11px] text-white">2</span> Reset</h2>
                    <p class="mt-2 text-xs text-slate-500">This cannot be undone. Choose what to reset:</p>
                    <label class="mt-3 flex items-start gap-2 rounded-[8px] border border-slate-200 px-3 py-2 text-xs text-slate-700"><input v-model="form.reset_transactions" type="checkbox" class="mt-0.5 h-4 w-4 accent-red-600"><span><strong class="block text-slate-900">Transactions</strong>Sales, orders, stock, purchases, expenses, installments and payments ({{ number(preview.total_rows) }} records).</span></label>
                    <label class="mt-2 flex items-start gap-2 rounded-[8px] border border-slate-200 px-3 py-2 text-xs text-slate-700"><input v-model="form.empty_trash" type="checkbox" class="mt-0.5 h-4 w-4 accent-red-600" :disabled="!trash.total"><span><strong class="block text-slate-900">Empty the Trash</strong>{{ trash.total ? `${number(trash.total)} deleted item${trash.total === 1 ? '' : 's'} removed for good.` : 'The Trash is empty.' }}</span></label>
                    <label class="mt-3 flex items-start gap-2 text-xs text-slate-700"><input v-model="downloaded" type="checkbox" class="mt-0.5 h-4 w-4 accent-red-600" :disabled="!recentBackup"> I have downloaded the backup and kept it safe.</label>
                    <div class="mt-3"><label class="label">Type RESET to confirm</label><input v-model="form.confirmation" class="input font-mono" autocomplete="off" placeholder="RESET" :disabled="!recentBackup"><p v-if="form.errors.confirmation" class="error">{{ form.errors.confirmation }}</p></div>
                    <div class="mt-3"><label class="label">Your password</label><input v-model="form.password" type="password" class="input" autocomplete="current-password" :disabled="!recentBackup"><p v-if="form.errors.password" class="error">{{ form.errors.password }}</p></div>
                    <button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-[8px] bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!ready || form.processing || nothingToReset"><Icon name="trash" :size="16" /> {{ form.processing ? 'Resetting…' : (form.reset_transactions && form.empty_trash ? 'Reset data and empty Trash' : (form.empty_trash ? 'Empty the Trash' : 'Reset data')) }}</button>
                    <p v-if="!recentBackup" class="mt-2 text-[11px] text-slate-500">Create a backup first.</p>
                    <p v-else-if="!form.reset_transactions && !form.empty_trash" class="mt-2 text-[11px] text-slate-500">Choose at least one option.</p>
                    <p v-else-if="nothingToReset" class="mt-2 text-[11px] text-slate-500">There is nothing to reset.</p>
                </form>

                <section class="card p-5 text-xs text-slate-600">
                    <h2 class="font-semibold text-slate-900">After the reset</h2>
                    <ol class="mt-2 list-decimal space-y-1 pl-4">
                        <li>Enter previous purchases in <Link href="/admin/stock/entry?mode=purchase" class="font-semibold text-prinx-700">Add stock</Link> with their real dates, marked Received. This rebuilds stock and costs.</li>
                        <li>Enter previous sales in <Link href="/admin/sales/past" class="font-semibold text-prinx-700">Record past sale</Link> with their dates.</li>
                        <li>Stock you have but no purchase for can go in as opening stock.</li>
                    </ol>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
