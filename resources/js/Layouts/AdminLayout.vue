<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Flash from '../Components/Flash.vue';

const open = ref(false);
const page = usePage();
const sections = [
    {
        label: 'Store management',
        items: [
            ['Dashboard', '/admin', 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-5H4v5Zm10-11h6V4h-6v5Z'],
            ['Products', '/admin/products', 'M5 7h14l-1 13H6L5 7Zm3 0a4 4 0 0 1 8 0'],
            ['Services', '/admin/services', 'M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-3 3-3-1-1-3 3-3Z'],
            ['Homepage carousel', '/admin/carousel', 'M4 6h16v12H4V6Zm3 8 3-3 2 2 3-4 2 3'],
            ['Product reels', '/admin/reels', 'M7 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Zm3 5 5 3-5 3V9Z'],
            ['Categories & brands', '/admin/catalog', 'M4 5h16v4H4V5Zm0 6h7v8H4v-8Zm9 0h7v8h-7v-8Z'],
        ],
    },
    {
        label: 'Sales & customers',
        items: [
            ['Orders', '/admin/orders', 'M6 3h12l2 4v14H4V7l2-4Zm-2 4h16M9 11h6'],
            ['Service requests', '/admin/inquiries', 'M4 4h16v12H7l-3 3V4Zm4 4h8M8 12h5'],
        ],
    },
    {
        label: 'Channels',
        items: [
            ['Google Merchant', '/admin/merchant', 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 4v12M6 12h12'],
        ],
    },
];
const active = (url) => url === '/admin' ? page.url === '/admin' : page.url.startsWith(url);
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
                <button class="flex h-9 w-9 items-center justify-center rounded-[9px] border border-slate-700 text-white lg:hidden" @click="open=false">✕</button>
            </div>

            <div class="scrollbar-hidden flex-1 overflow-y-auto p-4">
                <div class="mb-5 rounded-[10px] border border-slate-800 bg-slate-900 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-prinx-400">Store control</p>
                    <p class="mt-2 text-sm font-black text-white">Prinx Electronics</p>
                    <p class="mt-1 text-xs leading-5 text-slate-400">Manage products, services, categories and sales.</p>
                </div>

                <div v-for="section in sections" :key="section.label" class="mb-5">
                    <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[.18em] text-slate-500">{{ section.label }}</p>
                    <nav class="space-y-1">
                        <Link v-for="item in section.items" :key="item[1]" :href="item[1]" :class="['group flex items-center gap-3 rounded-[10px] px-3 py-3 text-sm font-extrabold transition', active(item[1]) ? 'bg-prinx-500 text-white shadow-lg shadow-prinx-500/20' : 'text-slate-300 hover:bg-slate-900 hover:text-white']">
                            <svg viewBox="0 0 24 24" :class="['h-5 w-5 fill-none stroke-current', active(item[1]) ? 'text-white' : 'text-slate-500 group-hover:text-prinx-400']" stroke-width="2"><path :d="item[2]"/></svg>
                            {{ item[0] }}
                        </Link>
                    </nav>
                </div>
            </div>

            <div class="border-t border-slate-800 p-4">
                <Link href="/" class="flex w-full items-center justify-center gap-2 rounded-[10px] border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm font-extrabold text-white transition hover:border-prinx-400">View public store ↗</Link>
                <button class="mt-2 w-full rounded-[10px] px-4 py-2 text-sm font-bold text-slate-400 hover:bg-slate-900 hover:text-white" @click="logout">Sign out</button>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 flex h-[76px] items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button class="flex h-10 w-10 items-center justify-center rounded-[9px] border border-slate-200 bg-white lg:hidden" @click="open=true">☰</button>
                    <div><p class="text-[10px] font-black uppercase tracking-[.15em] text-prinx-600">Administration</p><p class="mt-0.5 font-black text-slate-950">Store management panel</p></div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block"><p class="text-sm font-black text-slate-900">{{ page.props.auth.user?.name }}</p><p class="text-xs font-semibold text-slate-500">Administrator</p></div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-slate-950 text-sm font-black text-white">{{ page.props.auth.user?.name?.charAt(0) || 'A' }}</div>
                </div>
            </header>
            <main class="p-4 sm:p-6 lg:p-8"><slot /></main>
        </div>
    </div>
</template>
