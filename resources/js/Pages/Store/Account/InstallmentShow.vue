<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({
    plan: Object,
    items: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
});

const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const dateTime = (value) => value ? new Date(value).toLocaleString('en-TZ', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const statusClass = (status) => ({
    active: 'bg-amber-50 text-amber-700',
    paid: 'bg-emerald-50 text-emerald-700',
    released: 'bg-sky-50 text-sky-700',
    cancelled: 'bg-slate-100 text-slate-500',
}[status] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <AppLayout>
        <Head :title="`Installment ${plan.reference}`"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>

        <section class="bg-[#fbfbfb] py-5 sm:py-7">
            <div class="container-shell">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span>
                    <Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span>
                    <Link href="/account/installments" class="hover:text-prinx-600">Installments</Link><span>/</span>
                    <span class="text-prinx-600">{{ plan.reference }}</span>
                </nav>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Installment purchase</p>
                        <h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[28px]">{{ plan.reference }}</h1>
                        <p class="mt-1 text-xs text-slate-500">Track your reserved products, every payment received and your remaining balance.</p>
                    </div>
                    <span :class="['self-start rounded-full px-3 py-1.5 text-[10px] font-semibold capitalize sm:self-auto', statusClass(plan.status)]">{{ plan.status }}</span>
                </div>

                <div class="mt-5"><CustomerAccountNav /></div>

                <div class="mt-4 grid gap-3 lg:grid-cols-[1.25fr_.75fr]">
                    <section class="rounded-[9px] border border-slate-200 bg-white p-4">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-[7px] bg-slate-50 p-3"><p class="text-[9px] font-semibold uppercase tracking-[.08em] text-slate-400">Net total</p><strong class="mt-1 block text-[12px] text-slate-950">{{ money(plan.net_total) }}</strong></div>
                            <div class="rounded-[7px] bg-slate-50 p-3"><p class="text-[9px] font-semibold uppercase tracking-[.08em] text-slate-400">Discount</p><strong class="mt-1 block text-[12px] text-prinx-600">-{{ money(plan.discount_amount) }}</strong></div>
                            <div class="rounded-[7px] bg-emerald-50 p-3"><p class="text-[9px] font-semibold uppercase tracking-[.08em] text-emerald-600/70">Paid</p><strong class="mt-1 block text-[12px] text-emerald-700">{{ money(plan.paid_amount) }}</strong></div>
                            <div class="rounded-[7px] bg-slate-950 p-3 text-white"><p class="text-[9px] font-semibold uppercase tracking-[.08em] text-slate-400">Remaining</p><strong class="mt-1 block text-[12px]">{{ money(plan.remaining_amount) }}</strong></div>
                        </div>

                        <div class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-[10px]"><span class="font-medium text-slate-600">Payment progress</span><strong class="text-slate-900">{{ plan.progress }}%</strong></div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-prinx-500" :style="{ width: `${plan.progress}%` }"></div></div>
                        </div>
                    </section>

                    <section class="rounded-[9px] border border-slate-200 bg-white p-4">
                        <template v-if="plan.status === 'active'">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-prinx-50 text-prinx-600">₮</div>
                            <h2 class="mt-3 text-sm font-semibold text-slate-950">Continue paying gradually</h2>
                            <p class="mt-1 text-[11px] leading-5 text-slate-500">Make your next payment through Prinx. Once staff confirms it, the payment and new remaining balance will appear here and you will receive an SMS confirmation.</p>
                            <p class="mt-3 rounded-[7px] bg-slate-50 px-3 py-2 text-[11px] font-medium text-slate-700">Balance left: {{ money(plan.remaining_amount) }}</p>
                        </template>
                        <template v-else-if="plan.status === 'paid'">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">✓</div>
                            <h2 class="mt-3 text-sm font-semibold text-slate-950">Fully paid</h2>
                            <p class="mt-1 text-[11px] leading-5 text-slate-500">Your installment balance is complete. Your reserved item{{ items.length === 1 ? '' : 's' }} can now be prepared for release by Prinx.</p>
                        </template>
                        <template v-else-if="plan.status === 'released'">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-50 text-sky-600">✓</div>
                            <h2 class="mt-3 text-sm font-semibold text-slate-950">Items released</h2>
                            <p class="mt-1 text-[11px] leading-5 text-slate-500">This installment purchase was completed and the reserved items were released to you.</p>
                            <p class="mt-3 text-[10px] text-slate-400">Released {{ dateTime(plan.released_at) }}</p>
                        </template>
                        <template v-else>
                            <h2 class="text-sm font-semibold text-slate-950">Plan cancelled</h2>
                            <p class="mt-1 text-[11px] leading-5 text-slate-500">This installment plan is no longer active.</p>
                        </template>
                    </section>
                </div>

                <section class="mt-4 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Reserved products</h2><p class="mt-1 text-[11px] text-slate-500">These items are attached to this installment agreement.</p></div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="item in items" :key="item.id" class="grid gap-2 px-4 py-3.5 sm:grid-cols-[1fr_100px_150px] sm:items-center">
                            <div><strong class="block text-xs font-semibold text-slate-900">{{ item.product_name }}</strong><span class="mt-1 block text-[10px] text-slate-400">{{ item.sku || 'No SKU' }}</span></div>
                            <div class="text-[11px] text-slate-500">Qty <strong class="text-slate-800">{{ item.quantity }}</strong></div>
                            <div class="text-left sm:text-right"><strong class="text-xs font-semibold text-slate-900">{{ money(item.line_total) }}</strong><span class="ml-2 text-[10px] text-slate-400">{{ money(item.unit_price) }} each</span></div>
                        </div>
                    </div>
                </section>

                <section class="mt-4 overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Payment history</h2><p class="mt-1 text-[11px] text-slate-500">Every confirmed installment payment is listed here.</p></div>
                    <div v-if="payments.length" class="divide-y divide-slate-100">
                        <div v-for="payment in payments" :key="payment.id" class="grid gap-2 px-4 py-3.5 sm:grid-cols-[1fr_150px_120px] sm:items-center">
                            <div><strong class="block text-xs font-semibold text-slate-900">{{ payment.reference }}</strong><span class="mt-1 block text-[10px] text-slate-400">{{ dateTime(payment.created_at) }} · {{ payment.payment_method_name }}</span><span v-if="payment.external_reference" class="mt-1 block text-[10px] text-slate-400">Transaction: {{ payment.external_reference }}</span></div>
                            <div><span :class="['rounded-full px-2 py-1 text-[9px] font-semibold', payment.sms_status === 'sent' ? 'bg-emerald-50 text-emerald-700' : payment.sms_status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700']">SMS {{ payment.sms_status }}</span></div>
                            <strong class="text-left text-xs font-semibold text-emerald-700 sm:text-right">{{ money(payment.amount) }}</strong>
                        </div>
                    </div>
                    <div v-else class="px-4 py-8 text-center text-xs text-slate-500">No payments have been recorded yet.</div>
                </section>
            </div>
        </section>
    </AppLayout>
</template>
