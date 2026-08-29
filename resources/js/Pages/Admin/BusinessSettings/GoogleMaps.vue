<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    available: Boolean,
    configured: Boolean,
});

const form = useForm({
    _method: 'put',
    google_maps_api_key: '',
    clear_google_maps_api_key: false,
});

const save = () => form.post('/admin/business-settings/maps', {
    preserveScroll: true,
    onSuccess: () => {
        form.google_maps_api_key = '';
        form.clear_google_maps_api_key = false;
    },
});
</script>

<template>
    <AdminLayout>
        <Head title="Google Maps Settings" />

        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Business settings</p>
                <h1 class="section-title">Google Maps</h1>
                <p class="mt-1 text-[12px] text-slate-500">Configure the API key used by the store locator on the Contact Us page.</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/admin/business-settings" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Business settings</Link>
                <button type="button" class="btn-primary" :disabled="form.processing || !available" @click="save">
                    <Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" />
                    {{ form.processing ? 'Saving...' : 'Save key' }}
                </button>
            </div>
        </div>

        <form class="mt-4 max-w-3xl" @submit.prevent="save">
            <section class="form-section">
                <div class="mb-4 flex items-start gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="location" :size="17" /></span>
                    <div>
                        <h2 class="font-semibold text-slate-900">Store locator API key</h2>
                        <p class="text-[11px] text-slate-500">The public Contact Us page uses Google Maps Locator Plus to show Prinx Tech in Dodoma.</p>
                    </div>
                </div>

                <div v-if="!available" class="rounded-[7px] border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                    Run the latest database migrations before configuring Google Maps.
                </div>

                <div v-else class="space-y-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                        <span class="font-semibold text-slate-900">Status:</span>
                        <span :class="configured ? 'text-emerald-600' : 'text-amber-600'" class="ml-1 font-semibold">{{ configured ? 'API key configured' : 'Not configured' }}</span>
                    </div>

                    <div>
                        <label class="label">Google Maps API key</label>
                        <input
                            v-model="form.google_maps_api_key"
                            type="password"
                            autocomplete="new-password"
                            class="input"
                            :placeholder="configured ? 'Configured — leave blank to keep the current key' : 'Paste your Google Maps API key'"
                            :disabled="form.clear_google_maps_api_key"
                        >
                        <p class="mt-1 text-[10px] leading-4 text-slate-500">Use the browser API key from your Google Maps Platform project. Restrict the key to your website domain with HTTP referrer restrictions.</p>
                        <p v-if="form.errors.google_maps_api_key" class="error">{{ form.errors.google_maps_api_key }}</p>
                    </div>

                    <label v-if="configured" class="flex cursor-pointer items-center gap-2 rounded-[7px] border border-slate-200 px-3 py-2.5 text-xs text-slate-700">
                        <input v-model="form.clear_google_maps_api_key" type="checkbox" class="h-4 w-4 accent-prinx-500">
                        Remove the currently saved Google Maps API key
                    </label>

                    <div class="rounded-[8px] border border-slate-200 bg-white px-3 py-3 text-[11px] leading-5 text-slate-600">
                        <p class="font-semibold text-slate-900">Configured store location</p>
                        <p class="mt-1">Prinx Tech · Uhindini Street, Dodoma, Tanzania</p>
                        <p>Coordinates: -6.1759075, 35.7490705</p>
                    </div>
                </div>
            </section>
        </form>
    </AdminLayout>
</template>
