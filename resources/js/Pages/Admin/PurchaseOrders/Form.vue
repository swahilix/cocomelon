<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    order: { type: Object, default: null },
    products: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const editing = computed(() => Boolean(props.order?.id));
const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));
const variantFor = (line) => productById(line.product_id)?.variants.find((variant) => Number(variant.id) === Number(line.product_variant_id));
const newLine = (productId = '', variantId = '', quantity = 1) => ({ product_id: productId, product_variant_id: variantId, quantity, estimated_unit_cost: '', notes: '' });

const form = useForm({
    supplier_id: props.order?.supplier_id || '',
    supplier_name: props.order && !props.order.supplier_id ? (props.order.supplier_name || '') : '',
    order_date: props.order?.order_date || props.today,
    expected_date: props.order?.expected_date || '',
    notes: props.order?.notes || '',
    items: props.order?.items?.length ? props.order.items.map((item) => ({ ...item })) : [newLine()],
});
const oneTimeSupplier = ref(Boolean(props.order && !props.order.supplier_id && props.order.supplier_name) || !props.suppliers.length);

// Default the estimate from the last purchase cost when the product or variant changes.
const estimateFor = (line) => {
    const product = productById(line.product_id);
    if (!product) return null;
    return product.variants.length ? (variantFor(line)?.estimated_cost ?? null) : product.estimated_cost;
};
const onProductChange = (line) => {
    line.product_variant_id = '';
    line.estimated_unit_cost = estimateFor(line) ?? '';
};
const onVariantChange = (line) => {
    const estimate = estimateFor(line);
    if (estimate !== null) line.estimated_unit_cost = estimate;
};
const stockFor = (line) => {
    const product = productById(line.product_id);
    if (!product) return null;
    if (product.variants.length) return variantFor(line)?.stock_quantity ?? null;
    return product.track_stock ? product.stock_quantity : null;
};
const lineTotal = (line) => (line.estimated_unit_cost !== '' && line.estimated_unit_cost !== null ? Number(line.estimated_unit_cost) * Number(line.quantity || 0) : null);
const estimatedTotal = computed(() => form.items.reduce((sum, line) => sum + (lineTotal(line) || 0), 0));
const missingEstimates = computed(() => form.items.filter((line) => line.product_id && lineTotal(line) === null).length);
const totalQuantity = computed(() => form.items.reduce((sum, line) => sum + Number(line.quantity || 0), 0));

const addLine = () => form.items.push(newLine());
const removeLine = (index) => { if (form.items.length > 1) form.items.splice(index, 1); };

// Add everything at or below its minimum stock alert, ordering enough to reach
// twice the alert level (at least 1).
const lowStockMessage = ref('');
const addLowStock = () => {
    const existing = new Set(form.items.map((line) => `${line.product_id}-${line.product_variant_id || 0}`));
    let added = 0;
    props.products.forEach((product) => {
        if (!product.track_stock) return;
        const threshold = Number(product.low_stock_threshold || 0);
        const targets = product.variants.length
            ? product.variants.filter((variant) => variant.stock_quantity <= threshold).map((variant) => ({ variant, stock: variant.stock_quantity, cost: variant.estimated_cost }))
            : (product.stock_quantity <= threshold ? [{ variant: null, stock: product.stock_quantity, cost: product.estimated_cost }] : []);
        targets.forEach(({ variant, stock, cost }) => {
            const key = `${product.id}-${variant?.id || 0}`;
            if (existing.has(key)) return;
            existing.add(key);
            form.items.push({ ...newLine(product.id, variant?.id || '', Math.max(1, threshold * 2 - stock)), estimated_unit_cost: cost ?? '' });
            added += 1;
        });
    });
    form.items = form.items.filter((line) => line.product_id || form.items.length === 1);
    if (!form.items.length) form.items.push(newLine());
    lowStockMessage.value = added ? `Added ${added} low-stock item${added === 1 ? '' : 's'}. Adjust the quantities as needed.` : 'No products are at or below their minimum stock alert.';
};

const lineError = (index, field) => form.errors[`items.${index}.${field}`];
const submit = () => {
    form.transform((data) => ({
        ...data,
        supplier_id: oneTimeSupplier.value ? null : (data.supplier_id || null),
        supplier_name: oneTimeSupplier.value ? data.supplier_name : null,
        items: data.items.filter((line) => line.product_id).map((line) => ({ ...line, product_variant_id: line.product_variant_id || null, estimated_unit_cost: line.estimated_unit_cost === '' ? null : line.estimated_unit_cost })),
    }));
    if (editing.value) form.put(`/admin/purchase-orders/${props.order.id}`);
    else form.post('/admin/purchase-orders');
};
</script>

