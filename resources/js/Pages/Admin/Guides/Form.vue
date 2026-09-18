<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    guide: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const g = props.guide || {};
const form = useForm({
    title: g.title || '',
    slug: g.slug || '',
    category_id: g.category_id || '',
    excerpt: g.excerpt || '',
    body: g.body || '',
    cover_image: g.cover_image || '',
    related_product_ids: (g.related_product_ids || []).map(Number),
    status: g.status || 'draft',
    featured: Boolean(g.featured),
    meta_title: g.meta_title || '',
    meta_description: g.meta_description || '',
});

const submit = () => {
    if (g.id) form.patch(`/admin/guides/${g.slug}`, { preserveScroll: true });
    else form.post('/admin/guides');
};
</script>

<template>
    <AdminLayout>
        <Head :title="g.id ? 'Edit Buying Guide' : 'Create Buying Guide'" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="section-kicker">SEO content</p><h1 class="section-title">{{ g.id ? 'Edit buying guide' : 'Create buying guide' }}</h1><p class="mt-2 text-sm text-slate-500">Write useful content first; link it to the products and category it helps customers choose.</p></div>
            <Link href="/admin/guides" class="btn-light">Back to guides</Link>
        </div>

        <form class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="card p-5">
                    <div class="grid gap-4">
                        <div><label class="label">Guide title *</label><input v-model="form.title" class="input" placeholder="e.g. How to choose a power bank for everyday use"><p class="error">{{ form.errors.title }}</p></div>
                        <div><label class="label">URL slug</label><input v-model="form.slug" class="input" placeholder="Generated from title"><p class="error">{{ form.errors.slug }}</p></div>
                        <div><label class="label">Short summary</label><textarea v-model="form.excerpt" rows="3" class="input" placeholder="A clear summary for Google and guide cards"></textarea><p class="error">{{ form.errors.excerpt }}</p></div>
                        <div><label class="label">Guide content *</label><textarea v-model="form.body" rows="18" class="input min-h-[420px]" placeholder="Write practical advice in clear paragraphs. Explain what to compare, common mistakes, who each option suits, and what customers should check before buying."></textarea><p class="mt-1 text-[10px] text-slate-500">Plain text is rendered with paragraph line breaks. Avoid copying manufacturer or competitor articles.</p><p class="error">{{ form.errors.body }}</p></div>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="font-semibold text-slate-900">Search appearance</h2>
                    <div class="mt-4 grid gap-4">
                        <div><label class="label">SEO title</label><input v-model="form.meta_title" class="input" maxlength="190" placeholder="Leave blank to use the guide title"><p class="error">{{ form.errors.meta_title }}</p></div>
                        <div><label class="label">SEO description</label><textarea v-model="form.meta_description" rows="3" class="input" maxlength="180" placeholder="Short useful summary for search results"></textarea><p class="error">{{ form.errors.meta_description }}</p></div>
                        <div><label class="label">Cover image URL</label><input v-model="form.cover_image" type="url" class="input" placeholder="https://..."><p class="text-[10px] text-slate-500">Optional. Use an original Prinx Tech image or a source you have permission to use.</p><p class="error">{{ form.errors.cover_image }}</p></div>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="card p-5">
                    <h2 class="font-semibold text-slate-900">Publishing</h2>
                    <div class="mt-4 space-y-4">
                        <div><label class="label">Status</label><select v-model="form.status" class="input"><option value="draft">Draft</option><option value="published">Published</option></select></div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input v-model="form.featured" type="checkbox" class="h-4 w-4 accent-prinx-500"> Feature this guide</label>
                        <button class="btn-primary w-full" :disabled="form.processing">{{ form.processing ? 'Saving...' : g.id ? 'Save guide' : 'Create guide' }}</button>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="font-semibold text-slate-900">Catalogue links</h2>
                    <div class="mt-4 space-y-4">
                        <div><label class="label">Primary category</label><select v-model="form.category_id" class="input"><option value="">General guide</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><p class="error">{{ form.errors.category_id }}</p></div>
                        <div><label class="label">Related products</label><select v-model="form.related_product_ids" multiple class="input min-h-[220px]"><option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }}</option></select><p class="mt-1 text-[10px] text-slate-500">Hold Ctrl/Cmd to select multiple products. These products will be linked directly from the guide.</p><p class="error">{{ form.errors.related_product_ids }}</p></div>
                    </div>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
