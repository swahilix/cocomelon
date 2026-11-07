<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Flash from '../Components/Flash.vue';
import Icon from '../Components/Icon.vue';
import { useAdminSelect2 } from '../lib/useAdminSelect2';
import { adminSettings, adminTools, canAccess, urlInHub } from '../lib/adminHubs';

const open = ref(false);
const page = usePage();
const granted = computed(() => page.props.auth.permissions || []);
const canAny = (permissions = []) => !permissions.length || permissions.some((permission) => granted.value.includes(permission));
// A hub link is shown when the user can open at least one page inside it.
const hubPermissions = (items) => (items.some((item) => canAccess(granted.value, item.permissions)) ? [] : ['__none__']);

useAdminSelect2('.admin-shell');

const sections = computed(() => [
    {
        label: 'Store management',
        items: [
            ['Dashboard', '/admin', 'home-2', ['manage_storefront']],
            ['Products', '/admin/products', 'box', ['manage_storefront']],
            ['Add stock', '/admin/stock/entry', 'box-add', ['adjust_stock', 'manage_purchases']],
            ['Purchases', '/admin/purchases', 'shopping-cart', ['manage_purchases', 'view_costs']],
            ['Purchase orders', '/admin/purchase-orders', 'document-text-2', ['manage_purchases']],
            ['Stock management', '/admin/stock', 'shop', ['view_inventory', 'manage_purchases']],
            ['Inventory', '/admin/stock/inventory', 'box', ['view_inventory']],
            ['Barcode printing', '/admin/barcodes', 'box', ['view_inventory', 'manage_storefront']],
            ['Suppliers', '/admin/suppliers', 'truck', ['manage_suppliers']],
            ['Categories & brands', '/admin/catalog', 'shop', ['manage_storefront']],
            ['Coupons', '/admin/coupons', 'percentage-circle', ['manage_storefront']],
            ['Tools', '/admin/tools', 'category', hubPermissions(adminTools)],
        ].filter((item) => canAny(item[3])),
    },
    {
        label: 'Sales & customers',
        items: [
            ['Point of sale', '/admin/pos', 'shop', ['use_pos']],
            ['Customers', '/admin/customers', 'user-1', ['use_pos', 'manage_orders']],
            ['Installments', '/admin/installments', 'money-add', ['use_pos', 'manage_orders']],
            ['Warranties', '/admin/warranties', 'shield-tick', ['use_pos', 'manage_orders']],
            ['Cash registers', '/admin/registers', 'money-add', ['manage_registers']],
            ['Sales', '/admin/sales', 'basket-1', ['use_pos', 'view_reports']],
            ['Record past sale', '/admin/sales/past', 'calendar-add', ['use_pos']],
            ['Expenses', '/admin/expenses', 'bulb', ['manage_expenses']],
            ['Profit & costing', '/admin/profit', 'percentage-circle', ['view_profit_reports']],
            ['Reports', '/admin/reports', 'document-text-2', ['view_reports']],
            ['Conversion report', '/admin/conversions', 'chart', ['manage_storefront']],
            ['Orders', '/admin/orders', 'shopping-cart', ['manage_orders']],
            ['Service requests', '/admin/inquiries', 'box-square', ['manage_inquiries']],
        ].filter((item) => canAny(item[3])),
    },
    {
        label: 'Channels',
        items: [['Google Merchant', '/admin/merchant', 'shop', ['manage_merchant']]].filter((item) => canAny(item[3])),
    },
    {
        label: 'System',
        items: [
            ['My account', '/admin/account', 'user-1', []],
            ['Settings', '/admin/settings', 'setting-2', hubPermissions(adminSettings)],
            ['Staff access', '/admin/access', 'shield-user', ['manage_staff']],
            ['Audit trail', '/admin/audit', 'hierarchy-2', ['manage_staff']],
            ['System monitoring', '/admin/system-monitor', 'monitor', ['view_system_monitor']],
        ].filter((item) => canAny(item[3])),
    },
].filter((section) => section.items.length));

