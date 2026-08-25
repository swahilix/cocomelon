<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import CustomerAccountNav from '../../../Components/CustomerAccountNav.vue';

const props = defineProps({ plans: { type: Array, default: () => [] } });
const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const date = (value) => value ? new Date(value).toLocaleDateString('en-TZ', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statusClass = (status) => ({ active: 'bg-amber-50 text-amber-700', paid: 'bg-emerald-50 text-emerald-700', released: 'bg-sky-50 text-sky-700', cancelled: 'bg-slate-100 text-slate-500' }[status] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <AppLayout>
        <Head title="My Installments"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-5 sm:py-7">
            <div class="container-shell">
                <nav class="flex items-center gap-2 text-xs font-medium text-slate-500"><Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/account" class="hover:text-prinx-600">My Account</Link><span>/</span><span class="text-prinx-600">Installments</span></nav>
                <div class="mt-3"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Pay gradually</p><h1 class="mt-1 text-[26px] font-semibold tracking-[-.025em] text-slate-950 sm:text-[30px]">My Installment Plans</h1><p class="mt-1 text-xs text-slate-500">Follow every payment, remaining balance and the release status of your reserved items.</p></div>
                <div class="mt-5"><CustomerAccountNav /></div>

                <div v-if="plans.length" class="mt-4 grid gap-3 lg:grid-cols-2">
                    <Link v-for="plan in plans" :key="plan.id" :href="`/account/installments/${plan.reference}`" class="group rounded-[9px] border border-slate-200 bg-white p-4 transition hover:border-prinx-200">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">{{ plan.reference }}</p><h2 class="mt-1 text-sm font-semibold text-slate-950">Reserved purchase</h2><p class="mt-1 text-[11px] text-slate-500">Started {{ date(plan.created_at) }}</p></div><span :class="['rounded-full px-2.5 py-1 text-[9px] font-semibold capitalize', statusClass(plan.status)]">{{ plan.status }}</span></div>
                        <div class="mt-4 grid grid-cols-3 gap-2"><div class="rounded-[7px] bg-slate-50 p-2.5"><span class="block text-[9px] text-slate-400">Net total</span><strong class="mt-1 block text-[11px] text-slate-900">{{ money(plan.net_total) }}</strong></div><div class="rounded-[7px] bg-slate-50 p-2.5"><span class="block text-[9px] text-slate-400">Paid</span><strong class="mt-1 block text-[11px] text-emerald-700">{{ money(plan.paid_amount) }}</strong></div><div class="rounded-[7px] bg-slate-950 p-2.5 text-white"><span class="block text-[9px] text-slate-400">Remaining</span><strong class="mt-1 block text-[11px]">{{ money(plan.remaining_amount) }}</strong></div></div>
                        <div class="mt-3"><div class="mb-1 flex justify-between text-[9px] text-slate-500"><span>{{ plan.progress }}% paid</span><span class="font-medium text-prinx-600 group-hover:underline">View details →</span></div><div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-prinx-500" :style="{ width: `${plan.progress}%` }"></div></div></div>
                    </Link>
                </div>

                <div v-else class="mt-4 rounded-[9px] border border-slate-200 bg-white px-5 py-12 text-center"><div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-prinx-50 text-prinx-600">₮</div><h2 class="mt-3 text-sm font-semibold text-slate-800">No installment plans yet</h2><p class="mx-auto mt-1 max-w-md text-xs leading-5 text-slate-500">When a Prinx installment purchase is opened for your phone number, it will appear here automatically.</p><Link href="/pages/contact" class="mt-4 inline-flex h-9 items-center rounded-[6px] bg-prinx-500 px-4 text-xs font-semibold text-white">Contact Prinx</Link></div>
            </div>
        </section>
    </AppLayout>
</template>
