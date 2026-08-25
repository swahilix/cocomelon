<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const page = usePage();
const items = [
    { label: 'Track Orders', text: 'Follow order progress and delivery status', href: '/account/orders', icon: 'shopping-cart' },
    { label: 'Payment Records', text: 'Review your online and store payments', href: '/account/payments', icon: 'bank-card' },
    { label: 'Invoices', text: 'View your order and POS invoices', href: '/account/invoices', icon: 'document-text-2' },
];
const active = (href) => page.url === href || page.url.startsWith(`${href}/`);
const logout = () => router.post('/logout');
const phone = () => page.props.auth.user?.phone ? `+255 ${page.props.auth.user.phone}` : 'Customer account';
</script>

<template>
    <section class="rounded-[9px] border border-slate-200 bg-white p-3 sm:p-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">My Prinx</p>
                <p class="mt-1 text-sm font-semibold text-slate-950">{{ phone() }}</p>
            </div>
            <button type="button" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:border-red-200 hover:text-red-600" @click="logout">
                <Icon name="logout" :size="14" /> Sign out
            </button>
        </div>

        <div class="mt-3 grid gap-2 sm:grid-cols-3">
            <Link v-for="item in items" :key="item.href" :href="item.href" :class="['group flex min-h-[82px] items-start gap-3 rounded-[8px] border p-3 transition', active(item.href) ? 'border-prinx-300 bg-prinx-50' : 'border-slate-200 bg-white hover:border-prinx-200']">
                <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-[7px]', active(item.href) ? 'bg-prinx-500 text-white' : 'bg-slate-50 text-slate-600 group-hover:text-prinx-600']"><Icon :name="item.icon" :size="17" /></span>
                <span class="min-w-0"><strong class="block text-xs font-semibold text-slate-900">{{ item.label }}</strong><span class="mt-1 block text-[11px] leading-4 text-slate-500">{{ item.text }}</span></span>
            </Link>
        </div>
    </section>
</template>
