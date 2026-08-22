<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Flash from '../Components/Flash.vue';
import Icon from '../Components/Icon.vue';

const open = ref(false);
const page = usePage();
const sections = [
    {
        label: 'Store management',
        items: [
            ['Dashboard', '/admin', 'home-2'],
            ['Products', '/admin/products', 'box'],
            ['Stock management', '/admin/stock', 'shop'],
            ['Inventory', '/admin/stock/inventory', 'box'],
            ['Suppliers', '/admin/suppliers', 'profile'],
            ['Services', '/admin/services', 'setting-2'],
            ['Homepage carousel', '/admin/carousel', 'monitor'],
            ['Product reels', '/admin/reels', 'mobile'],
            ['Categories & brands', '/admin/catalog', 'shop'],
        ],
    },
    {
        label: 'Sales & customers',
        items: [
            ['Point of sale', '/admin/pos', 'shop'],
            ['Cash registers', '/admin/registers', 'wallet'],
            ['Sales', '/admin/sales', 'receipt-item'],
            ['Expenses', '/admin/expenses', 'wallet'],
            ['Profit & costing', '/admin/profit', 'wallet'],
            ['Orders', '/admin/orders', 'shopping-cart'],
            ['Service requests', '/admin/inquiries', 'sms'],
        ],
    },
    {
        label: 'Channels',
        items: [
            ['Google Merchant', '/admin/merchant', 'shop'],
        ],
    },
    {
        label: 'System',
        items: [
            ['System monitoring', '/admin/system-monitor', 'monitor'],
        ],
    },
];
const active = (url) => {
    if (url === '/admin') return page.url === '/admin';
    if (url === '/admin/stock') return page.url === '/admin/stock';
    return page.url.startsWith(url);
};
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-[#f5f6f8]">
        <Head><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <Flash />

        <div v-if="open" class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden" @click="open=false"></div>
        <aside :class="['fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-800 bg-slate-950 transition-transform duration-300 lg:translate-x-0', open ? 'translate-x-0' : '-translate-x-full']">
            <div class="flex h-[76px] items-center justify-between border-b border-slate-800 px-5">
                <Link href="/admin"><img src="/assets/prinx-logo.png" class="h-10 w-auto brightness-0 invert" alt="Prinx Electronics"></Link>
                <button class="flex h-9 w-9 items-center justify-center rounded-[9px] border border-slate-700 text-white lg:hidden" @click="open=false"><Icon name="close-circle" :size="20" /></button>
            </div>

            <div class="scrollbar-hidden flex-1 overflow-y-auto p-4">
                <div v-for="section in sections" :key="section.label" class="mb-5">
                    <p class="mb-2 px-3 text-[10px] font-medium uppercase tracking-[.18em] text-slate-500">{{ section.label }}</p>
                    <nav class="space-y-1">
                        <Link
                            v-for="item in section.items"
                            :key="item[1]"
                            :href="item[1]"
                            :class="[
                                'group flex items-center gap-3 rounded-[10px] px-2.5 py-2.5 text-sm font-medium transition',
                                active(item[1])
                                    ? 'bg-prinx-500 text-white shadow-lg shadow-prinx-500/20'
                                    : 'text-slate-300 hover:bg-slate-900 hover:text-white'
                            ]"
                        >
                            <span :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] transition',
                                active(item[1])
                                    ? 'bg-white/15 text-white'
                                    : 'bg-slate-900 text-slate-400 group-hover:bg-slate-800 group-hover:text-prinx-400'
                            ]">
                                <Icon :name="item[2]" :size="18" />
                            </span>
                            <span class="min-w-0 truncate">{{ item[0] }}</span>
                        </Link>
                    </nav>
                </div>
            </div>

            <div class="border-t border-slate-800 p-4">
                <Link href="/" class="flex w-full items-center justify-center gap-2 rounded-[10px] border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:border-prinx-400">
                    <Icon name="export-1" :size="17" />
                    View public store
                </Link>
                <button class="mt-2 flex w-full items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-slate-900 hover:text-white" @click="logout">
                    <Icon name="logout" :size="17" />
                    Sign out
                </button>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 flex h-[76px] items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button class="flex h-10 w-10 items-center justify-center rounded-[9px] border border-slate-200 bg-white lg:hidden" @click="open=true"><Icon name="menu" :size="22" /></button>
                    <div><p class="text-[10px] font-medium uppercase tracking-[.15em] text-prinx-600">Administration</p><p class="mt-0.5 font-semibold text-slate-950">Store management panel</p></div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block"><p class="text-sm font-semibold text-slate-900">{{ page.props.auth.user?.name }}</p><p class="text-xs font-medium text-slate-500">Administrator</p></div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-slate-950 text-sm font-semibold text-white">{{ page.props.auth.user?.name?.charAt(0) || 'A' }}</div>
                </div>
            </header>
            <main class="p-4 sm:p-6 lg:p-8"><slot /></main>
        </div>
    </div>
</template>
