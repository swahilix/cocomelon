<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    available: Boolean,
    openai: { type: Object, default: () => ({}) },
    upcitemdb: { type: Object, default: () => ({}) },
});

const form = useForm({
    _method: 'put',
    openai_api_key: '',
    clear_openai_api_key: false,
    openai_model: props.openai.model || '',
    ai_product_intelligence_enabled: props.openai.product_intelligence_enabled,
    ai_description_formatter_enabled: props.openai.product_formatter_enabled,
    upcitemdb_enabled: props.upcitemdb.enabled,
    upcitemdb_key: '',
    clear_upcitemdb_key: false,
});
const showUpcKey = ref(Boolean(props.upcitemdb.key_source));
const testing = ref(false);

const keyStatus = computed(() => ({
    admin: { text: 'Saved here', tone: 'text-emerald-600' },
    env: { text: 'Set in the server .env file', tone: 'text-emerald-600' },
}[props.openai.key_source] || { text: 'Not set', tone: 'text-amber-600' }));
const hasKey = computed(() => Boolean(props.openai.key_source));

const save = () => form.post('/admin/business-settings/ai', {
    preserveScroll: true,
    onSuccess: () => {
        form.openai_api_key = '';
        form.clear_openai_api_key = false;
        form.upcitemdb_key = '';
        form.clear_upcitemdb_key = false;
    },
});
const test = () => {
    testing.value = true;
    router.post('/admin/business-settings/ai/test', {}, { preserveScroll: true, onFinish: () => { testing.value = false; } });
};
</script>