<template>
    <AdminLayout>
        <Head :title="editing ? `Edit ${order.reference}` : 'New purchase order'" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-sm text-slate-500"><Link href="/admin/purchase-orders" class="hover:text-prinx-600">Purchase orders</Link><template v-if="editing"><span>·</span><span>{{ order.reference }}</span></template></div>
                <h1 class="section-title mt-1">{{ editing ? 'Edit purchase order' : 'New purchase order' }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Choose what to buy and how many. The estimated cost is for you only; it is never printed or exported.</p>
            </div>
        </div>

        <form class="mt-5 grid gap-5 xl:grid-cols-[1fr_320px]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="card p-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between"><label class="label">Supplier <span class="font-normal text-slate-400">(optional)</span></label><button v-if="suppliers.length" type="button" class="text-xs font-semibold text-prinx-600" @click="oneTimeSupplier = !oneTimeSupplier">{{ oneTimeSupplier ? 'Choose saved supplier' : 'Type a supplier name' }}</button></div>
                            <select v-if="!oneTimeSupplier" v-model="form.supplier_id" class="input"><option value="">Not decided yet</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select>
                            <input v-else v-model="form.supplier_name" class="input" placeholder="Supplier name (optional)">
                            <p v-if="form.errors.supplier_id || form.errors.supplier_name" class="error">{{ form.errors.supplier_id || form.errors.supplier_name }}</p>
                        </div>
                        <div><label class="label">Order date</label><input v-model="form.order_date" type="date" class="input" required><p v-if="form.errors.order_date" class="error">{{ form.errors.order_date }}</p></div>
                        <div><label class="label">Needed by <span class="font-normal text-slate-400">(optional)</span></label><input v-model="form.expected_date" type="date" class="input"><p v-if="form.errors.expected_date" class="error">{{ form.errors.expected_date }}</p></div>
                        <div class="sm:col-span-2 lg:col-span-4"><label class="label">Notes for the supplier <span class="font-normal text-slate-400">(printed)</span></label><textarea v-model="form.notes" rows="2" class="input" placeholder="Delivery instructions, brand preferences, etc."></textarea></div>
                    </div>
                </section>

                <section class="card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div><h2 class="font-semibold">Items to buy</h2><p class="mt-1 text-xs text-slate-500">Estimated cost is filled from the last purchase price. Change it if you know the new price.</p></div>
                        <button type="button" class="btn-light" @click="addLowStock"><Icon name="danger" :size="16" /> Add low-stock items</button>
                    </div>
                    <p v-if="lowStockMessage" class="mt-3 rounded-[8px] bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ lowStockMessage }}</p>

                    <div class="mt-4 space-y-3">
                        <div v-for="(line, index) in form.items" :key="index" class="rounded-[10px] border border-slate-200 p-3">
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_40px]">
                                <div :class="productById(line.product_id)?.variants.length ? '' : 'sm:col-span-2'">
                                    <select v-model="line.product_id" class="input" required @change="onProductChange(line)"><option value="">Choose product</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }}</option></select>
                                    <p v-if="lineError(index, 'product_id')" class="error">{{ lineError(index, 'product_id') }}</p>
                                </div>
                                <div v-if="productById(line.product_id)?.variants.length">
                                    <select v-model="line.product_variant_id" data-native-select class="input" required @change="onVariantChange(line)"><option value="">Choose variant</option><option v-for="variant in productById(line.product_id).variants" :key="variant.id" :value="variant.id">{{ variant.name }}</option></select>
                                    <p v-if="lineError(index, 'product_variant_id')" class="error">{{ lineError(index, 'product_variant_id') }}</p>
                                </div>
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-[8px] text-red-500 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" title="Remove item" @click="removeLine(index)"><Icon name="trash" :size="18" /></button>
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-[100px_1fr_1fr_1.4fr]">
                                <div><label class="text-[10px] font-semibold text-slate-500">Quantity</label><input v-model.number="line.quantity" type="number" min="1" class="input" required></div>
                                <div><label class="text-[10px] font-semibold text-slate-500">Estimated cost (each)</label><input v-model.number="line.estimated_unit_cost" type="number" min="0" step="0.01" class="input" placeholder="Unknown"></div>
                                <div><label class="text-[10px] font-semibold text-slate-500">Estimated total</label><div class="input flex items-center bg-slate-50 text-sm font-semibold">{{ lineTotal(line) !== null ? money(lineTotal(line)) : '—' }}</div></div>
                                <div><label class="text-[10px] font-semibold text-slate-500">Note for this item <span class="font-normal">(printed)</span></label><input v-model="line.notes" class="input" placeholder="Optional, e.g. black colour"></div>
                            </div>
                            <p v-if="stockFor(line) !== null" class="mt-2 text-[11px] text-slate-500">In stock now: {{ stockFor(line) }}<template v-if="productById(line.product_id)?.track_stock"> · Alert at {{ productById(line.product_id).low_stock_threshold }}</template></p>
                            <p v-if="lineError(index, 'quantity')" class="error">{{ lineError(index, 'quantity') }}</p>
                        </div>
                    </div>
                    <p v-if="form.errors.items" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ form.errors.items }}</p>
                    <p v-if="form.errors.order" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ form.errors.order }}</p>
                    <button type="button" class="btn-light mt-3" @click="addLine"><Icon name="add-circle" :size="16" /> Add item</button>
                </section>
            </div>

            <aside class="card h-fit p-5 xl:sticky xl:top-24">
                <h2 class="font-semibold">Estimate</h2>
                <p class="mt-1 text-xs text-slate-500">Only visible here, not on the printed or downloaded order.</p>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Items</span><b>{{ totalQuantity }}</b></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span class="font-semibold">Estimated cost</span><b>{{ money(estimatedTotal) }}</b></div>
                    <p v-if="missingEstimates" class="text-xs text-amber-700">{{ missingEstimates }} item{{ missingEstimates === 1 ? ' has' : 's have' }} no cost estimate and {{ missingEstimates === 1 ? 'is' : 'are' }} not counted.</p>
                </div>
                <button class="btn-primary mt-5 w-full justify-center" :disabled="form.processing"><Icon name="tick-circle" :size="17" /> {{ form.processing ? 'Saving…' : (editing ? 'Save changes' : 'Create purchase order') }}</button>
                <Link :href="editing ? `/admin/purchase-orders/${order.id}` : '/admin/purchase-orders'" class="btn-light mt-2 w-full justify-center">Cancel</Link>
            </aside>
        </form>
    </AdminLayout>
</template>
