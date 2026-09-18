<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({ guides: Object, status: String, counts: Object });

const remove = (guide) => {
    if (confirm(`Remove “${guide.title}” from the content library?`)) {
        router.delete(`/admin/guides/${guide.slug}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AdminLayout>
        <Head title="Buying Guides" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="section-kicker">SEO content</p><h1 class="section-title">Buying Guides</h1><p class="mt-2 text-sm text-slate-500">Publish useful product research content that links customers into your catalogue.</p></div>
            <Link href="/admin/guides/create" class="btn-primary">Create buying guide</Link>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <Link v-for="item in [['','All',counts.all],['published','Published',counts.published],['draft','Drafts',counts.draft]]" :key="item[1]" :href="item[0] ? `/admin/guides?status=${item[0]}` : '/admin/guides'" :class="['tab-button border', (status || '') === item[0] ? 'border-prinx-500 bg-prinx-500 text-white' : 'border-slate-200 bg-white text-slate-600']">{{ item[1] }} <span class="ml-1 opacity-70">{{ item[2] }}</span></Link>
        </div>

        <div class="mt-5 space-y-3">
            <article v-for="guide in guides.data" :key="guide.id" class="card p-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2"><strong class="text-[13px] font-semibold text-slate-900">{{ guide.title }}</strong><span class="rounded-[5px] bg-slate-100 px-2 py-1 text-[9px] font-semibold capitalize text-slate-600">{{ guide.status }}</span><span v-if="guide.featured" class="rounded-[5px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">Featured</span></div>
                        <p class="mt-1 text-[10px] text-slate-500">{{ guide.category?.name || 'General guide' }} · /guides/{{ guide.slug }}</p>
                        <p class="mt-2 line-clamp-2 text-[11px] leading-5 text-slate-600">{{ guide.excerpt || guide.body }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2"><a v-if="guide.status === 'published'" :href="`/guides/${guide.slug}`" target="_blank" class="btn-light">View</a><Link :href="`/admin/guides/${guide.slug}/edit`" class="btn-light">Edit</Link><button type="button" class="btn-danger" @click="remove(guide)">Delete</button></div>
                </div>
            </article>
            <div v-if="!guides.data?.length" class="card py-14 text-center text-sm text-slate-400">No buying guides in this section.</div>
        </div>

        <div v-if="guides.links?.length > 3" class="mt-5 flex flex-wrap gap-2"><Link v-for="link in guides.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded-[6px] border px-3 py-2 text-[11px]', link.active ? 'border-prinx-500 bg-prinx-500 text-white' : 'border-slate-200 bg-white text-slate-600', !link.url ? 'pointer-events-none opacity-40' : '']" /></div>
    </AdminLayout>
</template>
