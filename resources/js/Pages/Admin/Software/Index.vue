<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ solutions: Object, filters: Object, counts: Object });
const trash = computed(() => props.filters.view === 'trash');
const form = reactive({ search: props.filters.search || '', status: props.filters.status || '', view: props.filters.view || '' });
const apply = () => router.get('/admin/software', form, { preserveState: true, replace: true });
const switchView = (view) => router.get('/admin/software', { view: view || undefined });
const destroy = (solution) => confirm(`Delete “${solution.name}”? It will be moved to Trash.`) && router.delete(`/admin/software/${solution.slug}`);
const restore = (solution) => router.post(`/admin/software/${solution.id}/restore`, {}, { preserveScroll: true });
const statusBadge = (status) => status === 'active' ? 'bg-emerald-100 text-emerald-700' : status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600';
</script>

<template>
    <AdminLayout>
        <Head title="Software Solutions" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Software catalogue</p>
                <h1 class="section-title">Software Solutions</h1>
                <p class="mt-2 text-sm text-slate-500">Manage the software systems shown on the public Software page.</p>
            </div>
            <Link href="/admin/software/create" class="btn-primary"><Icon name="add-circle" :size="17" /> Add software</Link>
        </div>

        <div class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.15em] text-slate-400">Total software</p><b class="mt-2 block text-3xl font-black">{{ counts.active }}</b><p class="mt-1 text-xs text-slate-500">Available in management</p></div>
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.15em] text-emerald-600">Published</p><b class="mt-2 block text-3xl font-black">{{ counts.published }}</b><p class="mt-1 text-xs text-slate-500">Visible on the public website</p></div>
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.15em] text-amber-600">Drafts</p><b class="mt-2 block text-3xl font-black">{{ counts.drafts }}</b><p class="mt-1 text-xs text-slate-500">Hidden until published</p></div>
            <button class="metric-card text-left transition hover:border-red-200" @click="switchView('trash')"><p class="text-[10px] font-black uppercase tracking-[.15em] text-red-500">Trash</p><b class="mt-2 block text-3xl font-black">{{ counts.trash }}</b><p class="mt-1 text-xs text-slate-500">Restorable</p></button>
        </div>

        <div class="mt-6 flex flex-wrap gap-2 rounded-[10px] border border-slate-200 bg-white p-2">
            <button :class="['tab-button', !trash ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100']" @click="switchView('')">Current software <span class="rounded-[10px] bg-white/15 px-2 py-0.5 text-[10px]">{{ counts.active }}</span></button>
            <button :class="['tab-button', trash ? 'bg-red-600 text-white' : 'text-slate-600 hover:bg-red-50 hover:text-red-600']" @click="switchView('trash')">Trash <span class="rounded-[10px] bg-white/15 px-2 py-0.5 text-[10px]">{{ counts.trash }}</span></button>
        </div>

        <form class="card mt-5 grid gap-3 p-4 md:grid-cols-[1fr_220px_auto]" @submit.prevent="apply">
            <div class="relative"><Icon name="search-normal" :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="form.search" class="input pl-9" placeholder="Search software solutions"></div>
            <select v-if="!trash" v-model="form.status" class="input"><option value="">All statuses</option><option value="active">Active</option><option value="draft">Draft</option><option value="archived">Archived</option></select>
            <input type="hidden" v-model="form.view">
            <button class="btn-primary">Apply filters</button>
        </form>

        <div class="card mt-6 overflow-x-auto">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div><h2 class="font-black">{{ trash ? 'Deleted software' : 'Software catalogue' }}</h2><p class="mt-1 text-xs text-slate-500">{{ trash ? 'Deleted solutions can be restored.' : `${solutions.total} solutions match the current filters.` }}</p></div>
                <span v-if="trash" class="status bg-red-50 text-red-600">Soft delete only</span>
            </div>
            <table class="admin-table">
                <thead><tr><th>Software</th><th v-if="!trash">Status</th><th v-if="!trash">Featured</th><th>Order</th><th v-if="trash">Deleted</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="solution in solutions.data" :key="solution.id">
                        <td><div class="flex min-w-72 items-center gap-3"><div class="flex h-14 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50"><img v-if="solution.image_url" :src="solution.image_url" :alt="solution.name" class="h-full w-full object-cover"><Icon v-else name="monitor" :size="24" class="text-prinx-500" /></div><div><Link v-if="!trash" :href="`/admin/software/${solution.slug}/edit`" class="font-black text-slate-900 hover:text-prinx-600">{{ solution.name }}</Link><b v-else>{{ solution.name }}</b><p class="mt-1 line-clamp-1 max-w-96 text-xs text-slate-500">{{ solution.short_description }}</p></div></div></td>
                        <td v-if="!trash"><span :class="['status', statusBadge(solution.status)]">{{ solution.status }}</span></td>
                        <td v-if="!trash"><span :class="['status', solution.featured ? 'bg-prinx-50 text-prinx-700' : 'bg-slate-100 text-slate-500']">{{ solution.featured ? 'Featured' : 'Standard' }}</span></td>
                        <td class="font-semibold text-slate-600">{{ solution.sort_order }}</td>
                        <td v-if="trash"><span class="text-xs font-semibold text-slate-500">{{ new Date(solution.deleted_at).toLocaleString() }}</span></td>
                        <td><div class="flex min-w-max justify-end gap-2"><template v-if="!trash"><Link :href="`/admin/software/${solution.slug}/edit`" class="btn-light px-3 py-2">Edit</Link><button class="btn-danger" @click="destroy(solution)">Delete</button></template><button v-else class="btn-primary px-4" @click="restore(solution)">Restore</button></div></td>
                    </tr>
                    <tr v-if="!solutions.data.length"><td :colspan="trash ? 4 : 5" class="py-20 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[10px] bg-slate-100 text-slate-500"><Icon name="monitor" :size="25" /></div><p class="mt-4 font-black text-slate-700">{{ trash ? 'Trash is empty' : 'No software found' }}</p><p class="mt-1 text-xs text-slate-400">{{ trash ? 'Deleted software will appear here.' : 'Add your first software solution.' }}</p></td></tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="solutions.links" />
    </AdminLayout>
</template>
