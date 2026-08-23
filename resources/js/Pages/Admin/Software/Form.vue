<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ software: { type: Object, default: null } });
const editing = computed(() => !!props.software);
const software = props.software || {};
const form = useForm({
    name: software.name || '',
    slug: software.slug || '',
    short_description: software.short_description || '',
    description: software.description || '',
    image: null,
    image_url: '',
    demo_url: software.demo_url || '',
    status: software.status || 'draft',
    featured: software.featured ?? false,
    sort_order: software.sort_order || 0,
    meta_title: software.meta_title || '',
    meta_description: software.meta_description || '',
    _method: editing.value ? 'patch' : 'post',
});
const submit = () => form.post(editing.value ? `/admin/software/${software.slug}` : '/admin/software', { forceFormData: true, preserveScroll: true });
</script>

<template>
    <AdminLayout>
        <Head :title="editing ? 'Edit Software' : 'Add Software'" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="section-kicker">Software catalogue</p><h1 class="section-title">{{ editing ? 'Edit software' : 'Add software' }}</h1><p class="mt-2 text-sm text-slate-500">Manage the information displayed on the public Software page.</p></div>
            <Link href="/admin/software" class="btn-light"><Icon name="arrow-left-2" :size="16" /> Back to software</Link>
        </div>

        <form class="mt-6 grid gap-6 xl:grid-cols-[1fr_340px]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Software information</h2></div>
                    <div class="grid gap-4 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label">Software name *</label><input v-model="form.name" class="input" placeholder="e.g. POS & Inventory Management System"><p class="error">{{ form.errors.name }}</p></div>
                        <div class="sm:col-span-2"><label class="label">URL slug</label><input v-model="form.slug" class="input" placeholder="Generated automatically"><p class="error">{{ form.errors.slug }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Short description</label><textarea v-model="form.short_description" rows="3" class="input" maxlength="500" placeholder="Short summary used on the software card"></textarea><p class="error">{{ form.errors.short_description }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Full description</label><textarea v-model="form.description" rows="10" class="input" placeholder="Describe the software, features, target businesses and implementation"></textarea><p class="error">{{ form.errors.description }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Demo / external link</label><input v-model="form.demo_url" type="url" class="input" placeholder="https://..."><p class="image-spec">Optional. If empty, Request Demo will open the Prinx contact page.</p><p class="error">{{ form.errors.demo_url }}</p></div>
                    </div>
                </section>

                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Software image</h2><p class="mt-1 text-xs text-slate-500">Use a clean screenshot of the dashboard or software interface.</p></div>
                    <div class="grid gap-4 p-6 sm:grid-cols-2">
                        <div><label class="label">Upload image</label><input type="file" class="input" accept="image/jpeg,image/png,image/webp" @change="form.image=$event.target.files[0] || null"><p class="image-spec">Recommended: 1600 × 900 px · PNG, JPG or WebP · max 8 MB.</p><p class="error">{{ form.errors.image }}</p></div>
                        <div><label class="label">Or image URL</label><input v-model="form.image_url" type="url" class="input" placeholder="https://..."><p class="image-spec">Remote images are displayed as supplied.</p><p class="error">{{ form.errors.image_url }}</p></div>
                        <div v-if="editing && software.image_url" class="sm:col-span-2"><p class="label">Current image</p><img :src="software.image_url" :alt="software.name" class="aspect-video w-full max-w-xl rounded-[8px] border border-slate-200 bg-slate-50 object-cover"></div>
                    </div>
                </section>

                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Search visibility</h2></div>
                    <div class="space-y-4 p-6"><div><label class="label">Meta title</label><input v-model="form.meta_title" class="input" maxlength="190"><p class="error">{{ form.errors.meta_title }}</p></div><div><label class="label">Meta description</label><textarea v-model="form.meta_description" rows="3" class="input" maxlength="180"></textarea><p class="error">{{ form.errors.meta_description }}</p></div></div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="card overflow-hidden xl:sticky xl:top-24">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4"><h2 class="font-black">Publish software</h2></div>
                    <div class="space-y-4 p-5">
                        <div><label class="label">Status</label><select v-model="form.status" class="input"><option value="draft">Draft — private</option><option value="active">Active — public</option><option value="archived">Archived — hidden</option></select><p class="error">{{ form.errors.status }}</p></div>
                        <div><label class="label">Display order</label><input v-model="form.sort_order" type="number" min="0" class="input"><p class="error">{{ form.errors.sort_order }}</p></div>
                        <label class="flex items-center justify-between rounded-[10px] border border-slate-200 p-3 text-sm font-bold"><span>Featured software</span><input v-model="form.featured" type="checkbox" class="h-4 w-4 accent-prinx-500"></label>
                        <button class="btn-primary w-full py-3" :disabled="form.processing">{{ form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create software' }}</button>
                    </div>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
