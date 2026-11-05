<script setup>
import { ref, watch } from 'vue';
import { postJson } from '../lib/adminApi';

// Checks what Tanzanian sellers ask online for a product and lets the user
// apply the suggested price. Emits "use" with a price.
const props = defineProps({
    productId: { type: [Number, String], default: null },
    variantId: { type: [Number, String], default: null },
    enabled: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});
const emit = defineEmits(['use']);

const loading = ref(false);
const error = ref('');
const result = ref(null);
const open = ref(false);

const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const host = (url) => { try { return new URL(url).hostname.replace(/^www\./, ''); } catch { return url; } };

watch(() => [props.productId, props.variantId], () => {
    result.value = null;
    error.value = '';
    open.value = false;
});

async function check(refresh = false) {
    if (!props.productId || loading.value) return;
    open.value = true;
    loading.value = true;
    error.value = '';
    const response = await postJson('/admin/market-price', {
        product_id: props.productId,
        product_variant_id: props.variantId || null,
        refresh,
    });
    loading.value = false;
    if (response.ok) result.value = response.data;
    else error.value = response.message;
}

const use = (price) => {
    emit('use', price);
    open.value = false;
};
</script>

<template>
    <div v-if="enabled" class="text-xs">
        <button type="button" class="inline-flex items-center gap-1 font-semibold text-prinx-600 hover:text-prinx-700 disabled:text-slate-400" :disabled="!productId || loading" @click="open && result ? (open = false) : check()">
            {{ loading ? 'Checking Tanzanian prices…' : (open && result ? 'Hide market prices' : 'Check market price') }}
        </button>

        <div v-if="open && !loading && (result || error)" class="mt-2 rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
            <p v-if="error" class="text-red-600">{{ error }}</p>

            <template v-else-if="result">
                <template v-if="result.listings?.length">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[.12em] text-slate-400">Suggested price</p>
                            <p class="text-base font-semibold text-slate-950">{{ money(result.suggested_price) }}</p>
                            <p class="text-slate-500">Range {{ money(result.low) }} – {{ money(result.high) }} · {{ result.listings.length }} listing{{ result.listings.length === 1 ? '' : 's' }}</p>
                        </div>
                        <button type="button" class="rounded-[6px] bg-slate-950 px-3 py-1.5 font-semibold text-white" @click="use(result.suggested_price)">Use {{ money(result.suggested_price) }}</button>
                    </div>
                    <ul :class="['mt-3 divide-y divide-slate-100 border-t border-slate-100', compact ? 'max-h-40 overflow-auto' : 'max-h-64 overflow-auto']">
                        <li v-for="listing in result.listings" :key="listing.url + listing.price" class="flex items-start justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <a :href="listing.url" target="_blank" rel="noopener noreferrer" class="block truncate font-semibold text-slate-800 hover:text-prinx-600">{{ listing.seller || host(listing.url) }}</a>
                                <p class="truncate text-slate-500">{{ listing.title }}</p>
                                <p class="text-[10px] text-slate-400">{{ [listing.location, listing.condition, host(listing.url)].filter(Boolean).join(' · ') }}</p>
                            </div>
                            <button type="button" class="shrink-0 font-semibold text-slate-900 hover:text-prinx-600" title="Use this price" @click="use(listing.price)">{{ money(listing.price) }}</button>
                        </li>
                    </ul>
                </template>
                <p v-else class="text-slate-600">No matching Tanzanian listings were found.<template v-if="result.notes"> {{ result.notes }}</template></p>
                <p v-if="result.listings?.length && result.notes" class="mt-2 text-[11px] text-slate-500">{{ result.notes }}</p>
                <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                    <span>Searched “{{ result.query }}” · {{ new Date(result.checked_at).toLocaleString() }}</span>
                    <button type="button" class="font-semibold text-slate-500 hover:text-prinx-600" @click="check(true)">Search again</button>
                </div>
            </template>
        </div>
    </div>
</template>