const active = (url) => {
    if (url === '/admin/tools') return page.url.startsWith('/admin/tools') || urlInHub(page.url, adminTools);
    if (url === '/admin/settings') return page.url.startsWith('/admin/settings') || urlInHub(page.url, adminSettings);
    if (url === '/admin') return page.url === '/admin';
    if (url === '/admin/stock') return page.url === '/admin/stock';
    if (url === '/admin/sales') return page.url === '/admin/sales' || page.url.startsWith('/admin/sales?') || page.url.startsWith('/admin/sales/pos') || page.url.startsWith('/admin/sales/online');
    if (url === '/admin/purchases') return page.url.startsWith('/admin/purchases') || page.url.startsWith('/admin/stock/purchases/');
    if (url === '/admin/business-settings') return page.url === '/admin/business-settings';
    return page.url.startsWith(url);
};
const closeMenu = () => { open.value = false; };

// Desktop sidebar collapse. Saved in this browser so it stays the same after
// a refresh or page change until it is toggled again (admin pages render in
// the browser only, so reading it here causes no flash).
const SIDEBAR_KEY = 'prinx.admin.sidebar.collapsed';
const readCollapsed = () => {
    try {
        return typeof window !== 'undefined' && window.localStorage.getItem(SIDEBAR_KEY) === '1';
    } catch {
        return false;
    }
};
const collapsed = ref(readCollapsed());
const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
    try {
        window.localStorage.setItem(SIDEBAR_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Storage blocked (private mode): the choice lasts until the next page load.
    }
};
// Keep other open admin tabs in step.
const syncSidebar = (event) => {
    if (event.key === SIDEBAR_KEY) collapsed.value = event.newValue === '1';
};
onMounted(() => window.addEventListener('storage', syncSidebar));
onBeforeUnmount(() => window.removeEventListener('storage', syncSidebar));
const logout = () => router.post('/logout');
</script>

