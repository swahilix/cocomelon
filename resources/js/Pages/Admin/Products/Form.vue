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

// Older products stored warranty as free text ("12 months warranty"). Read a
// duration from it so editing keeps the warranty.
const parseWarranty = (text) => {
    const match = String(text || '').toLowerCase().match(/(\d+)\s*(day|days|month|months|mwezi|miezi|year|years|yr|yrs|mwaka|miaka)/);
    if (!match) return { value: '', unit: '' };
    const word = match[2];
    const unit = /^(day|days)$/.test(word) ? 'days' : /^(year|years|yr|yrs|mwaka|miaka)$/.test(word) ? 'years' : 'months';
    return { value: Number(match[1]), unit };
};
const parsedWarranty = p.warranty_duration_value ? { value: '', unit: '' } : parseWarranty(p.warranty);
const unparsedWarrantyText = !p.warranty_duration_value && p.warranty && !parsedWarranty.value ? p.warranty : '';
const canPublish = Number(p.price || 0) > 0;
const money = (value) => `TZS ${new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;

const form = useForm({
    _method: p.id ? 'PATCH' : 'POST',
    category_id: p.category_id || '',
    brand_id: p.brand_id || '',
    name: p.name || '',
    slug: p.slug || '',
    sku: p.sku || '',
    short_description: p.short_description || '',
    description: p.description || '',
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
    warranty_duration_value: p.warranty_duration_value || parsedWarranty.value,
    warranty_duration_unit: p.warranty_duration_unit || parsedWarranty.unit,
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
        current_price: variant.price,
        current_sale_price: variant.sale_price,
        current_stock: variant.stock_quantity ?? 0,
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
    .reduce((sum, variant) => sum + Number(variant.current_stock || 0), 0));
const hasWarranty = computed({
    get: () => Boolean(form.warranty_duration_unit),
    set: (value) => {
        form.warranty_duration_value = value ? (form.warranty_duration_value || 1) : '';
        form.warranty_duration_unit = value ? (form.warranty_duration_unit || 'years') : '';
    },
});

const addVariant = () => {
    const suffix = String(form.variants.length + 1).padStart(2, '0');
    form.variants.push({
        id: null,
        sku: form.sku ? `${String(form.sku).toUpperCase()}-${suffix}` : '',
        label: '',
        color: '',
        storage: '',
        size: '',
        current_price: null,
        current_sale_price: null,
        current_stock: 0,
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
    if (!canPublish) issues.push('add a selling price in Stock & pricing');
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
                <p class="mt-1 text-sm text-slate-500">{{ p.id ? 'Product details and specifications. Prices and stock are managed in Stock & pricing.' : 'Step 1 of 2: product details and specifications. After saving, add opening stock or a purchase with the cost and selling price.' }}</p>
            </div>
            <div class="flex gap-2"><Link v-if="p.id" :href="`/admin/products/${p.slug}/stock`" class="btn-primary">Stock & pricing</Link><Link href="/admin/products" class="btn-light">Back</Link></div>
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
                        <div><label class="label">Minimum stock alert</label><input v-model="form.low_stock_threshold" name="low_stock_threshold" type="number" min="0" class="input"><p class="mt-1 text-[11px] text-slate-500">You are alerted when stock falls to this quantity.</p><p v-if="form.errors.low_stock_threshold" class="error">{{ form.errors.low_stock_threshold }}</p></div>
                        <div>
                            <div class="flex items-center justify-between"><label class="label">Warranty</label><label class="flex items-center gap-1.5 text-xs text-slate-600"><input v-model="hasWarranty" type="checkbox"> Has warranty</label></div>
                            <div v-if="hasWarranty" class="grid grid-cols-[1fr_1.2fr] gap-2"><input v-model.number="form.warranty_duration_value" type="number" min="1" class="input" aria-label="Warranty duration"><select v-model="form.warranty_duration_unit" data-native-select class="input" aria-label="Warranty unit"><option value="days">Days</option><option value="months">Months</option><option value="years">Years</option></select></div>
                            <div v-else class="input flex items-center bg-slate-50 text-slate-400">No warranty</div>
                            <p v-if="unparsedWarrantyText" class="mt-1 text-[11px] text-amber-700">Previous warranty text: “{{ unparsedWarrantyText }}”. Set a duration to keep a warranty.</p>
                            <p v-if="form.errors.warranty_duration_value || form.errors.warranty_duration_unit" class="error">{{ form.errors.warranty_duration_value || form.errors.warranty_duration_unit }}</p>
                        </div>
                        <div v-if="p.id" class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <div><span class="text-slate-500">Selling price</span> <strong class="ml-1">{{ canPublish ? money(p.current_price) : 'Not set' }}</strong><span class="mx-3 text-slate-300">|</span><span class="text-slate-500">Stock</span> <strong class="ml-1">{{ p.track_stock ? p.stock_quantity : 'Not tracked' }}</strong></div>
                            <Link :href="`/admin/products/${p.slug}/stock`" class="text-xs font-semibold text-prinx-600">Add stock or change price →</Link>
                        </div>
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
                                <div class="sm:col-span-2 lg:col-span-1"><label class="label">Price & stock</label><div class="input flex items-center bg-slate-50 text-xs text-slate-600">{{ variant.id ? `${variant.current_price !== null && variant.current_price !== undefined ? money(variant.current_sale_price ?? variant.current_price) : 'No price'} · ${variant.current_stock} in stock` : 'Added in Stock & pricing' }}</div></div>
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
                        Active variant stock total: <strong>{{ variantStockTotal }}</strong>. Each variant's quantity, cost and selling price are entered in Stock & pricing.
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
                        <div><label class="label">Product type</label><input v-model="form.product_type" class="input"></div>
                    </div>
                </section>
            </div>

            <aside class="card h-fit p-5">
                <h2 class="font-semibold">Publishing</h2>
                <div class="mt-4 grid gap-4">
                    <div><label class="label">Status</label><select v-model="form.status" data-native-select class="input"><option value="draft">Draft</option><option value="active" :disabled="!canPublish">Active{{ canPublish ? '' : ' (add a price first)' }}</option><option value="archived">Archived</option></select><p v-if="!canPublish" class="mt-1 text-[11px] text-slate-500">The product stays a draft until it has a selling price from opening stock, a purchase or Update price.</p><p v-if="form.errors.status" class="error">{{ form.errors.status }}</p></div>
                    <div><label class="label">Condition</label><select v-model="form.condition" class="input"><option value="new">New</option><option value="refurbished">Refurbished</option><option value="used">Used</option></select></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.track_stock" type="checkbox"> Track stock</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.featured" type="checkbox"> Featured</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.merchant_sync_enabled" type="checkbox"> Google Merchant sync</label>
                    <button class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving...' : (p.id ? 'Save product details' : 'Save & continue to stock') }}</button>
                </div>
            </aside>
        </form>
    </AdminLayout>
</template>
