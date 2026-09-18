<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ product: Object, categories: Array, brands: Array, aiFormatterEnabled: Boolean });
const p = props.product || {};
const imageInput = ref(null);
const isDraggingImages = ref(false);
const replaceMainImage = ref(false);
const selectedImagePreviews = ref([]);

const form = useForm({
    _method: p.id ? 'PATCH' : 'POST',
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
    variants: (p.variants || []).map((variant) => ({
        id: variant.id,
        sku: variant.sku || '',
        label: variant.label || '',
        color: variant.color || '',
        storage: variant.storage || '',
        size: variant.size || '',
        price: variant.price || '',
        sale_price: variant.sale_price || '',
        stock_quantity: variant.stock_quantity ?? 0,
        image_url: variant.image && /^https?:\/\//i.test(variant.image) ? variant.image : '',
        image_file: null,
        existing_image_url: variant.image_url || '',
        is_active: variant.is_active !== false,
        sort_order: variant.sort_order || 0,
    })),
});

const currentImages = computed(() => {
    const images = [];
    if (p.primary_image && p.image_url) images.push({ url: p.image_url, label: 'Main image', main: true });
    for (const url of p.gallery_urls || []) images.push({ url, label: 'Gallery image', main: false });
    return images;
});

const hasVariants = computed(() => form.variants.length > 0);
const variantStockTotal = computed(() => form.variants
    .filter((variant) => variant.is_active)
    .reduce((sum, variant) => sum + Number(variant.stock_quantity || 0), 0));

const addVariant = () => {
    const suffix = String(form.variants.length + 1).padStart(2, '0');
    form.variants.push({
        id: null,
        sku: form.sku ? `${String(form.sku).toUpperCase()}-${suffix}` : '',
        label: '',
        color: '',
        storage: '',
        size: '',
        price: '',
        sale_price: '',
        stock_quantity: 0,
        image_url: '',
        image_file: null,
        existing_image_url: '',
        is_active: true,
        sort_order: form.variants.length,
    });
};

const removeVariant = (index) => {
    form.variants.splice(index, 1);
};

const merchantIssues = computed(() => {
    const issues = [];
    if (form.status !== 'active') issues.push('set status to Active');
    if (!form.merchant_sync_enabled) issues.push('enable Google Merchant sync');
    if (p.id && !form.sku) issues.push('add SKU');
    if (!form.name) issues.push('add product name');
    if (!form.description) issues.push('add description');
    if (!(Number(form.price) > 0)) issues.push('add a valid price');
    if (!p.primary_image && !form.primary_image && !form.primary_image_url) issues.push('add a main image');
    if (!form.gtin && !(form.brand_id && form.mpn)) issues.push('add GTIN or Brand + MPN');
    return issues;
});

const clearImagePreviews = () => {
    selectedImagePreviews.value.forEach((item) => URL.revokeObjectURL(item.url));
    selectedImagePreviews.value = [];
};

const resetCreateForm = () => {
    form.reset();

    // Explicitly restore every select/toggle default. Inertia can retain the
    // submitted values after a successful request, especially for selects.
    form._method = 'POST';
    form.category_id = '';
    form.brand_id = '';
    form.status = 'draft';
    form.condition = 'new';
    form.track_stock = true;
    form.featured = false;
    form.merchant_sync_enabled = true;
    form.variants = [];

    form.clearErrors();
    clearImagePreviews();
    replaceMainImage.value = false;
    isDraggingImages.value = false;
    if (imageInput.value) imageInput.value.value = '';
};

const applySelectedImages = (files) => {
    const images = Array.from(files || []).filter((file) => file.type?.startsWith('image/')).slice(0, 10);
    if (!images.length) return;

    clearImagePreviews();
    selectedImagePreviews.value = images.map((file, index) => ({
        file,
        url: URL.createObjectURL(file),
        main: (!p.primary_image || replaceMainImage.value) && index === 0,
    }));

    if (!p.primary_image || replaceMainImage.value) {
        form.primary_image = images[0];
        form.gallery = images.slice(1);
    } else {
        form.primary_image = null;
        form.gallery = images;
    }

    form.clearErrors('primary_image', 'gallery');
};

