<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    plan: Object,
    items: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    reservationPercent: { type: Number, default: 70 },
});

const paymentForm = useForm({
    amount: props.plan.remaining_amount || '',
    payment_method_id: props.paymentMethods[0]?.id || '',
    external_reference: '',
    notes: '',
});

const signedContractForm = useForm({
    signed_contract: null,
});

const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const dateTime = (value) => value ? new Date(value).toLocaleString('en-TZ', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const statusClass = computed(() => ({ active: 'bg-amber-50 text-amber-700', paid: 'bg-emerald-50 text-emerald-700', released: 'bg-sky-50 text-sky-700', cancelled: 'bg-slate-100 text-slate-500' }[props.plan.status] || 'bg-slate-100 text-slate-600'));
const stockReserved = computed(() => props.items.length > 0 && props.items.every((item) => item.reservation_movement_id !== null));
const reservationAmount = computed(() => Number(props.plan.reservation_threshold_amount || (Number(props.plan.net_total || 0) * Number(props.reservationPercent || 70) / 100)));
const paymentAfter = computed(() => Number(props.plan.paid_amount || 0) + Math.max(0, Number(paymentForm.amount || 0)));
const paymentWillReserveStock = computed(() => !stockReserved.value && paymentAfter.value >= reservationAmount.value && Number(paymentForm.amount || 0) > 0);

const postPayment = () => paymentForm.post(`/admin/installments/${props.plan.reference}/payments`, {
    preserveScroll: true,
    onSuccess: () => paymentForm.reset('external_reference', 'notes'),
});
const resendSms = (payment) => router.post(`/admin/installments/${props.plan.reference}/payments/${payment.id}/sms`, {}, { preserveScroll: true });
const uploadSignedContract = () => signedContractForm.post(`/admin/installments/${props.plan.reference}/contract/signed`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => signedContractForm.reset(),
});
const releaseItems = () => {
    if (window.confirm('Confirm that all reserved items are being released to this customer?')) router.post(`/admin/installments/${props.plan.reference}/release`, {}, { preserveScroll: true });
};
const cancelPlan = () => {
    const message = stockReserved.value
        ? 'Cancel this plan and return all reserved items to available stock?'
        : 'Cancel this installment plan? No stock has been reserved yet.';
    if (window.confirm(message)) router.delete(`/admin/installments/${props.plan.reference}`);
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Installment ${plan.reference}`" />

        <div class="mx-auto max-w-[1300px] space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><Link href="/admin/installments" class="text-[10px] font-semibold text-prinx-600">← Installment plans</Link><h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950">{{ plan.reference }}</h1><p class="mt-1 text-[12px] text-slate-500">{{ plan.customer_name }} · {{ plan.customer_phone }}</p></div>
                <span :class="['self-start rounded-full px-3 py-1.5 text-[10px] font-semibold capitalize sm:self-auto', statusClass]">{{ plan.status }}</span>
            </div>

            <div class="grid gap-3 lg:grid-cols-[1.2fr_.8fr]">
                <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Payment progress</h2></div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4"><div class="rounded-[7px] bg-slate-50 p-3"><p class="text-[9px] uppercase tracking-[.08em] text-slate-400">Subtotal</p><strong class="mt-1 block text-[13px]">{{ money(plan.subtotal) }}</strong></div><div class="rounded-[7px] bg-slate-50 p-3"><p class="text-[9px] uppercase tracking-[.08em] text-slate-400">Discount</p><strong class="mt-1 block text-[13px] text-prinx-600">-{{ money(plan.discount_amount) }}</strong></div><div class="rounded-[7px] bg-slate-50 p-3"><p class="text-[9px] uppercase tracking-[.08em] text-slate-400">Paid</p><strong class="mt-1 block text-[13px] text-emerald-700">{{ money(plan.paid_amount) }}</strong></div><div class="rounded-[7px] bg-slate-950 p-3 text-white"><p class="text-[9px] uppercase tracking-[.08em] text-slate-400">Remaining</p><strong class="mt-1 block text-[13px]">{{ money(plan.remaining_amount) }}</strong></div></div>
                        <div class="mt-4"><div class="mb-1.5 flex items-center justify-between text-[10px]"><span class="font-medium text-slate-600">{{ plan.progress }}% paid</span><strong class="text-slate-900">Net total {{ money(plan.net_total) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-prinx-500 transition-all" :style="{ width: `${plan.progress}%` }"></div></div></div>
                        <div :class="['mt-3 flex items-start justify-between gap-3 rounded-[7px] border px-3 py-2.5', stockReserved ? 'border-emerald-100 bg-emerald-50/60' : 'border-amber-100 bg-amber-50/60']">
                            <div><strong :class="['block text-[11px] font-semibold', stockReserved ? 'text-emerald-800' : 'text-amber-800']">{{ stockReserved ? 'Stock reserved' : 'Stock not reserved yet' }}</strong><p :class="['mt-0.5 text-[10px] leading-4', stockReserved ? 'text-emerald-700' : 'text-amber-700']">{{ stockReserved ? `The selected quantities became reserved after payments reached ${reservationPercent}%.` : `The products remain sellable until cumulative payments reach ${money(reservationAmount)} (${reservationPercent}% of the agreed amount).` }}</p></div>
                            <span :class="['shrink-0 rounded-full px-2 py-1 text-[9px] font-semibold', stockReserved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">{{ stockReserved ? 'Reserved' : `${reservationPercent}% trigger` }}</span>
                        </div>
                        <p v-if="plan.notes" class="mt-4 rounded-[7px] border border-slate-100 bg-slate-50 px-3 py-2.5 text-[11px] leading-5 text-slate-600">{{ plan.notes }}</p>
                    </div>
                </section>

                <section class="rounded-[9px] border border-slate-200 bg-white p-4">
                    <template v-if="plan.status === 'active'">
                        <h2 class="text-sm font-semibold text-slate-950">Receive payment</h2><p class="mt-1 text-[11px] text-slate-500">The remaining balance updates immediately and an SMS confirmation is sent to the customer.</p>
                        <form class="mt-3 space-y-3" @submit.prevent="postPayment">
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Amount received</span><input v-model.number="paymentForm.amount" type="number" min="1" :max="plan.remaining_amount" step="0.01" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs outline-none focus:border-prinx-400" required></label>
                            <p v-if="paymentWillReserveStock" class="rounded-[6px] bg-emerald-50 px-2.5 py-2 text-[10px] leading-4 text-emerald-700">This payment takes total payments to at least {{ reservationPercent }}%. The system will reserve the selected stock as part of this payment.</p>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Payment method</span><select v-model="paymentForm.payment_method_id" class="h-9 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-xs" required><option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option></select></label>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Transaction/reference</span><input v-model="paymentForm.external_reference" class="h-9 w-full rounded-[6px] border border-slate-200 px-3 text-xs" placeholder="Optional"></label>
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">Note</span><textarea v-model="paymentForm.notes" rows="2" class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-xs" placeholder="Optional"></textarea></label>
                            <p v-if="paymentForm.errors.amount || paymentForm.errors.payment_method_id" class="text-[11px] text-red-600">{{ paymentForm.errors.amount || paymentForm.errors.payment_method_id }}</p>
                            <button class="flex h-10 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white hover:bg-prinx-600 disabled:bg-slate-300" :disabled="paymentForm.processing">{{ paymentForm.processing ? 'Saving…' : 'Confirm payment received' }}</button>
                        </form>
                    </template>
                    <template v-else-if="plan.status === 'paid'">
                        <div class="flex h-full min-h-[210px] flex-col items-center justify-center text-center"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-xl text-emerald-600">✓</div><h2 class="mt-3 text-sm font-semibold text-slate-950">Balance fully paid</h2><p class="mt-1 max-w-xs text-[11px] leading-5 text-slate-500">The customer has completed all installment payments. The selected stock is reserved and can now be released when handed over.</p><button type="button" class="mt-4 h-9 rounded-[6px] bg-emerald-600 px-4 text-xs font-semibold text-white" @click="releaseItems">Mark items released</button></div>
                    </template>
                    <template v-else><div class="flex min-h-[210px] flex-col items-center justify-center text-center"><h2 class="text-sm font-semibold text-slate-950">{{ plan.status === 'released' ? 'Items released' : 'Plan cancelled' }}</h2><p class="mt-1 text-[11px] text-slate-500">{{ plan.status === 'released' ? `Released ${dateTime(plan.released_at)}` : 'No further payments can be recorded.' }}</p></div></template>
                </section>
            </div>

            <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-950">Installment contract</h2>
                    <p class="mt-1 text-[11px] text-slate-500">Generate a simple Ptix agreement in English or Swahili, print it for signatures and office stamp, then upload the signed copy for records.</p>
                </div>
                <div class="grid gap-4 p-4 lg:grid-cols-[1fr_.9fr]">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Generate contract</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a :href="`/admin/installments/${plan.reference}/contract?language=en`" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-slate-950 px-3.5 text-[11px] font-semibold text-white">English contract</a>
                            <a :href="`/admin/installments/${plan.reference}/contract?language=sw`" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3.5 text-[11px] font-semibold text-slate-700 hover:border-prinx-300 hover:text-prinx-600">Swahili contract</a>
                        </div>
                        <p class="mt-2 max-w-xl text-[10px] leading-4 text-slate-500">The generated page includes the customer name and phone, selected product(s), agreed price, full-payment-before-release statement, alternative-item option, both signatures and an office stamp area. Use Print / Save PDF from the contract page.</p>
                    </div>

                    <div class="rounded-[7px] bg-slate-50 p-3">
                        <div v-if="plan.signed_contract_original_name" class="mb-3 flex items-start justify-between gap-3 rounded-[6px] border border-emerald-100 bg-emerald-50 px-3 py-2.5">
                            <div class="min-w-0"><strong class="block text-[11px] font-semibold text-emerald-800">Signed copy on file</strong><p class="mt-0.5 truncate text-[10px] text-emerald-700">{{ plan.signed_contract_original_name }}</p><p class="mt-1 text-[9px] text-emerald-600">Uploaded {{ dateTime(plan.signed_contract_uploaded_at) }}</p></div>
                            <a :href="`/admin/installments/${plan.reference}/contract/signed`" target="_blank" rel="noopener" class="shrink-0 text-[10px] font-semibold text-emerald-700">View</a>
                        </div>
                        <form class="space-y-2" @submit.prevent="uploadSignedContract">
                            <label class="block"><span class="mb-1 block text-[10px] font-semibold text-slate-600">{{ plan.signed_contract_original_name ? 'Replace signed contract' : 'Upload signed contract' }}</span><input type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-[10px] text-slate-600 file:mr-2 file:rounded-[5px] file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-[10px] file:font-semibold" required @change="signedContractForm.signed_contract = $event.target.files?.[0] || null"></label>
                            <p class="text-[9px] leading-4 text-slate-400">PDF, JPG or PNG. Maximum 10 MB. The file is kept in private application storage.</p>
                            <p v-if="signedContractForm.errors.signed_contract" class="text-[10px] text-red-600">{{ signedContractForm.errors.signed_contract }}</p>
                            <button class="h-9 rounded-[6px] bg-prinx-500 px-3.5 text-[11px] font-semibold text-white disabled:bg-slate-300" :disabled="signedContractForm.processing || !signedContractForm.signed_contract">{{ signedContractForm.processing ? 'Uploading…' : (plan.signed_contract_original_name ? 'Replace signed copy' : 'Save signed copy') }}</button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white"><div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Installment products</h2><p class="mt-1 text-[11px] text-slate-500">{{ stockReserved ? `These quantities are reserved and no longer part of sellable stock.` : `These products are part of the agreement but remain sellable until payments reach ${reservationPercent}%.` }}</p></div><div class="overflow-x-auto"><table class="min-w-[650px] w-full text-left text-[11px]"><thead class="bg-slate-50 text-[9px] uppercase text-slate-500"><tr><th class="px-4 py-2.5">Product</th><th class="px-4 py-2.5">SKU</th><th class="px-4 py-2.5">Qty</th><th class="px-4 py-2.5">Unit price</th><th class="px-4 py-2.5 text-right">Line total</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in items" :key="item.id"><td class="px-4 py-3 font-medium text-slate-900">{{ item.product_name }}</td><td class="px-4 py-3 text-slate-500">{{ item.sku || '—' }}</td><td class="px-4 py-3">{{ item.quantity }}</td><td class="px-4 py-3">{{ money(item.unit_price) }}</td><td class="px-4 py-3 text-right font-semibold">{{ money(item.line_total) }}</td></tr></tbody></table></div></section>

            <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white"><div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Payment history</h2><p class="mt-1 text-[11px] text-slate-500">Every received payment keeps its own receipt reference and SMS delivery state.</p></div><div v-if="payments.length" class="overflow-x-auto"><table class="min-w-[850px] w-full text-left text-[11px]"><thead class="bg-slate-50 text-[9px] uppercase text-slate-500"><tr><th class="px-4 py-2.5">Receipt</th><th class="px-4 py-2.5">Date</th><th class="px-4 py-2.5">Method</th><th class="px-4 py-2.5">External ref</th><th class="px-4 py-2.5">Amount</th><th class="px-4 py-2.5">SMS</th><th class="px-4 py-2.5"></th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="payment in payments" :key="payment.id"><td class="px-4 py-3 font-semibold text-slate-900">{{ payment.reference }}</td><td class="px-4 py-3 text-slate-500">{{ dateTime(payment.created_at) }}</td><td class="px-4 py-3">{{ payment.payment_method_name }}</td><td class="px-4 py-3 text-slate-500">{{ payment.external_reference || '—' }}</td><td class="px-4 py-3 font-semibold text-emerald-700">{{ money(payment.amount) }}</td><td class="px-4 py-3"><span :class="['rounded-full px-2 py-1 text-[9px] font-semibold capitalize', payment.sms_status === 'sent' ? 'bg-emerald-50 text-emerald-700' : payment.sms_status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700']">{{ payment.sms_status }}</span></td><td class="px-4 py-3 text-right"><button v-if="payment.sms_status !== 'sent'" type="button" class="text-[10px] font-semibold text-prinx-600" @click="resendSms(payment)">Resend SMS</button></td></tr></tbody></table></div><div v-else class="px-4 py-8 text-center text-xs text-slate-500">No payments have been received yet.</div></section>

            <div v-if="plan.status === 'active' && Number(plan.paid_amount || 0) === 0" class="flex justify-end"><button type="button" class="h-9 rounded-[6px] border border-red-200 bg-white px-4 text-[11px] font-semibold text-red-600 hover:bg-red-50" @click="cancelPlan">{{ stockReserved ? 'Cancel plan & return stock' : 'Cancel plan' }}</button></div>
        </div>
    </AdminLayout>
</template>
