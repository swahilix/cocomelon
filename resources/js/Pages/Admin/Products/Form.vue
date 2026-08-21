<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ product: Object, categories: Array, brands: Array, aiFormatterEnabled: Boolean });
const p = props.product || {};

const form = useForm({
    category_id: p.category_id || '',
    brand_id: p.brand_id || '',
    name: p.name || '',
    slug: p.slug || '',
    sku: p.sku || '',
    short_description: p.short_description || '',
    description: p.description || '',
    price: p.price || '',
    sale_price: p.sale_price || '',
    stock_quantity: p.stock_quantity ?? 0,
    low_stock_threshold: p.low_stock_threshold ?? 5,
    track_stock: p.track_stock ?? true,
    condition: p.condition || 'new',
    status: p.status || 'draft',
    featured: p.featured ?? false,
    merchant_sync_enabled: p.merchant_sync_enabled ?? true,
    primary_image: null,
    primary_image_url: '',
    gallery: [],
    gallery_urls: '',
    gtin: p.gtin || '',
    mpn: p.mpn || '',
    google_product_category: p.google_product_category || '',
    product_type: p.product_type || '',
    warranty: p.warranty || '',
    specifications_text: p.specifications ? Object.entries(p.specifications).map(([k, v]) => `${k}: ${v}`).join('\n') : '',
    meta_title: p.meta_title || '',
    meta_description: p.meta_description || '',
    sort_order: p.sort_order || 0,
});

const merchantIssues = computed(() => {
    const issues = [];
    if (form.status !== 'active') issues.push('set status to Active');
    if (!form.merchant_sync_enabled) issues.push('enable Google Merchant sync');
    if (!form.sku) issues.push('add SKU');
    if (!form.name) issues.push('add product name');
    if (!form.description) issues.push('add description');
    if (!(Number(form.price) > 0)) issues.push('add a valid price');
    if (!p.primary_image && !form.primary_image && !form.primary_image_url) issues.push('add a main image');
    if (!form.gtin && !(form.brand_id && form.mpn)) issues.push('add GTIN or Brand + MPN');
    return issues;
});

const submit = () => {
    form.clearErrors();

    if (p.id) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(`/admin/products/${p.slug}`, {
            forceFormData: true,
            preserveScroll: true,
        });
        return;
    }

    form.post('/admin/products', { forceFormData: true, preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <div class="flex items-center justify-between">
            <div>
                <p class="section-kicker">Catalogue</p>
                <h1 class="section-title">{{ p.id ? 'Edit product' : 'Add product' }}</h1>
            </div>
            <Link href="/admin/products" class="btn-light">Back</Link>
        </div>

        <form class="mt-6 grid gap-5 lg:grid-cols-[1fr_340px]" @submit.prevent="submit">
            <div class="grid gap-5">
                <section class="card p-5">
                    <h2 class="font-semibold">Product information</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label">Product name</label>
                            <input v-model="form.name" class="input">
                            <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="label">SKU</label>
                            <input v-model="form.sku" class="input">
                            <p v-if="form.errors.sku" class="error">{{ form.errors.sku }}</p>
                        </div>
                        <div>
                            <label class="label">Category</label>
                            <select v-model="form.category_id" class="input"><option value="">Select</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                            <p v-if="form.errors.category_id" class="error">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="label">Brand</label>
                            <select v-model="form.brand_id" class="input"><option value="">No brand</option><option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option></select>
                            <p v-if="form.errors.brand_id" class="error">{{ form.errors.brand_id }}</p>
                        </div>
                        <div><label class="label">Price</label><input v-model="form.price" type="number" class="input"><p v-if="form.errors.price" class="error">{{ form.errors.price }}</p></div>
                        <div><label class="label">Sale price</label><input v-model="form.sale_price" type="number" class="input"><p v-if="form.errors.sale_price" class="error">{{ form.errors.sale_price }}</p></div>
                        <div><label class="label">Stock</label><input v-model="form.stock_quantity" type="number" class="input"></div>
                        <div><label class="label">Low stock alert</label><input v-model="form.low_stock_threshold" type="number" class="input"></div>
                        <div class="sm:col-span-2"><label class="label">Short description</label><textarea v-model="form.short_description" class="input" rows="3"></textarea></div>
                        <div class="sm:col-span-2"><label class="label">Description</label><textarea v-model="form.description" class="input" rows="10"></textarea><p v-if="form.errors.description" class="error">{{ form.errors.description }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Specifications <span class="text-xs text-slate-400">one per line: Label: Value</span></label><textarea v-model="form.specifications_text" class="input font-mono" rows="7"></textarea></div>
                    </div>
                </section>

                <section class="card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-semibold">Images & Merchant data</h2>
                            <p class="mt-1 text-sm text-slate-500">Google requires a main image and either a GTIN or Brand + MPN.</p>
                        </div>
                        <span :class="['status shrink-0', merchantIssues.length ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700']">{{ merchantIssues.length ? `${merchantIssues.length} item${merchantIssues.length === 1 ? '' : 's'} missing` : 'Merchant ready' }}</span>
                    </div>

                    <div v-if="merchantIssues.length" class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <span class="font-semibold">To make this product Merchant-ready:</span>
                        {{ merchantIssues.join(', ') }}.
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><label class="label">Main image</label><input type="file" accept="image/*" @change="form.primary_image = $event.target.files[0]"><p v-if="form.errors.primary_image" class="error">{{ form.errors.primary_image }}</p></div>
                        <div><label class="label">Or image URL</label><input v-model="form.primary_image_url" class="input"></div>
                        <div><label class="label">GTIN</label><input v-model.trim="form.gtin" class="input" placeholder="UPC / EAN / GTIN"></div>
                        <div><label class="label">MPN</label><input v-model.trim="form.mpn" class="input" placeholder="Manufacturer part number"></div>
                        <div><label class="label">Warranty</label><input v-model="form.warranty" class="input"></div>
                        <div><label class="label">Product type</label><input v-model="form.product_type" class="input"></div>
                    </div>
                </section>
            </div>

            <aside class="card h-fit p-5">
                <h2 class="font-semibold">Publishing</h2>
                <div class="mt-4 grid gap-4">
                    <div><label class="label">Status</label><select v-model="form.status" class="input"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></div>
                    <div><label class="label">Condition</label><select v-model="form.condition" class="input"><option value="new">New</option><option value="refurbished">Refurbished</option><option value="used">Used</option></select></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.track_stock" type="checkbox"> Track stock</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.featured" type="checkbox"> Featured</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.merchant_sync_enabled" type="checkbox"> Google Merchant sync</label>
                    <button class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save product' }}</button>
                </div>
            </aside>
        </form>
    </AdminLayout>
</template>
