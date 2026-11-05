<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import MarketPricePanel from '../../../Components/MarketPricePanel.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    initialProductId: { type: Number, default: null },
    initialMode: { type: String, default: null },
    initialSupplierId: { type: Number, default: null },
    marketPriceEnabled: { type: Boolean, default: false },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const today = new Date().toISOString().slice(0, 10);
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));

const openingProducts = computed(() => props.products.filter((product) => product.opening_allowed || product.variants.some((variant) => variant.opening_allowed)));
const openingPossible = (product) => product && (product.opening_allowed || product.variants.some((variant) => variant.opening_allowed));

const initialProduct = productById(props.initialProductId);
const pickMode = () => {
    if (props.initialMode === 'opening' && props.can.opening) return 'opening';
    if (props.initialMode === 'purchase' && props.can.purchase) return 'purchase';
    if (initialProduct && props.can.opening && openingPossible(initialProduct)) return 'opening';
    return props.can.purchase ? 'purchase' : 'opening';
};
const mode = ref(pickMode());

const newLine = (productId = '') => {
    const product = productById(productId);
    return {
        product_id: productId || '',
        product_variant_id: '',
        quantity: 1,
        unit_cost: '',
        selling_price: product && !product.variants.length ? (product.selling_price ?? '') : '',
        discount_type: 'none',
        discount_value: '',
    };
};

const form = useForm({
    lines: [newLine(initialProduct && (mode.value !== 'opening' || openingPossible(initialProduct)) ? initialProduct.id : '')],
    notes: '',
    publish: true,
    // purchase only
    supplier_id: props.suppliers.some((supplier) => supplier.id === props.initialSupplierId) ? props.initialSupplierId : '',
    one_time_supplier_name: '',
    invoice_number: '',
    purchase_date: today,
    status: props.can.receive ? 'received' : 'ordered',
    payment_method: 'cash',
});
const oneTimeSupplier = ref(!props.suppliers.length);

const productOptions = computed(() => (mode.value === 'opening' ? openingProducts.value : props.products));
const variantOptions = (line) => {
    const product = productById(line.product_id);
    if (!product) return [];
    return mode.value === 'opening' ? product.variants.filter((variant) => variant.opening_allowed) : product.variants;
};
const variantFor = (line) => productById(line.product_id)?.variants.find((variant) => Number(variant.id) === Number(line.product_variant_id));
const currentPrice = (line) => {
    const product = productById(line.product_id);
    if (!product) return null;
    return product.variants.length ? (variantFor(line)?.selling_price ?? null) : product.selling_price;
};

const onProductChange = (line) => {
    line.product_variant_id = '';
    line.selling_price = currentPrice(line) ?? '';
};
const onVariantChange = (line) => {
    const price = currentPrice(line);
    if (price !== null) line.selling_price = price;
};
const setMode = (value) => {
    mode.value = value;
    form.clearErrors();
    // Drop lines that cannot take opening stock when switching to opening.
    if (value === 'opening') {
        form.lines = form.lines.filter((line) => !line.product_id || openingPossible(productById(line.product_id)));
        form.lines.forEach((line) => {
            if (line.product_variant_id && !variantOptions(line).some((variant) => variant.id === line.product_variant_id)) line.product_variant_id = '';
        });
        if (!form.lines.length) form.lines.push(newLine());
    }
};
const addLine = () => form.lines.push(newLine());
const removeLine = (index) => form.lines.length > 1 && form.lines.splice(index, 1);

const salePrice = (line) => {
    const price = Number(line.selling_price || 0);
    const value = Number(line.discount_value || 0);
    if (!price || line.discount_type === 'none' || value <= 0) return null;
    return line.discount_type === 'percent' ? Math.round(price * (1 - value / 100)) : price - value;
};
const belowCost = (line) => Number(line.unit_cost) > 0 && Number(line.selling_price) > 0 && Number(salePrice(line) ?? line.selling_price) <= Number(line.unit_cost);
const total = computed(() => form.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_cost || 0), 0));
const hasDrafts = computed(() => form.lines.some((line) => productById(line.product_id)?.status === 'draft'));
const lineError = (index, field) => form.errors[`lines.${index}.${field}`] || form.errors[`items.${index}.${field}`];