const chooseImages = () => imageInput.value?.click();
const selectImages = (event) => applySelectedImages(event.target.files);
const dropImages = (event) => {
    isDraggingImages.value = false;
    applySelectedImages(event.dataTransfer?.files);
};

const removeSelectedImage = (index) => {
    const files = selectedImagePreviews.value.filter((_, i) => i !== index).map((item) => item.file);
    if (!files.length) {
        clearImagePreviews();
        form.primary_image = null;
        form.gallery = [];
        if (imageInput.value) imageInput.value.value = '';
        return;
    }
    applySelectedImages(files);
};

const refreshImageRole = () => {
    if (selectedImagePreviews.value.length) applySelectedImages(selectedImagePreviews.value.map((item) => item.file));
};

const submit = () => {
    form.clearErrors();

    if (p.id) {
        form._method = 'PATCH';
        form.post(`/admin/products/${p.slug}`, {
            forceFormData: true,
            preserveScroll: true,
        });
        return;
    }

    form._method = 'POST';
    form.post('/admin/products', {
        forceFormData: true,
        preserveScroll: false,
        // POST visits preserve component state by default in Inertia. Disable
        // that here because a successful create must return a completely fresh
        // Add Product page, including native select elements.
        preserveState: false,
        onSuccess: resetCreateForm,
    });
};

