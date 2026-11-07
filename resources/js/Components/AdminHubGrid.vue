<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from './Icon.vue';
import { canAccess } from '../lib/adminHubs';

const props = defineProps({
    items: { type: Array, required: true },
    empty: { type: String, default: 'You do not have access to any of these pages.' },
});

const page = usePage();
const visible = computed(() => {
    const granted = page.props.auth?.permissions || [];
    return props.items.filter((item) => canAccess(granted, item.permissions));
});
</script>

<template>
    <div v-if="visible.length" class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <Link
            v-for="item in visible"
            :key="item.href"
            :href="item.href"
            class="group flex items-start gap-3 rounded-[9px] border border-slate-200 bg-white p-4 transition hover:border-prinx-300 hover:shadow-sm"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-slate-950 text-white transition group-hover:bg-prinx-500"><Icon :name="item.icon" :size="19" /></span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2"><strong class="text-[13px] font-semibold text-slate-950">{{ item.label }}</strong><span class="text-[12px] text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-prinx-500">→</span></span>
                <span class="mt-1 block text-[11px] leading-[1.45] text-slate-500">{{ item.description }}</span>
            </span>
        </Link>
    </div>
    <div v-else class="rounded-[9px] border border-slate-200 bg-white px-4 py-10 text-center text-xs text-slate-500">{{ empty }}</div>
</template>
