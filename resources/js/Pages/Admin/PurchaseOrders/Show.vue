<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    order: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    estimate: { type: Object, default: () => ({}) },
});

const page = usePage();
const store = computed(() => page.props.store || {});
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: store.value.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const formatDate = (value) => (value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '');
const isOpen = computed(() => ['draft', 'sent'].includes(props.order.status));
const busy = ref('');

const statusLabel = (status) => ({ converted: 'Purchased' }[status] || status);
const statusClass = (status) => ({
    draft: 'border-slate-200 bg-slate-50 text-slate-600',
    sent: 'border-blue-200 bg-blue-50 text-blue-700',
    converted: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    cancelled: 'border-red-200 bg-red-50 text-red-700',
}[status] || 'border-slate-200 bg-slate-50 text-slate-600');

const post = (action, confirmText = null) => {
    if (confirmText && !window.confirm(confirmText)) return;
    busy.value = action;
    router.post(`/admin/purchase-orders/${props.order.id}/${action}`, {}, { preserveScroll: true, onFinish: () => { busy.value = ''; } });
};
const print = () => window.print();
</script>

<template>
    <AdminLayout>
        <Head :title="`Purchase order ${order.reference}`" />

        <!-- In-system view (hidden when printing) -->
        <div class="print-hidden">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-sm text-slate-500"><Link href="/admin/purchase-orders" class="hover:text-prinx-600">Purchase orders</Link><span>·</span><span>{{ order.reference }}</span></div>
                    <h1 class="section-title mt-1">Purchase order {{ order.reference }}</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ order.display_supplier_name || 'Supplier not chosen' }} · {{ formatDate(order.order_date) }}<template v-if="order.expected_date"> · Needed by {{ formatDate(order.expected_date) }}</template></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span :class="['inline-flex items-center rounded-[8px] border px-3 py-2 text-xs font-semibold capitalize', statusClass(order.status)]">{{ statusLabel(order.status) }}</span>
                    <button type="button" class="btn-light" @click="print"><Icon name="printer" :size="16" /> Print / PDF</button>
                    <a :href="`/admin/purchase-orders/${order.id}/export`" class="btn-light"><Icon name="export-1" :size="16" /> Download Excel</a>
                    <Link v-if="isOpen" :href="`/admin/purchase-orders/${order.id}/edit`" class="btn-light"><Icon name="edit-2" :size="16" /> Edit</Link>
                </div>
            </div>

            <div v-if="order.status === 'converted' && order.stock_purchase_id" class="mt-5 flex flex-wrap items-center justify-between gap-2 rounded-[10px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <span>This order was purchased as <strong>{{ order.purchase_reference }}</strong>.</span>
                <Link :href="`/admin/stock/purchases/${order.stock_purchase_id}`" class="text-xs font-semibold underline">View purchase</Link>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_320px]">
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Items</h2><p class="mt-1 text-xs text-slate-500">The estimate columns are only shown here. Printed and downloaded copies list items and quantities only.</p></div>
                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[760px]">
                            <thead><tr><th>#</th><th>Item</th><th>Quantity</th><th>In stock</th><th>Est. cost (each)</th><th>Est. total</th></tr></thead>
                            <tbody>
                                <tr v-for="(item, index) in items" :key="item.id">
                                    <td class="text-slate-400">{{ index + 1 }}</td>
                                    <td><p class="font-semibold">{{ item.product_name }}</p><p v-if="item.variant_name" class="text-xs font-semibold text-prinx-700">{{ item.variant_name }}</p><p class="text-xs text-slate-500">{{ item.sku }}<template v-if="item.notes"> · {{ item.notes }}</template></p></td>
                                    <td class="font-semibold">{{ item.quantity }}</td>
                                    <td>{{ item.current_stock ?? '—' }}</td>
                                    <td>{{ item.estimated_unit_cost !== null ? money(item.estimated_unit_cost) : '—' }}</td>
                                    <td class="font-semibold">{{ item.estimated_total !== null ? money(item.estimated_total) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="space-y-5">
                    <section class="card p-5">
                        <h2 class="font-semibold">Estimated cost</h2>
                        <p class="mt-3 text-2xl font-semibold">{{ money(estimate.total) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ estimate.quantity }} item{{ estimate.quantity === 1 ? '' : 's' }}, based on last purchase prices.</p>
                        <p v-if="estimate.missing" class="mt-2 text-xs text-amber-700">{{ estimate.missing }} line{{ estimate.missing === 1 ? ' has' : 's have' }} no estimate and {{ estimate.missing === 1 ? 'is' : 'are' }} not included.</p>
                    </section>

                    <section v-if="isOpen" class="card space-y-2 p-5">
                        <h2 class="font-semibold">Next step</h2>
                        <Link :href="`/admin/stock/entry?purchase_order=${order.id}`" class="btn-primary w-full justify-center"><Icon name="shopping-cart" :size="16" /> Goods arrived: make purchase</Link>
                        <p class="text-[11px] text-slate-500">Opens Add stock with these items. Enter the actual prices and save to record the purchase.</p>
                        <button v-if="order.status === 'draft'" type="button" class="btn-light w-full justify-center" :disabled="busy === 'sent'" @click="post('sent')"><Icon name="truck" :size="16" /> Mark as sent to supplier</button>
                        <button type="button" class="btn-light w-full justify-center border-red-200 text-red-600" :disabled="busy === 'cancel'" @click="post('cancel', 'Cancel this purchase order?')"><Icon name="close-circle" :size="16" /> Cancel order</button>
                    </section>

                    <section class="card p-5 text-xs text-slate-600">
                        <p v-if="order.notes"><span class="font-semibold text-slate-900">Notes:</span> {{ order.notes }}</p>
                        <p :class="order.notes ? 'mt-2' : ''">Created by {{ order.created_by_name || '—' }} on {{ formatDate(order.created_at) }}</p>
                        <p v-if="order.sent_at">Sent on {{ formatDate(order.sent_at) }}</p>
                        <p v-if="order.cancelled_at">Cancelled on {{ formatDate(order.cancelled_at) }}</p>
                    </section>
                </div>
            </div>
        </div>

        <!-- Printed document: items and quantities only, no prices -->
        <article class="hidden text-[12px] text-black print:block">
            <header class="flex items-start justify-between border-b-2 border-black pb-3">
                <div>
                    <p class="text-lg font-bold">{{ store.name }}</p>
                    <p v-if="store.address">{{ store.address }}</p>
                    <p>{{ [store.phone, store.email].filter(Boolean).join(' · ') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold tracking-wide">PURCHASE ORDER</p>
                    <p class="mt-1 font-semibold">{{ order.reference }}</p>
                    <p>Date: {{ formatDate(order.order_date) }}</p>
                    <p v-if="order.expected_date">Needed by: {{ formatDate(order.expected_date) }}</p>
                </div>
            </header>

            <section class="mt-4">
                <p class="text-[10px] font-bold uppercase tracking-wider">Supplier</p>
                <p class="font-semibold">{{ order.display_supplier_name || '______________________________' }}</p>
                <p v-if="order.supplier_phone || order.supplier_email">{{ [order.supplier_phone, order.supplier_email].filter(Boolean).join(' · ') }}</p>
            </section>

            <table class="mt-4 w-full border-collapse">
                <thead>
                    <tr class="border-y border-black text-left">
                        <th class="w-10 py-1.5 pr-2">No.</th>
                        <th class="py-1.5 pr-2">Item</th>
                        <th class="py-1.5 pr-2">SKU</th>
                        <th class="w-20 py-1.5 pr-2 text-right">Quantity</th>
                        <th class="w-40 py-1.5">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id" class="border-b border-gray-300 align-top">
                        <td class="py-1.5 pr-2">{{ index + 1 }}</td>
                        <td class="py-1.5 pr-2"><span class="font-semibold">{{ item.product_name }}</span><span v-if="item.variant_name"> — {{ item.variant_name }}</span></td>
                        <td class="py-1.5 pr-2">{{ item.sku }}</td>
                        <td class="py-1.5 pr-2 text-right font-semibold">{{ item.quantity }}</td>
                        <td class="py-1.5">{{ item.notes }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-black">
                        <td></td>
                        <td class="py-1.5 font-bold" colspan="2">Total quantity</td>
                        <td class="py-1.5 pr-2 text-right font-bold">{{ estimate.quantity }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <p v-if="order.notes" class="mt-4"><span class="font-bold">Notes:</span> {{ order.notes }}</p>

            <div class="mt-12 grid grid-cols-2 gap-10">
                <div><div class="border-t border-black pt-1">Prepared by{{ order.created_by_name ? `: ${order.created_by_name}` : '' }}</div></div>
                <div><div class="border-t border-black pt-1">Approved by</div></div>
            </div>
        </article>
    </AdminLayout>
</template>
