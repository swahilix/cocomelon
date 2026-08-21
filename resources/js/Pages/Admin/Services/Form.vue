<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    service: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
});
const editing = computed(() => !!props.service);
const service = props.service || {};
const form = useForm({
    category_id: service.category_id || '',
    name: service.name || '',
    slug: service.slug || '',
    short_description: service.short_description || '',
    description: service.description || '',
    price_from: service.price_from || '',
    price_label: service.price_label || '',
    duration: service.duration || '',
    image: null,
    image_url: '',
    icon: service.icon || '',
    status: service.status || 'draft',
    featured: service.featured ?? false,
    sort_order: service.sort_order || 0,
    meta_title: service.meta_title || '',
    meta_description: service.meta_description || '',
    _method: editing.value ? 'patch' : 'post',
});
const submit = () => form.post(editing.value ? `/admin/services/${service.slug}` : '/admin/services', { forceFormData: true, preserveScroll: true });
</script>

<template>
    <AdminLayout>
        <Head :title="editing ? 'Edit Service' : 'Add Service'" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="section-kicker">Service catalogue</p><h1 class="section-title">{{ editing ? 'Edit service' : 'Add service' }}</h1></div><Link href="/admin/services" class="btn-light"><Icon name="arrow-left-2" :size="16" /> Back to services</Link></div>

        <form class="mt-6 grid gap-6 xl:grid-cols-[1fr_340px]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="card overflow-hidden"><div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Service information</h2></div><div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label">Service name *</label><input v-model="form.name" class="input" placeholder="e.g. Laptop repair and upgrade"><p class="error">{{ form.errors.name }}</p></div>
                    <div><label class="label">Service category</label><select v-model="form.category_id" class="input"><option value="">Select service category</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><p class="error">{{ form.errors.category_id }}</p></div>
                    <div><label class="label">URL slug</label><input v-model="form.slug" class="input" placeholder="Generated automatically"><p class="error">{{ form.errors.slug }}</p></div>
                    <div class="sm:col-span-2"><label class="label">Short description</label><textarea v-model="form.short_description" rows="3" class="input" maxlength="500" placeholder="A concise summary for service cards and search results"></textarea><p class="error">{{ form.errors.short_description }}</p></div>
                    <div class="sm:col-span-2"><label class="label">Full description *</label><textarea v-model="form.description" rows="10" class="input" placeholder="Explain what is included, common problems handled and the assessment process"></textarea><p class="error">{{ form.errors.description }}</p></div>
                </div></section>

                <section class="card overflow-hidden"><div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Pricing and delivery</h2></div><div class="grid gap-4 p-6 sm:grid-cols-2"><div><label class="label">Starting price</label><input v-model="form.price_from" type="number" min="0" step="0.01" class="input" placeholder="30000"><p class="error">{{ form.errors.price_from }}</p></div><div><label class="label">Customer-facing price label</label><input v-model="form.price_label" class="input" placeholder="From TZS 30,000"></div><div><label class="label">Typical duration</label><input v-model="form.duration" class="input" placeholder="Same day for selected repairs"></div><div><label class="label">Icon name</label><input v-model="form.icon" class="input" placeholder="repair"></div></div></section>

                <section class="card overflow-hidden"><div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Service image</h2><p class="mt-1 text-xs text-slate-500">Uploads are compressed to WebP with descriptive filenames and Prinx metadata.</p></div><div class="grid gap-4 p-6 sm:grid-cols-2"><div><label class="label">Upload image</label><input type="file" class="input" accept="image/jpeg,image/png,image/webp" @change="form.image=$event.target.files[0]"><p class="image-spec">Recommended: 1600 × 1200 px (4:3) · max 8 MB · automatically converted to WebP</p><p class="error">{{ form.errors.image }}</p></div><div><label class="label">Or image URL</label><input v-model="form.image_url" type="url" class="input" placeholder="https://..."><p class="image-spec">Use the same 1600 × 1200 px proportion. Remote URLs are not converted.</p></div><div v-if="editing && service.image_url" class="sm:col-span-2"><p class="label">Current image</p><img :src="service.image_url" :alt="service.name" class="aspect-[4/3] w-full max-w-md rounded-[10px] border border-slate-200 bg-slate-50 object-cover"></div></div></section>

                <section class="card overflow-hidden"><div class="border-b border-slate-200 bg-slate-50 px-6 py-4"><h2 class="font-black">Search visibility</h2></div><div class="space-y-4 p-6"><div><label class="label">Meta title</label><input v-model="form.meta_title" class="input" maxlength="190"></div><div><label class="label">Meta description</label><textarea v-model="form.meta_description" rows="3" class="input" maxlength="180"></textarea></div></div></section>
            </div>

            <aside class="space-y-5">
                <section class="card overflow-hidden xl:sticky xl:top-24"><div class="border-b border-slate-200 bg-slate-50 px-5 py-4"><h2 class="font-black">Publish service</h2></div><div class="space-y-4 p-5"><div><label class="label">Status</label><select v-model="form.status" class="input"><option value="draft">Draft — private</option><option value="active">Active — public</option><option value="archived">Archived — hidden</option></select></div><div><label class="label">Display order</label><input v-model="form.sort_order" type="number" min="0" class="input"></div><label class="flex items-center justify-between rounded-[10px] border border-slate-200 p-3 text-sm font-bold"><span>Feature on homepage</span><input v-model="form.featured" type="checkbox" class="h-4 w-4 accent-prinx-500"></label><button class="btn-primary w-full py-3" :disabled="form.processing">{{ form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create service' }}</button></div></section>
            </aside>
        </form>
    </AdminLayout>
</template>
