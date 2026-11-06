<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Flash from '../Components/Flash.vue';
import Icon from '../Components/Icon.vue';
import { useAdminSelect2 } from '../lib/useAdminSelect2';

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const canViewInventory = computed(() => permissions.value.includes('view_inventory') || permissions.value.includes('manage_purchases'));

useAdminSelect2('.pos-shell');

// Highlight the section that is open.
const path = computed(() => String(page.url || '').split('?')[0]);
const navItems = computed(() => [
    { href: '/admin/sales', label: 'Sales', icon: 'receipt-item', active: path.value.startsWith('/admin/sales') },
    { href: '/admin/pos', label: 'New sale', icon: 'shopping-cart', active: path.value === '/admin/pos' || path.value.startsWith('/admin/pos/receipt') },
    { href: '/admin/pos/documents', label: 'Documents', icon: 'document-text', active: path.value.startsWith('/admin/pos/documents') },
    ...(canViewInventory.value ? [{ href: '/admin/stock/inventory', label: 'Items', icon: 'box', active: path.value.startsWith('/admin/stock/inventory') }] : []),
]);

const logout = () => router.post('/logout');
const toggleFullscreen = async () => {
    try {
        if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
        else await document.exitFullscreen();
    } catch (_) {}
};
</script>

<template>
    <div class="pos-shell min-h-screen bg-[#eef1f4] text-slate-900">
        <Head><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <Flash />

        <header class="sticky top-0 z-50 h-[58px] border-b border-slate-800 bg-slate-950 text-white shadow-sm">
            <div class="mx-auto flex h-full w-full max-w-[1800px] items-center px-2.5 sm:px-4">
                <Link href="/admin" class="flex shrink-0 items-center gap-2" title="Back to dashboard">
                    <img src="/assets/prinx-logo.png" class="h-8 w-auto brightness-0 invert" alt="Prinx Electronics">
                    <span class="hidden text-sm font-semibold md:inline">Prinx POS</span>
                </Link>

                <nav class="ml-4 hidden items-center gap-1 lg:flex">
                    <Link v-for="item in navItems" :key="item.href" :href="item.href" :aria-current="item.active ? 'page' : undefined" :class="['rounded-[7px] px-3 py-2 text-xs transition', item.active ? 'bg-white/10 font-semibold text-white' : 'font-medium text-slate-300 hover:bg-slate-900 hover:text-white']"><Icon :name="item.icon" :size="15" class="mr-1.5 inline" />{{ item.label }}</Link>
                </nav>

                <div class="ml-auto flex items-center gap-1.5">
                    <slot name="header-actions" />
                    <Link href="/admin/registers" class="hidden h-9 items-center gap-1.5 rounded-[7px] px-2.5 text-xs font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white sm:flex" title="Cash register"><Icon name="wallet" :size="16" />Register</Link>
                    <button type="button" class="hidden h-9 w-9 items-center justify-center rounded-[7px] text-slate-300 transition hover:bg-slate-900 hover:text-white md:flex" title="Toggle fullscreen" @click="toggleFullscreen"><Icon name="monitor" :size="17" /></button>
                    <Link href="/admin" class="flex h-9 w-9 items-center justify-center rounded-[7px] text-slate-300 transition hover:bg-slate-900 hover:text-white" title="Dashboard"><Icon name="home-2" :size="17" /></Link>
                    <div class="ml-1 hidden text-right xl:block"><p class="text-xs font-semibold text-white">{{ page.props.auth.user?.name }}</p><p class="text-[10px] text-slate-400">{{ page.props.auth.role?.name || 'Staff' }}</p></div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-[7px] bg-prinx-500 text-xs font-semibold text-white">{{ page.props.auth.user?.name?.charAt(0) || 'A' }}</div>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-[7px] text-slate-400 transition hover:bg-slate-900 hover:text-white" title="Sign out" @click="logout"><Icon name="logout" :size="16" /></button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1800px] p-2 sm:p-3">
            <slot />
        </main>
    </div>
</template>
