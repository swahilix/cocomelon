<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
    days: { type: Number, default: 30 },
    summary: { type: Object, default: () => ({}) },
    sources: { type: Array, default: () => [] },
    topProducts: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
});

const page = usePage();
const currency = computed(() => page.props.store?.currency || 'TZS');
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: currency.value,
    maximumFractionDigits: 0,
}).format(Number(value || 0));
const number = (value) => Number(value || 0).toLocaleString('en-TZ');
const sourceLabel = (value) => ({
    google: 'Google',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    whatsapp: 'WhatsApp',
    direct: 'Direct',
    referral: 'Referral',
    unknown: 'Unknown',
}[value] || value || 'Unknown');
const setDays = (days) => router.get('/admin/conversions', { days }, { preserveState: true, replace: true });
const sourceWithOrders = computed(() => props.sources
    .filter((source) => source.source !== 'unknown' && Number(source.completed_orders || 0) > 0)
    .sort((a, b) => Number(b.revenue || 0) - Number(a.revenue || 0)));
const strongestSource = computed(() => sourceWithOrders.value[0] || null);
</script>

<template>
    <AdminLayout>
        <Head title="Conversion Report" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Sales funnel</p>
                <h1 class="section-title">Conversion report</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">See which traffic sources create product interest, checkout activity, WhatsApp enquiries and completed online orders.</p>
            </div>
            <div class="inline-flex h-10 w-fit overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                <button v-for="option in [7, 30, 90]" :key="option" type="button" :class="['min-w-16 border-r border-slate-200 px-3 text-xs font-semibold last:border-r-0', days === option ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50']" @click="setDays(option)">{{ option }} days</button>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-slate-400">Product views</p><b class="mt-2 block text-2xl">{{ number(summary.product_views) }}</b><p class="mt-1 text-xs text-slate-500">Unique product/session/day events</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-prinx-600">Add to cart</p><b class="mt-2 block text-2xl">{{ number(summary.cart_adds) }}</b><p class="mt-1 text-xs text-slate-500">{{ number(summary.buy_now) }} used Buy Now</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-blue-600">Checkout starts</p><b class="mt-2 block text-2xl">{{ number(summary.checkout_starts) }}</b><p class="mt-1 text-xs text-slate-500">{{ Number(summary.checkout_conversion_rate || 0).toFixed(1) }}% reached completed order</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-emerald-600">Completed orders</p><b class="mt-2 block text-2xl">{{ number(summary.completed_orders) }}</b><p class="mt-1 text-xs font-semibold text-emerald-700">{{ money(summary.completed_revenue) }} revenue</p></div>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div class="card p-4"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-emerald-600">WhatsApp enquiries</p><b class="mt-2 block text-2xl">{{ number(summary.whatsapp_enquiries) }}</b><p class="mt-1 text-xs text-slate-500">Product enquiry clicks recorded before WhatsApp opens.</p></div>
            <div class="card p-4"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-slate-400">Strongest completed-sales source</p><template v-if="strongestSource"><b class="mt-2 block text-xl">{{ sourceLabel(strongestSource.source) }}</b><p class="mt-1 text-xs text-slate-500">{{ number(strongestSource.completed_orders) }} completed · {{ money(strongestSource.revenue) }}</p></template><p v-else class="mt-2 text-sm font-semibold text-slate-600">No completed attributed orders yet</p></div>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold">Source performance</h2>
                <p class="mt-1 text-xs text-slate-500">First-touch source is preserved through checkout so completed sales remain tied to where the customer originally arrived.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[980px]">
                    <thead><tr><th>Source</th><th>Views</th><th>Cart</th><th>Buy Now</th><th>Checkout</th><th>WhatsApp</th><th>Completed</th><th>Checkout → completed</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <tr v-for="source in sources" :key="source.source">
                            <td><span class="inline-flex rounded-[6px] bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ sourceLabel(source.source) }}</span></td>
                            <td>{{ number(source.product_views) }}</td>
                            <td>{{ number(source.cart_adds) }}</td>
                            <td>{{ number(source.buy_now) }}</td>
                            <td>{{ number(source.checkout_starts) }}</td>
                            <td>{{ number(source.whatsapp_enquiries) }}</td>
                            <td class="font-semibold">{{ number(source.completed_orders) }}</td>
                            <td>{{ Number(source.checkout_conversion_rate || 0).toFixed(1) }}%</td>
                            <td class="font-semibold text-emerald-700">{{ money(source.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Products creating interest</h2><p class="mt-1 text-xs text-slate-500">Real views, cart additions, Buy Now actions and WhatsApp enquiries.</p></div>
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[650px]">
                        <thead><tr><th>Product</th><th>Views</th><th>Cart</th><th>Buy Now</th><th>WhatsApp</th></tr></thead>
                        <tbody>
                            <tr v-for="product in topProducts" :key="product.id">
                                <td><Link :href="`/products/${product.slug}`" class="font-semibold text-slate-900 hover:text-prinx-600">{{ product.name }}</Link></td>
                                <td>{{ number(product.product_views) }}</td><td>{{ number(product.cart_adds) }}</td><td>{{ number(product.buy_now) }}</td><td>{{ number(product.whatsapp_enquiries) }}</td>
                            </tr>
                            <tr v-if="!topProducts.length"><td colspan="5" class="py-10 text-center text-slate-400">No product conversion activity in this period yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">UTM campaigns</h2><p class="mt-1 text-xs text-slate-500">Use tagged links such as <span class="font-mono">?utm_source=instagram&utm_campaign=weekend_sale</span> to measure individual campaigns.</p></div>
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[620px]">
                        <thead><tr><th>Campaign</th><th>Source</th><th>Events</th><th>Checkout</th><th>Completed</th><th>Revenue</th></tr></thead>
                        <tbody>
                            <tr v-for="campaign in campaigns" :key="`${campaign.source}-${campaign.utm_campaign}`">
                                <td class="font-semibold">{{ campaign.utm_campaign }}</td><td>{{ sourceLabel(campaign.source) }}</td><td>{{ number(campaign.events) }}</td><td>{{ number(campaign.checkout_starts) }}</td><td>{{ number(campaign.completed_orders) }}</td><td class="font-semibold text-emerald-700">{{ money(campaign.revenue) }}</td>
                            </tr>
                            <tr v-if="!campaigns.length"><td colspan="6" class="py-10 text-center text-slate-400">No tagged campaign traffic yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="mt-5 rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-xs leading-5 text-slate-500">
            This report uses first-party storefront events. Historical orders created before Phase 9 do not receive invented sources; attribution starts when this phase is deployed.
        </div>
    </AdminLayout>
</template>