const submit = () => {
    if (mode.value === 'opening') {
        form.transform((data) => ({ lines: data.lines, notes: data.notes, publish: data.publish }))
            .post('/admin/stock/opening', {
                preserveScroll: true,
                onSuccess: () => { form.lines = [newLine()]; form.notes = ''; },
            });
        return;
    }

    form.transform((data) => ({
        supplier_id: oneTimeSupplier.value ? null : data.supplier_id,
        one_time_supplier_name: oneTimeSupplier.value ? data.one_time_supplier_name : null,
        invoice_number: data.invoice_number,
        purchase_date: data.purchase_date,
        status: data.status,
        payment_method: data.payment_method,
        notes: data.notes,
        items: data.lines.map((line) => ({ ...line, product_variant_id: line.product_variant_id || null, selling_price: line.selling_price || null, discount_value: line.discount_value || null })),
    })).post('/admin/stock/purchases', { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head title="Add stock" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Inventory</p>
                <h1 class="section-title">Add stock</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Enter opening stock you already have, or a purchase from a supplier, for one or many products. Each line sets the quantity, what you paid, and the selling price.</p>
            </div>
            <div class="flex gap-2"><Link href="/admin/products/create" class="btn-light"><Icon name="add-circle" :size="16" /> Add product</Link><Link href="/admin/stock?tab=purchases" class="btn-light"><Icon name="shopping-cart" :size="16" /> Purchase history</Link></div>
        </div>

        <div class="mt-5 grid max-w-xl grid-cols-2 gap-1 rounded-[10px] border border-slate-200 bg-white p-1.5">
            <button v-if="can.opening" type="button" :class="['rounded-[8px] px-3 py-2.5 text-left transition', mode === 'opening' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100']" @click="setMode('opening')"><span class="block text-sm font-semibold">Opening stock</span><span :class="['block text-[11px]', mode === 'opening' ? 'text-slate-300' : 'text-slate-400']">Stock you already have · added now</span></button>
            <button v-if="can.purchase" type="button" :class="['rounded-[8px] px-3 py-2.5 text-left transition', mode === 'purchase' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100']" @click="setMode('purchase')"><span class="block text-sm font-semibold">Purchase</span><span :class="['block text-[11px]', mode === 'purchase' ? 'text-slate-300' : 'text-slate-400']">Bought from a supplier</span></button>
        </div>

        <form class="card mt-4 p-5" @submit.prevent="submit">
            <div v-if="mode === 'purchase'" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between"><label class="label">Supplier</label><button v-if="suppliers.length" type="button" class="text-xs font-semibold text-prinx-600" @click="oneTimeSupplier = !oneTimeSupplier">{{ oneTimeSupplier ? 'Choose saved supplier' : 'Use one-time supplier' }}</button></div>
                    <select v-if="!oneTimeSupplier" v-model="form.supplier_id" class="input" required><option value="">Select supplier</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select>
                    <input v-else v-model="form.one_time_supplier_name" class="input" placeholder="Supplier name" required>
                    <p v-if="form.errors.supplier_id || form.errors.one_time_supplier_name" class="mt-1 text-xs text-red-600">{{ form.errors.supplier_id || form.errors.one_time_supplier_name }}</p>
                </div>
                <div><label class="label">Purchase date</label><input v-model="form.purchase_date" type="date" class="input" required></div>
                <div><label class="label">Invoice number</label><input v-model="form.invoice_number" class="input" placeholder="Optional"></div>
                <div><label class="label">Payment method</label><select v-model="form.payment_method" data-native-select class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="credit">Credit</option></select></div>
                <div class="sm:col-span-2 lg:col-span-5"><label class="label">Status</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-if="can.receive" :class="['flex cursor-pointer items-start gap-2 rounded-[8px] border px-3 py-2', form.status === 'received' ? 'border-prinx-400 bg-prinx-50' : 'border-slate-200']"><input v-model="form.status" type="radio" value="received" class="mt-1"><span><strong class="block text-sm">Received</strong><span class="text-xs text-slate-500">Goods have arrived: stock, cost and selling prices update now.</span></span></label>
                        <label :class="['flex cursor-pointer items-start gap-2 rounded-[8px] border px-3 py-2', form.status === 'ordered' ? 'border-prinx-400 bg-prinx-50' : 'border-slate-200']"><input v-model="form.status" type="radio" value="ordered" class="mt-1"><span><strong class="block text-sm">Ordered</strong><span class="text-xs text-slate-500">Not arrived yet: nothing changes until you receive it.</span></span></label>
                    </div>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                </div>
            </div>

            <p v-if="mode === 'opening' && !openingProducts.length" class="rounded-[8px] bg-amber-50 px-3 py-2 text-sm text-amber-800">Every product already has stock. Opening stock is only for products with no stock history; use Purchase instead.</p>

            <div class="space-y-3">
                <div v-for="(line, index) in form.lines" :key="index" class="rounded-[10px] border border-slate-200 p-3">
                    <div class="grid gap-2 sm:grid-cols-[1fr_1fr_40px]">
                        <div :class="variantOptions(line).length || productById(line.product_id)?.variants.length ? '' : 'sm:col-span-2'">
                            <select v-model="line.product_id" class="input" required @change="onProductChange(line)"><option value="">Choose product</option><option v-for="product in productOptions" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }}{{ product.status === 'draft' ? ' · draft' : '' }} · Stock {{ product.stock_quantity }}</option></select>
                            <p v-if="lineError(index, 'product_id')" class="mt-1 text-xs text-red-600">{{ lineError(index, 'product_id') }}</p>
                        </div>
                        <div v-if="productById(line.product_id)?.variants.length">
                            <select v-model="line.product_variant_id" data-native-select class="input" required @change="onVariantChange(line)"><option value="">Choose variant</option><option v-for="variant in variantOptions(line)" :key="variant.id" :value="variant.id">{{ variant.name }} · Stock {{ variant.stock_quantity }}</option></select>
                            <p v-if="lineError(index, 'product_variant_id')" class="mt-1 text-xs text-red-600">{{ lineError(index, 'product_variant_id') }}</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-[8px] text-red-500 hover:bg-red-50 disabled:text-slate-300" :disabled="form.lines.length === 1" title="Remove line" @click="removeLine(index)"><Icon name="trash" :size="18" /></button>
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-[90px_1fr_1fr_.8fr_.9fr]">
                        <div><label class="text-[10px] font-semibold text-slate-500">Quantity</label><input v-model.number="line.quantity" type="number" min="1" class="input" required></div>
                        <div><label class="text-[10px] font-semibold text-slate-500">Purchase price (each)</label><input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="input" required></div>
                        <div><label class="text-[10px] font-semibold text-slate-500">Selling price</label><input v-model.number="line.selling_price" type="number" min="0" step="0.01" class="input" :required="mode === 'opening' || !currentPrice(line)" :placeholder="currentPrice(line) ? `Keep ${money(currentPrice(line))}` : ''"></div>
                        <div><label class="text-[10px] font-semibold text-slate-500">Customer discount</label><select v-model="line.discount_type" data-native-select class="input"><option value="none">None</option><option value="amount">Amount</option><option value="percent">Percent</option></select></div>
                        <div><label class="text-[10px] font-semibold text-slate-500">{{ line.discount_type === 'percent' ? 'Discount %' : 'Discount amount' }}</label><input v-model.number="line.discount_value" type="number" min="0" step="0.01" class="input" :disabled="line.discount_type === 'none'"></div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-start justify-between gap-2">
                        <div class="space-y-0.5">
                            <p v-if="salePrice(line) !== null" class="text-xs text-emerald-700">Customers pay {{ money(salePrice(line)) }} instead of {{ money(line.selling_price) }}.</p>
                            <p v-if="belowCost(line)" class="text-xs text-amber-700">Selling price is at or below the purchase price.</p>
                            <p v-for="field in ['quantity', 'unit_cost', 'selling_price', 'discount_value']" v-show="lineError(index, field)" :key="field" class="text-xs text-red-600">{{ lineError(index, field) }}</p>
                        </div>
                        <MarketPricePanel class="w-full sm:w-auto sm:min-w-[320px] sm:max-w-md" :product-id="line.product_id || null" :variant-id="line.product_variant_id || null" :enabled="marketPriceEnabled" compact @use="(price) => (line.selling_price = price)" />
                    </div>
                </div>
            </div>
            <p v-if="form.errors.lines || form.errors.items" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ form.errors.lines || form.errors.items }}</p>
            <button type="button" class="btn-light mt-3" @click="addLine"><Icon name="add-circle" :size="16" /> Add another product</button>

            <div class="mt-5 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-[1fr_auto] sm:items-end">
                <div><label class="label">Notes</label><input v-model="form.notes" class="input" :placeholder="mode === 'opening' ? 'Optional, e.g. stock count on 5 Nov' : 'Optional'"></div>
                <label v-if="mode === 'opening' && hasDrafts" class="flex h-10 items-center gap-2 text-sm"><input v-model="form.publish" type="checkbox"> Publish draft products on the store</label>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div><p class="text-xs text-slate-500">{{ mode === 'opening' ? 'Stock value at cost' : 'Purchase total' }}</p><b class="text-xl">{{ money(total) }}</b></div>
                <button class="btn-primary" :disabled="form.processing || (mode === 'opening' && !openingProducts.length)"><Icon name="tick-circle" :size="17" /> {{ form.processing ? 'Saving…' : (mode === 'opening' ? 'Add opening stock' : (form.status === 'received' ? 'Save & add to stock' : 'Save purchase')) }}</button>
            </div>
        </form>
    </AdminLayout>
</template>