onBeforeUnmount(clearImagePreviews);
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
                            <input v-model.trim="form.name" name="name" class="input" autocomplete="off">
                            <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="label">SKU</label>
                            <input v-if="p.id" v-model.trim="form.sku" name="sku" class="input">
                            <input v-else value="Generated automatically when saved" class="input bg-slate-50 text-slate-500" disabled>
                            <p v-if="!p.id" class="mt-1 text-[11px] text-slate-500">A unique PRX SKU will be created automatically.</p>
                            <p v-if="form.errors.sku" class="error">{{ form.errors.sku }}</p>
                        </div>
                        <div>
                            <label class="label">Category</label>
                            <select v-model="form.category_id" name="category_id" class="input"><option value="">Select</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                            <p v-if="form.errors.category_id" class="error">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="label">Brand</label>
                            <select v-model="form.brand_id" name="brand_id" class="input"><option value="">No brand</option><option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option></select>
                            <p v-if="form.errors.brand_id" class="error">{{ form.errors.brand_id }}</p>
                        </div>
                        <div><label class="label">Price</label><input v-model="form.price" name="price" type="number" class="input"><p v-if="form.errors.price" class="error">{{ form.errors.price }}</p></div>
                        <div>
                            <label class="label">Sale price</label>
                            <input v-model="form.sale_price" name="sale_price" type="number" class="input">
                            <p class="mt-1 text-[11px] text-slate-500">Same as Price = no discount. Lower than Price = discounted product.</p>
                            <p v-if="form.errors.sale_price" class="error">{{ form.errors.sale_price }}</p>
                        </div>
                        <div><label class="label">Stock</label><input v-model="form.stock_quantity" name="stock_quantity" type="number" class="input" :disabled="hasVariants"><p v-if="hasVariants" class="mt-1 text-[11px] text-slate-500">Derived from active variants: {{ variantStockTotal }}</p></div>
                        <div><label class="label">Low stock alert</label><input v-model="form.low_stock_threshold" name="low_stock_threshold" type="number" class="input"></div>
                        <div class="sm:col-span-2"><label class="label">Short description</label><textarea v-model="form.short_description" name="short_description" class="input" rows="3"></textarea></div>
                        <div class="sm:col-span-2"><label class="label">Description</label><textarea v-model="form.description" name="description" class="input" rows="10"></textarea><p v-if="form.errors.description" class="error">{{ form.errors.description }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Specifications <span class="text-xs text-slate-400">one per line: Label: Value</span></label><textarea v-model="form.specifications_text" class="input font-mono" rows="7"></textarea></div>
                    </div>
                </section>

                <section class="card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-semibold">Product variants</h2>
                            <p class="mt-1 text-sm text-slate-500">Use variants when color, storage or size changes the exact SKU, price, stock or image.</p>
                        </div>
                        <button type="button" class="btn-light shrink-0" @click="addVariant">Add variant</button>
                    </div>

                    <div v-if="!form.variants.length" class="mt-4 rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-7 text-center">
                        <p class="text-sm font-semibold text-slate-700">No variants</p>
                        <p class="mt-1 text-xs text-slate-500">Keep this empty for a simple product, or add variants for options such as Black / 256 GB.</p>
                    </div>

                    <div v-else class="mt-4 space-y-4">
                        <article v-for="(variant, index) in form.variants" :key="variant.id || `new-${index}`" class="rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div><strong class="text-sm text-slate-900">Variant {{ index + 1 }}</strong><p class="mt-0.5 text-[11px] text-slate-500">{{ variant.sku || 'Add a unique SKU' }}</p></div>
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input v-model="variant.is_active" type="checkbox" class="h-4 w-4 accent-prinx-500"> Active</label>
                                    <button type="button" class="text-xs font-semibold text-red-600" @click="removeVariant(index)">Remove</button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label">Variant SKU *</label><input v-model.trim="variant.sku" class="input" placeholder="PHONE-256-BLUE"><p v-if="form.errors[`variants.${index}.sku`]" class="error">{{ form.errors[`variants.${index}.sku`] }}</p></div>
                                <div><label class="label">Label</label><input v-model="variant.label" class="input" placeholder="Optional label"><p v-if="form.errors[`variants.${index}.label`]" class="error">{{ form.errors[`variants.${index}.label`] }}</p></div>
                                <div><label class="label">Color</label><input v-model="variant.color" class="input" placeholder="Black"></div>
                                <div><label class="label">Storage</label><input v-model="variant.storage" class="input" placeholder="256 GB"></div>
                                <div><label class="label">Size</label><input v-model="variant.size" class="input" placeholder="Large / 15 inch"></div>
                                <div><label class="label">Regular price</label><input v-model="variant.price" type="number" min="0" class="input" :placeholder="`Uses base: ${form.price || 0}`"></div>
                                <div><label class="label">Sale price</label><input v-model="variant.sale_price" type="number" min="0" class="input"><p v-if="form.errors[`variants.${index}.sale_price`]" class="error">{{ form.errors[`variants.${index}.sale_price`] }}</p></div>
                                <div><label class="label">Stock *</label><input v-model.number="variant.stock_quantity" type="number" min="0" class="input"></div>
                                <div class="sm:col-span-2"><label class="label">Variant image URL</label><input v-model="variant.image_url" type="url" class="input" placeholder="https://..."></div>
                                <div class="sm:col-span-2"><label class="label">Or upload variant image</label><input type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="input" @change="variant.image_file = $event.target.files[0] || null"><p v-if="form.errors[`variants.${index}.image_file`]" class="error">{{ form.errors[`variants.${index}.image_file`] }}</p></div>
                            </div>

                            <div v-if="variant.existing_image_url || variant.image_url" class="mt-3 flex items-center gap-3 rounded-[7px] border border-slate-200 bg-white p-2">
                                <img :src="variant.image_url || variant.existing_image_url" alt="Variant preview" class="h-14 w-14 rounded-[6px] object-contain">
                                <p class="text-[11px] text-slate-500">Current variant image. Uploaded replacement takes priority when saved.</p>
                            </div>
                        </article>
                    </div>

                    <div v-if="form.variants.length" class="mt-4 rounded-[7px] border border-prinx-100 bg-prinx-50 px-4 py-3 text-xs text-prinx-800">
                        Active variant stock total: <strong>{{ variantStockTotal }}</strong>. Product stock is synchronized to this total when saved.
                    </div>
                </section>

                <section class="card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-semibold">Images & Merchant data</h2>
                            <p class="mt-1 text-sm text-slate-500">Upload multiple product images. The main image is used by the storefront and Google Merchant.</p>
                        </div>
                        <span :class="['status shrink-0', merchantIssues.length ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700']">{{ merchantIssues.length ? `${merchantIssues.length} item${merchantIssues.length === 1 ? '' : 's'} missing` : 'Merchant ready' }}</span>
                    </div>

                    <div v-if="merchantIssues.length" class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <span class="font-semibold">To make this product Merchant-ready:</span>
                        {{ merchantIssues.join(', ') }}.
                    </div>

                    <div v-if="currentImages.length" class="mt-5">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label class="label mb-0">Attached images</label>
                            <span class="text-xs text-slate-500">{{ currentImages.length }} saved</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            <div v-for="(image, index) in currentImages" :key="`${image.url}-${index}`" class="relative overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50">
                                <img :src="image.url" :alt="image.label" class="aspect-square w-full object-cover">
                                <div class="absolute inset-x-0 bottom-0 bg-slate-950/75 px-2 py-1.5 text-[11px] font-medium text-white">{{ image.main ? 'Main image' : `Gallery ${index}` }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                            <label class="label mb-0">Upload product images</label>
                            <label v-if="p.primary_image" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                <input v-model="replaceMainImage" type="checkbox" class="h-4 w-4 accent-prinx-500" @change="refreshImageRole">
                                Replace current main with first new image
                            </label>
                        </div>

                        <input ref="imageInput" type="file" accept="image/*" multiple class="hidden" @change="selectImages">
                        <button
                            type="button"
                            :class="['flex min-h-40 w-full flex-col items-center justify-center rounded-[10px] border-2 border-dashed px-5 py-7 text-center transition', isDraggingImages ? 'border-prinx-500 bg-prinx-50' : 'border-slate-300 bg-slate-50 hover:border-prinx-400 hover:bg-prinx-50/50']"
                            @click="chooseImages"
                            @dragenter.prevent="isDraggingImages = true"
                            @dragover.prevent="isDraggingImages = true"
                            @dragleave.prevent="isDraggingImages = false"
                            @drop.prevent="dropImages"
                        >
                            <span class="flex h-11 w-11 items-center justify-center rounded-[9px] bg-white text-prinx-600 shadow-sm"><Icon name="gallery-add" :size="22" /></span>
                            <span class="mt-3 text-sm font-semibold text-slate-800">Drop images here or click to browse</span>
                            <span class="mt-1 text-xs leading-5 text-slate-500">Select multiple JPG, PNG or WebP images. Maximum 10 images, 8 MB each.</span>
                        </button>

                        <p v-if="form.errors.primary_image" class="error">{{ form.errors.primary_image }}</p>
                        <p v-if="form.errors.gallery" class="error">{{ form.errors.gallery }}</p>

                        <div v-if="selectedImagePreviews.length" class="mt-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">New images</span>
                                <span class="text-xs text-slate-500">{{ selectedImagePreviews.length }} selected</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                <div v-for="(image, index) in selectedImagePreviews" :key="image.url" class="relative overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50">
                                    <img :src="image.url" alt="Selected product image" class="aspect-square w-full object-cover">
                                    <span v-if="image.main" class="absolute left-2 top-2 rounded-[6px] bg-slate-950/80 px-2 py-1 text-[10px] font-semibold text-white">New main</span>
                                    <button type="button" class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-[7px] bg-white/95 text-red-600 shadow" @click="removeSelectedImage(index)"><Icon name="close-circle" :size="17" /></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><label class="label">Or main image URL</label><input v-model="form.primary_image_url" class="input" placeholder="https://..."></div>
                        <div><label class="label">Additional image URLs</label><textarea v-model="form.gallery_urls" class="input" rows="2" placeholder="One image URL per line"></textarea></div>
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