<template>
    <AdminLayout>
        <Head title="AI & product lookup" />

        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Settings</p>
                <h1 class="section-title">AI & product lookup</h1>
                <p class="mt-1 text-[12px] text-slate-500">Keys and switches for Smart fill (barcode and photo recognition), market price checks and the AI description formatter.</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/admin/settings" class="inline-flex min-h-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white px-3 text-[12px] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">All settings</Link>
                <button type="button" class="btn-primary" :disabled="form.processing || !available" @click="save">
                    <Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" />
                    {{ form.processing ? 'Saving...' : 'Save settings' }}
                </button>
            </div>
        </div>

        <div v-if="!available" class="mt-4 max-w-3xl rounded-[7px] border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
            Run the latest database migrations before configuring AI settings.
        </div>

        <form v-else class="mt-4 max-w-3xl space-y-4" @submit.prevent="save">
            <section class="form-section">
                <div class="mb-4 flex items-start gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="magic-star" :size="17" /></span>
                    <div>
                        <h2 class="font-semibold text-slate-900">OpenAI</h2>
                        <p class="text-[11px] text-slate-500">Used to identify products from photos, search the web for unknown barcodes, check Tanzanian market prices and tidy product descriptions.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                        <span><span class="font-semibold text-slate-900">API key:</span> <span :class="['ml-1 font-semibold', keyStatus.tone]">{{ keyStatus.text }}</span><span v-if="openai.key_hint" class="ml-2 font-mono text-slate-400">{{ openai.key_hint }}</span></span>
                        <button v-if="hasKey" type="button" class="inline-flex items-center gap-1 font-semibold text-prinx-600 hover:text-prinx-700 disabled:text-slate-400" :disabled="testing || form.isDirty" :title="form.isDirty ? 'Save your changes first' : ''" @click="test">
                            <Icon name="flash-1" :size="14" /> {{ testing ? 'Testing…' : 'Test connection' }}
                        </button>
                    </div>

                    <div>
                        <label class="label">{{ hasKey ? 'Replace API key' : 'OpenAI API key' }}</label>
                        <input v-model.trim="form.openai_api_key" type="password" autocomplete="new-password" class="input font-mono" :placeholder="hasKey ? 'Leave blank to keep the current key' : 'sk-...'" :disabled="form.clear_openai_api_key">
                        <p class="mt-1 text-[10px] leading-4 text-slate-500">Create one at platform.openai.com → API keys (billing must be set up). It is stored encrypted and never shown again. A key saved here is used instead of the one in the server .env file.</p>
                        <p v-if="form.errors.openai_api_key" class="error">{{ form.errors.openai_api_key }}</p>
                    </div>

                    <label v-if="openai.key_source === 'admin'" class="flex cursor-pointer items-center gap-2 rounded-[7px] border border-slate-200 px-3 py-2.5 text-xs text-slate-700">
                        <input v-model="form.clear_openai_api_key" type="checkbox" class="h-4 w-4 accent-prinx-500">
                        Remove the key saved here (a key in the server .env file, if any, is used instead)
                    </label>

                    <div>
                        <label class="label">Model</label>
                        <input v-model.trim="form.openai_model" class="input font-mono" :placeholder="openai.default_model || 'gpt-5.4-mini'">
                        <p class="mt-1 text-[10px] leading-4 text-slate-500">Leave blank to use the default ({{ openai.default_model || 'gpt-5.4-mini' }}). Currently using <span class="font-mono">{{ openai.active_model }}</span>. Needs a model that supports web search.</p>
                        <p v-if="form.errors.openai_model" class="error">{{ form.errors.openai_model }}</p>
                    </div>

                    <label class="flex cursor-pointer items-start gap-2 rounded-[7px] border border-slate-200 px-3 py-2.5 text-xs text-slate-700">
                        <input v-model="form.ai_product_intelligence_enabled" type="checkbox" class="mt-0.5 h-4 w-4 accent-prinx-500">
                        <span><strong class="block text-slate-900">Smart fill and market prices</strong>Photo recognition, web search for unknown barcodes and Tanzanian market price checks. Each use is a paid OpenAI request.</span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-[7px] border border-slate-200 px-3 py-2.5 text-xs text-slate-700">
                        <input v-model="form.ai_description_formatter_enabled" type="checkbox" class="mt-0.5 h-4 w-4 accent-prinx-500">
                        <span><strong class="block text-slate-900">AI description formatter</strong>The button on the product form that rewrites rough notes into clean store copy.</span>
                    </label>
                </div>
            </section>

            <section class="form-section">
                <div class="mb-4 flex items-start gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="scan-barcode" :size="17" /></span>
                    <div>
                        <h2 class="font-semibold text-slate-900">Barcode database (UPCitemdb)</h2>
                        <p class="text-[11px] text-slate-500">Checked first when you scan a barcode. The free plan allows about 100 lookups a day without a key.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded-[7px] border border-slate-200 px-3 py-2.5 text-xs text-slate-700">
                        <input v-model="form.upcitemdb_enabled" type="checkbox" class="h-4 w-4 accent-prinx-500">
                        Use the barcode database
                    </label>

                    <button v-if="!showUpcKey" type="button" class="text-xs font-semibold text-prinx-600" @click="showUpcKey = true">I have a paid UPCitemdb key</button>
                    <div v-else>
                        <label class="label">UPCitemdb key <span class="font-normal text-slate-400">(paid plan, optional)</span></label>
                        <input v-model.trim="form.upcitemdb_key" type="password" autocomplete="new-password" class="input font-mono" :placeholder="upcitemdb.key_source ? 'Leave blank to keep the current key' : 'Paste your key'" :disabled="form.clear_upcitemdb_key">
                        <p v-if="upcitemdb.key_source" class="mt-1 text-[10px] text-slate-500">A key is set {{ upcitemdb.key_source === 'admin' ? 'here' : 'in the server .env file' }}.</p>
                        <p v-if="form.errors.upcitemdb_key" class="error">{{ form.errors.upcitemdb_key }}</p>
                        <label v-if="upcitemdb.key_source === 'admin'" class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-slate-700"><input v-model="form.clear_upcitemdb_key" type="checkbox" class="h-4 w-4 accent-prinx-500"> Remove the saved key (use the free plan)</label>
                    </div>
                </div>
            </section>
        </form>
    </AdminLayout>
</template>