<template>
    <div class="admin-shell min-h-screen bg-[#f5f6f8]">
        <Head><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <Flash />
        <div v-if="open" class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden" @click="closeMenu"></div>
        <aside id="admin-sidebar" :class="['fixed inset-y-0 left-0 z-50 flex w-[82vw] max-w-64 flex-col border-r border-slate-800 bg-slate-950 transition-[transform,width] duration-300 lg:max-w-none lg:translate-x-0', collapsed ? 'lg:w-16' : 'lg:w-60', open ? 'translate-x-0' : '-translate-x-full']">
            <div :class="['flex h-[60px] items-center justify-between border-b border-slate-800 px-3.5 sm:h-[62px] sm:px-4', collapsed ? 'lg:justify-center lg:px-0' : '']">
                <Link href="/admin" :class="collapsed ? 'lg:hidden' : ''" @click="closeMenu"><img src="/assets/prinx-logo.png" class="h-8 w-auto brightness-0 invert sm:h-9" alt="Prinx Tech"></Link>
                <Link v-if="collapsed" href="/admin" class="hidden h-9 w-9 items-center justify-center rounded-[6px] bg-prinx-500 text-[15px] font-bold text-white lg:flex" title="Dashboard" aria-label="Prinx Tech dashboard">P</Link>
                <button class="flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-700 text-white lg:hidden" aria-label="Close navigation" @click="closeMenu"><Icon name="x-circle" :size="18" /></button>
            </div>
            <div :class="['scrollbar-hidden flex-1 overflow-y-auto p-2.5 sm:p-3', collapsed ? 'lg:px-2' : '']">
                <div v-for="(section, sectionIndex) in sections" :key="section.label" class="mb-4">
                    <p :class="['mb-1.5 px-2.5 text-[9px] font-medium uppercase tracking-[.16em] text-slate-500', collapsed ? 'lg:hidden' : '']">{{ section.label }}</p>
                    <div v-if="collapsed && sectionIndex > 0" class="mx-2 mb-2 hidden border-t border-slate-800 lg:block" aria-hidden="true"></div>
                    <nav class="space-y-0.5" :aria-label="section.label">
                        <Link
                            v-for="item in section.items"
                            :key="item[1]"
                            :href="item[1]"
                            :title="collapsed ? item[0] : undefined"
                            :aria-current="active(item[1]) ? 'page' : undefined"
                            :class="['group flex items-center gap-2.5 rounded-[8px] px-2 py-1.5 text-[13px] font-medium transition sm:py-1.5', collapsed ? 'lg:justify-center lg:px-0' : '', active(item[1]) ? 'bg-prinx-500 text-white shadow-md shadow-prinx-500/15' : 'text-slate-300 hover:bg-slate-900 hover:text-white']"
                            @click="closeMenu"
                        >
                            <span :class="['flex h-7 w-7 shrink-0 items-center justify-center rounded-[7px] transition', active(item[1]) ? 'bg-white/15 text-white' : 'bg-slate-900 text-slate-400 group-hover:bg-slate-800 group-hover:text-prinx-400']"><Icon :name="item[2]" :size="16" /></span>
                            <span :class="['min-w-0 truncate', collapsed ? 'lg:sr-only' : '']">{{ item[0] }}</span>
                        </Link>
                    </nav>
                </div>
            </div>
            <div :class="['border-t border-slate-800 p-2.5 pb-[max(.625rem,env(safe-area-inset-bottom))] sm:p-3', collapsed ? 'lg:px-2' : '']">
                <Link href="/" :title="collapsed ? 'View public store' : undefined" :class="['flex min-h-9 w-full items-center justify-center gap-2 rounded-[8px] border border-slate-700 bg-slate-900 px-3 py-1.5 text-[13px] font-medium text-white transition hover:border-prinx-400', collapsed ? 'lg:px-0' : '']"><Icon name="export-1" :size="15" /><span :class="collapsed ? 'lg:sr-only' : ''">View public store</span></Link>
                <button :title="collapsed ? 'Sign out' : undefined" :class="['mt-1.5 flex min-h-9 w-full items-center justify-center gap-2 rounded-[8px] px-3 py-1.5 text-[13px] font-medium text-slate-400 transition hover:bg-slate-900 hover:text-white', collapsed ? 'lg:px-0' : '']" @click="logout"><Icon name="logout" :size="15" /><span :class="collapsed ? 'lg:sr-only' : ''">Sign out</span></button>
            </div>
        </aside>
        <div :class="['admin-content transition-[padding] duration-300', collapsed ? 'lg:pl-16' : 'lg:pl-60']">
            <header class="sticky top-0 z-30 flex h-[60px] items-center justify-between border-b border-slate-200 bg-white/95 px-3 shadow-sm backdrop-blur sm:h-[62px] sm:px-4 lg:px-5"><div class="flex min-w-0 items-center gap-2.5"><button class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] border border-slate-200 bg-white lg:hidden" aria-label="Open navigation" @click="open=true"><Icon name="hamburger-menu" :size="20" /></button><button type="button" class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 lg:flex" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" aria-controls="admin-sidebar" :aria-expanded="!collapsed" @click="toggleSidebar"><svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2" /><path d="M9.5 4.5v15" /><path v-if="collapsed" d="m13.5 10 2 2-2 2" /><path v-else d="m16 10-2 2 2 2" /></svg></button><div class="min-w-0"><p class="text-[8px] font-medium uppercase tracking-[.14em] text-prinx-600 sm:text-[9px]">Administration</p><p class="mt-0.5 truncate text-[13px] font-semibold text-slate-950 sm:text-sm">Store management panel</p></div></div><div class="flex shrink-0 items-center gap-2"><div class="hidden text-right sm:block"><p class="text-[13px] font-semibold text-slate-900">{{ page.props.auth.user?.name }}</p><p class="text-[11px] font-medium text-slate-500">{{ page.props.auth.role?.name || 'Staff' }}</p></div><div class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-slate-950 text-xs font-semibold text-white sm:h-9 sm:w-9">{{ page.props.auth.user?.name?.charAt(0) || 'A' }}</div></div></header>
            <main class="p-2.5 sm:p-4 lg:p-5"><slot /></main>
        </div>
    </div>
</template>
