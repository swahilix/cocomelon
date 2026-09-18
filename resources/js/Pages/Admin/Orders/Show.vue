<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ order: Object, inventoryMovements: Array });
const form = useForm({ status: props.order.status, payment_status: props.order.payment_status, note: '' });
const dateTime = (value) => value ? new Date(value).toLocaleString() : '—';
const money = (value) => `TZS ${Number(value || 0).toLocaleString()}`;
</script>

<template>
    <AdminLayout>
        <p class="section-kicker">{{ order.order_number }}</p>
        <h1 class="section-title">Order details</h1>

        <div v-if="form.errors.order" class="mt-5 rounded-[8px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ form.errors.order }}
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_360px]">
            <div class="grid gap-5">
                <section class="card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Items</h2>
                            <p class="mt-1 text-xs text-slate-500">Products captured when this order was placed.</p>
                        </div>
                        <Icon name="shopping-cart" :size="21" class="text-prinx-600" />
                    </div>
                    <div v-for="item in order.items" :key="item.id" class="flex justify-between gap-4 border-b border-slate-100 py-3 last:border-0">
                        <div><p class="font-medium">{{ item.product_name }} × {{ item.quantity }}</p><p class="text-xs text-slate-500">{{ item.sku }}</p></div>
                        <b>{{ money(item.line_total) }}</b>
                    </div>
                </section>

                <section class="card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Payment summary</h2>
                            <p class="mt-1 text-xs text-slate-500">Final online checkout totals recorded for this order.</p>
                        </div>
                        <Icon name="percentage-circle" :size="21" class="text-prinx-600" />
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Subtotal</span><strong>{{ money(order.subtotal) }}</strong></div>
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Shipping</span><strong>{{ money(order.shipping_total) }}</strong></div>
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Fulfillment</span><strong class="capitalize">{{ order.fulfillment_method === 'pickup' ? 'Store pickup' : 'Delivery' }}</strong></div>
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Payment method</span><strong>{{ ({ mobile_money: 'Mobile Money', bank_transfer: 'Bank Transfer', cash_on_delivery: 'Cash Payment' })[order.payment_method] || order.payment_method }}</strong></div>
                        <div v-if="Number(order.discount_total || 0) > 0" class="flex items-center justify-between gap-4 text-emerald-700"><span>Discount <strong v-if="order.coupon_code">({{ order.coupon_code }})</strong></span><strong>-{{ money(order.discount_total) }}</strong></div>
                        <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-3"><span class="font-semibold text-slate-900">Grand total</span><strong class="text-lg text-prinx-600">{{ money(order.grand_total) }}</strong></div>
                    </div>
                </section>

                <section class="card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div><h2 class="font-semibold">Inventory ledger</h2><p class="mt-1 text-xs text-slate-500">Stock activity linked directly to this online order.</p></div>
                        <Icon name="box" :size="21" class="text-prinx-600" />
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 p-3"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-slate-400">Stock posted</p><p class="mt-1 text-sm font-semibold">{{ dateTime(order.inventory_posted_at) }}</p></div>
                        <div class="rounded-[8px] border border-slate-200 p-3"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-slate-400">Stock restored</p><p class="mt-1 text-sm font-semibold">{{ dateTime(order.inventory_restored_at) }}</p></div>
                    </div>
                    <div v-if="!order.inventory_posted_at" class="mt-3 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Historical order: no automatic inventory restoration will be attempted because this order predates ledger-managed checkout.</div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="admin-table">
                            <thead><tr><th>Product</th><th>Type</th><th>Movement</th><th>Balance</th><th>Date</th></tr></thead>
                            <tbody>
                                <tr v-for="movement in inventoryMovements" :key="movement.id">
                                    <td><p class="font-medium">{{ movement.product_name }}</p><p class="text-xs text-slate-500">{{ movement.sku }}</p></td>
                                    <td class="capitalize">{{ movement.type.replaceAll('_', ' ') }}</td>
                                    <td :class="movement.quantity > 0 ? 'font-semibold text-emerald-600' : 'font-semibold text-red-600'">{{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}</td>
                                    <td>{{ movement.balance_after ?? '—' }}</td>
                                    <td>{{ dateTime(movement.created_at) }}</td>
                                </tr>
                                <tr v-if="!inventoryMovements.length"><td colspan="5" class="py-8 text-center text-slate-400">No ledger movements for this order.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="font-semibold">History</h2>
                    <div class="mt-3 space-y-3">
                        <div v-for="history in order.status_history" :key="history.id" class="border-l-2 border-prinx-500 pl-3">
                            <p class="font-semibold capitalize">{{ history.status }} · {{ history.payment_status }}</p>
                            <p class="text-sm text-slate-500">{{ history.note }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <form class="card h-fit p-5" @submit.prevent="form.patch(`/admin/orders/${order.id}`, { preserveScroll: true })">
                <h2 class="font-semibold">Update order</h2>
                <p class="mt-1 text-xs text-slate-500">Cancelling a ledger-managed order before shipment restores its stock exactly once.</p>
                <div class="mt-4 grid gap-4">
                    <div><label class="label">Order status</label><select v-model="form.status" class="input"><option v-for="status in ['pending','confirmed','processing','ready','shipped','completed','cancelled']" :key="status" :value="status">{{ status }}</option></select></div>
                    <div><label class="label">Payment status</label><select v-model="form.payment_status" class="input"><option v-for="status in ['pending','paid','failed','refunded']" :key="status" :value="status">{{ status }}</option></select></div>
                    <div><label class="label">Internal note</label><textarea v-model="form.note" class="input" placeholder="Reason or internal note"></textarea></div>
                    <button class="btn-primary" :disabled="form.processing"><Icon name="tick-circle" :size="17" /> Update order</button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>