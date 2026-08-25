<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({ coupons: Array, categories: Array, brands: Array, products: Array });

const form = useForm({
    id: null,
    name: '',
    code: '',
    discount_type: 'percentage',
    discount_value: '',
    applies_to: 'all',
    minimum_order_amount: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
    category_ids: [],
    product_ids: [],
    brand_ids: [],
});

const editing = computed(() => !!form.id);
const activeCount = computed(() => props.coupons.filter((coupon) => coupon.status === 'Active').length);
const scheduledCount = computed(() => props.coupons.filter((coupon) => coupon.status === 'Scheduled').length);
const expiredCount = computed(() => props.coupons.filter((coupon) => coupon.status === 'Expired').length);

const reset = () => {
    form.reset();
    form.clearErrors();
    Object.assign(form, {
        id: null,
        name: '',
        code: '',
        discount_type: 'percentage',
        discount_value: '',
        applies_to: 'all',
        minimum_order_amount: '',
        starts_at: '',
        ends_at: '',
        is_active: true,
        category_ids: [],
        product_ids: [],
        brand_ids: [],
    });
};

const edit = (coupon) => {
    Object.assign(form, {
        id: coupon.id,
        name: coupon.name,
        code: coupon.code,
        discount_type: coupon.discount_type,
        discount_value: coupon.discount_value,
        applies_to: coupon.applies_to,
        minimum_order_amount: coupon.minimum_order_amount ?? '',
        starts_at: coupon.starts_at,
        ends_at: coupon.ends_at,
        is_active: coupon.is_active,
        category_ids: coupon.categories.map((item) => item.id),
        product_ids: coupon.products.map((item) => item.id),
        brand_ids: coupon.brands.map((item) => item.id),
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: reset };
    if (editing.value) form.patch(`/admin/coupons/${form.id}`, options);
    else form.post('/admin/coupons', options);
};

const remove = (coupon) => confirm(`Delete coupon “${coupon.code}”?`) && router.delete(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
const discountLabel = (coupon) => coupon.discount_type === 'percentage'
    ? `${Number(coupon.discount_value).toLocaleString()}% off`
    : `TZS ${Number(coupon.discount_value).toLocaleString()} off`;
const scopeLabel = (coupon) => ({
    all: 'All items',
    category: 'Selected categories',
    product: 'Selected products',
    brand: 'Selected brands',
    order_amount: `Orders from TZS ${Number(coupon.minimum_order_amount || 0).toLocaleString()}`,
}[coupon.applies_to] || coupon.applies_to);
const targetNames = (coupon) => {
    if (coupon.applies_to === 'category') return coupon.categories.map((item) => item.name).join(', ');
    if (coupon.applies_to === 'product') return coupon.products.map((item) => item.name).join(', ');
    if (coupon.applies_to === 'brand') return coupon.brands.map((item) => item.name).join(', ');
    return '';
};
const statusClass = (status) => ({
    Active: 'bg-emerald-100 text-emerald-700',
    Scheduled: 'bg-blue-100 text-blue-700',
    Expired: 'bg-amber-100 text-amber-700',
    Inactive: 'bg-slate-100 text-slate-600',
}[status] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <AdminLayout>
        <Head title="Coupons" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Promotions</p>
                <h1 class="section-title">Coupons</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Create time-limited discounts for all products, selected categories, products, brands, or orders that reach a specific amount.</p>
            </div>
            <button v-if="editing" type="button" class="btn-light" @click="reset"><Icon name="close-circle" :size="16" /> Cancel editing</button>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.14em] text-slate-500">All coupons</p><b class="mt-2 block text-2xl font-black">{{ coupons.length }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.14em] text-emerald-600">Active</p><b class="mt-2 block text-2xl font-black">{{ activeCount }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.14em] text-blue-600">Scheduled</p><b class="mt-2 block text-2xl font-black">{{ scheduledCount }}</b></div>
            <div class="metric-card"><p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-600">Expired</p><b class="mt-2 block text-2xl font-black">{{ expiredCount }}</b></div>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-[390px_minmax(0,1fr)]">
            <section class="card h-fit overflow-hidden xl:sticky xl:top-24">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3.5">
                    <p class="text-[10px] font-black uppercase tracking-[.14em] text-prinx-600">Coupon setup</p>
                    <h2 class="mt-1 text-lg font-black">{{ editing ? 'Edit coupon' : 'Create coupon' }}</h2>
                </div>

                <form class="space-y-4 p-4" @submit.prevent="save">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2"><label class="label">Coupon name *</label><input v-model="form.name" class="input" placeholder="e.g. August Accessories"><p class="error">{{ form.errors.name }}</p></div>
                        <div class="col-span-2"><label class="label">Coupon code *</label><input v-model="form.code" class="input uppercase" placeholder="SAVE10" @input="form.code = form.code.toUpperCase().replace(/\s+/g, '')"><p class="error">{{ form.errors.code }}</p></div>
                        <div><label class="label">Discount type *</label><select v-model="form.discount_type" class="input"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select></div>
                        <div><label class="label">Discount value *</label><input v-model="form.discount_value" type="number" min="0.01" step="0.01" class="input" :placeholder="form.discount_type === 'percentage' ? '10' : '5000'"><p class="error">{{ form.errors.discount_value }}</p></div>
                    </div>

                    <div>
                        <label class="label">Applicable to *</label>
                        <select v-model="form.applies_to" class="input">
                            <option value="all">All items</option>
                            <option value="category">Specific categories</option>
                            <option value="product">Specific products</option>
                            <option value="brand">Specific brands</option>
                            <option value="order_amount">Specific order amount</option>
                        </select>
                    </div>

                    <div v-if="form.applies_to === 'category'"><label class="label">Categories *</label><select v-model="form.category_ids" multiple class="input min-h-32 py-2"><option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option></select><p class="mt-1 text-[10px] text-slate-400">Hold Ctrl/Cmd to select multiple categories.</p><p class="error">{{ form.errors.category_ids }}</p></div>
                    <div v-if="form.applies_to === 'product'"><label class="label">Products *</label><select v-model="form.product_ids" multiple class="input min-h-40 py-2"><option v-for="item in products" :key="item.id" :value="item.id">{{ item.name }} · {{ item.sku }}</option></select><p class="mt-1 text-[10px] text-slate-400">Hold Ctrl/Cmd to select multiple products.</p><p class="error">{{ form.errors.product_ids }}</p></div>
                    <div v-if="form.applies_to === 'brand'"><label class="label">Brands *</label><select v-model="form.brand_ids" multiple class="input min-h-32 py-2"><option v-for="item in brands" :key="item.id" :value="item.id">{{ item.name }}</option></select><p class="mt-1 text-[10px] text-slate-400">Hold Ctrl/Cmd to select multiple brands.</p><p class="error">{{ form.errors.brand_ids }}</p></div>
                    <div v-if="form.applies_to === 'order_amount'"><label class="label">Minimum order amount (TZS) *</label><input v-model="form.minimum_order_amount" type="number" min="1" step="1" class="input" placeholder="50000"><p class="error">{{ form.errors.minimum_order_amount }}</p></div>

                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Starts at *</label><input v-model="form.starts_at" type="datetime-local" class="input"><p class="error">{{ form.errors.starts_at }}</p></div>
                        <div><label class="label">Ends at *</label><input v-model="form.ends_at" type="datetime-local" class="input"><p class="error">{{ form.errors.ends_at }}</p></div>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-prinx-500"> Coupon enabled</label>
                    <button class="btn-primary w-full py-3" :disabled="form.processing">{{ form.processing ? 'Saving...' : editing ? 'Save coupon' : 'Create coupon' }}</button>
                </form>
            </section>

            <section class="min-w-0">
                <div class="mb-3"><h2 class="text-lg font-black text-slate-950">Coupon list</h2><p class="mt-1 text-xs text-slate-500">Eligibility and timeframe are checked again every time a customer applies a coupon.</p></div>
                <div class="space-y-3">
                    <article v-for="coupon in coupons" :key="coupon.id" class="card overflow-hidden">
                        <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_190px_170px_auto] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><code class="rounded-[5px] bg-slate-950 px-2 py-1 text-xs font-black tracking-[.08em] text-white">{{ coupon.code }}</code><span :class="['status', statusClass(coupon.status)]">{{ coupon.status }}</span></div>
                                <h3 class="mt-2 text-base font-black text-slate-950">{{ coupon.name }}</h3>
                                <p class="mt-1 text-xs font-semibold text-prinx-600">{{ discountLabel(coupon) }} · {{ scopeLabel(coupon) }}</p>
                                <p v-if="targetNames(coupon)" class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ targetNames(coupon) }}</p>
                            </div>
                            <div><p class="text-[9px] font-black uppercase tracking-[.12em] text-slate-400">Timeframe</p><p class="mt-1 text-xs font-semibold text-slate-700">{{ new Date(coupon.starts_at).toLocaleString() }}</p><p class="mt-1 text-xs text-slate-500">to {{ new Date(coupon.ends_at).toLocaleString() }}</p></div>
                            <div><p class="text-[9px] font-black uppercase tracking-[.12em] text-slate-400">Usage</p><p class="mt-1 text-sm font-black text-slate-900">{{ coupon.usage_count }}</p><p class="mt-1 text-xs text-slate-500">completed checkouts</p></div>
                            <div class="flex gap-2 lg:justify-end"><button class="btn-light px-3 py-2" @click="edit(coupon)">Edit</button><button class="btn-danger" @click="remove(coupon)">Delete</button></div>
                        </div>
                    </article>
                    <div v-if="!coupons.length" class="card py-16 text-center"><p class="font-black text-slate-700">No coupons yet</p><p class="mt-1 text-xs text-slate-400">Create your first coupon using the form.</p></div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
