<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    priceHistory: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
    purchases: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const today = new Date().toISOString().slice(0, 10);
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const hasVariants = computed(() => props.variants.length > 0);
const hasPrice = computed(() => props.product.price > 0);
const openingVariants = computed(() => props.variants.filter((variant) => variant.opening_allowed));
const openingAvailable = computed(() => (hasVariants.value ? openingVariants.value.length > 0 : props.product.opening_allowed));

const tabs = computed(() => [
    props.can.opening && openingAvailable.value ? ['opening', 'Opening stock', 'box-add'] : null,
    props.can.purchase ? ['purchase', 'Purchase', 'shopping-cart'] : null,
    props.can.price ? ['price', 'Update price', 'tag'] : null,
].filter(Boolean));
const tab = ref(tabs.value[0]?.[0] || '');

// Customer discount → the price customers actually pay.
const salePrice = (line) => {
    const price = Number(line.selling_price || 0);
    const value = Number(line.discount_value || 0);
    if (!price || line.discount_type === 'none' || value <= 0) return null;
    return line.discount_type === 'percent' ? Math.round(price * (1 - value / 100)) : price - value;
};
const belowCost = (line) => Number(line.unit_cost) > 0 && Number(line.selling_price) > 0 && Number(salePrice(line) ?? line.selling_price) <= Number(line.unit_cost);
const currentPrice = (variant) => (variant ? variant.price : (hasPrice.value ? props.product.price : null));
const newLine = (variant = null) => ({
    product_variant_id: variant?.id || '',
    label: variant?.name || props.product.name,
    quantity: '',
    unit_cost: '',
    selling_price: currentPrice(variant) ?? '',
    discount_type: 'none',
    discount_value: '',
});

// Only rows with a quantity are submitted, so remember which on-screen row each
// submitted line came from to show server errors on the right row.
const openingSubmitted = ref([]);
const purchaseSubmitted = ref([]);
const filledRows = (lines, target) => {
    const rows = lines.map((line, index) => ({ line, index })).filter(({ line }) => Number(line.quantity) > 0);
    target.value = rows.map(({ index }) => index);
    return rows.map(({ line }) => line);
};

// ---- Opening stock
const openingForm = useForm({
    lines: hasVariants.value ? openingVariants.value.map((variant) => newLine(variant)) : [newLine()],
    notes: '',
    publish: props.product.status === 'draft',
});
const submitOpening = () => openingForm
    .transform((data) => ({
        ...data,
        lines: filledRows(data.lines, openingSubmitted).map(({ label, ...line }) => line),
    }))
    .post(`/admin/products/${props.product.slug}/opening-stock`, { preserveScroll: true });

// ---- Purchase
const oneTimeSupplier = ref(!props.suppliers.length);
const purchaseForm = useForm({
    supplier_id: '',
    one_time_supplier_name: '',
    invoice_number: '',
    purchase_date: today,
    status: props.can.receive ? 'received' : 'ordered',
    payment_method: 'cash',
    notes: '',
    lines: hasVariants.value ? props.variants.map((variant) => newLine(variant)) : [newLine()],
});
const purchaseTotal = computed(() => purchaseForm.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_cost || 0), 0));
const submitPurchase = () => purchaseForm
    .transform((data) => ({
        supplier_id: oneTimeSupplier.value ? null : data.supplier_id,
        one_time_supplier_name: oneTimeSupplier.value ? data.one_time_supplier_name : null,
        invoice_number: data.invoice_number,
        purchase_date: data.purchase_date,
        status: data.status,
        payment_method: data.payment_method,
        notes: data.notes,
        stay: true,
        items: filledRows(data.lines, purchaseSubmitted).map((line) => ({
            product_id: props.product.id,
            product_variant_id: line.product_variant_id || null,
            quantity: line.quantity,
            unit_cost: line.unit_cost || 0,
            selling_price: line.selling_price || null,
            discount_type: line.discount_type,
            discount_value: line.discount_value || null,
        })),
    }))
    .post('/admin/stock/purchases', {
        preserveScroll: true,
        onSuccess: () => purchaseForm.reset('invoice_number', 'notes', 'one_time_supplier_name', 'lines'),
    });

// ---- Update price
const firstVariant = props.variants[0] || null;
const priceForm = useForm({
    product_variant_id: firstVariant?.id || '',
    selling_price: currentPrice(firstVariant) ?? '',
    discount_type: 'none',
    discount_value: '',
    sale_starts_at: '',
    sale_ends_at: '',
    notes: '',
});
const discountFromCurrent = (price, sale) => (sale !== null && sale !== undefined && price ? { type: 'amount', value: Math.round((price - sale) * 100) / 100 } : { type: 'none', value: '' });
const loadPriceTarget = () => {
    const variant = props.variants.find((item) => Number(item.id) === Number(priceForm.product_variant_id));
    const price = variant ? variant.price : (hasPrice.value ? props.product.price : null);
    const sale = variant ? variant.sale_price : props.product.sale_price;
    const discount = discountFromCurrent(price, sale);
    priceForm.selling_price = price ?? '';
    priceForm.discount_type = discount.type;
    priceForm.discount_value = discount.value;
    priceForm.sale_starts_at = variant ? '' : (props.product.sale_starts_at || '');
    priceForm.sale_ends_at = variant ? '' : (props.product.sale_ends_at || '');
};
loadPriceTarget();
const submitPrice = () => priceForm.post(`/admin/products/${props.product.slug}/price`, { preserveScroll: true });

const lineError = (form, index, field) => {
    const submitted = form === openingForm ? openingSubmitted.value : purchaseSubmitted.value;
    const position = submitted.indexOf(index);
    if (position === -1) return null;
    return form.errors[`lines.${position}.${field}`] || form.errors[`items.${position}.${field}`];
};
const movementLabel = (type) => ({
    opening_stock: 'Opening stock',
    opening_balance: 'Opening balance',
    purchase_receipt: 'Purchase received',
    purchase_return: 'Returned to supplier',
    pos_sale: 'POS sale',
    adjustment_increase: 'Adjustment +',
    adjustment_decrease: 'Adjustment −',
}[type] || String(type || '').replaceAll('_', ' '));
const sourceLabel = (source) => ({ opening_stock: 'Opening stock', purchase: 'Purchase', manual: 'Price update' }[source] || source);
const statusClass = (status) => ({
    received: 'bg-emerald-50 text-emerald-700',
    partially_received: 'bg-amber-50 text-amber-700',
    ordered: 'bg-blue-50 text-blue-700',
    cancelled: 'bg-red-50 text-red-700',
}[status] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <AdminLayout>
        <Head :title="`Stock & pricing · ${product.name}`" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
                <img :src="product.image_url" alt="" class="h-14 w-14 rounded-[10px] border border-slate-200 bg-white object-contain">
                <div>
                    <p class="section-kicker">Step 2 · Stock & pricing</p>
                    <h1 class="section-title">{{ product.name }}</h1>
                    <p class="mt-1 text-xs text-slate-500">{{ product.sku }}<template v-if="product.warranty"> · {{ product.warranty }}</template> · <span class="capitalize">{{ product.status }}</span></p>
                </div>
            </div>
            <div class="flex gap-2"><Link :href="`/admin/products/${product.slug}/edit`" class="btn-light"><Icon name="edit-2" :size="16" /> Edit details</Link><Link href="/admin/products/create" class="btn-light"><Icon name="add-circle" :size="16" /> Add another product</Link></div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Selling price</p><b class="mt-2 block text-xl">{{ hasPrice ? money(product.current_price) : 'Not set' }}</b><p v-if="product.sale_price" class="mt-1 text-xs text-slate-500">Regular {{ money(product.price) }}</p><p v-else-if="hasVariants && hasPrice" class="mt-1 text-xs text-slate-500">Lowest variant price</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">In stock</p><b :class="['mt-2 block text-2xl', product.track_stock && product.stock_quantity <= product.low_stock_threshold ? 'text-red-600' : '']">{{ product.track_stock ? product.stock_quantity : 'Not tracked' }}</b><p class="mt-1 text-xs text-slate-500">Alert at {{ product.low_stock_threshold }}</p></div>
            <div v-if="can.view_costs" class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Average cost</p><b class="mt-2 block text-xl">{{ product.average_cost ? money(product.average_cost) : '—' }}</b><p class="mt-1 text-xs text-slate-500">Weighted across purchases</p></div>
            <div class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Variants</p><b class="mt-2 block text-2xl">{{ variants.length || '—' }}</b><p class="mt-1 text-xs text-slate-500">{{ variants.length ? 'Each has its own stock and price' : 'Single product' }}</p></div>
        </div>

        <div v-if="!hasPrice && product.status === 'draft'" class="mt-4 flex items-start gap-2 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><Icon name="info-circle" :size="18" class="mt-0.5 shrink-0" /> This product has no selling price yet, so it stays a draft. Add opening stock or a purchase below.</div>

        <div v-if="hasVariants" class="card mt-5 overflow-x-auto">
            <table class="admin-table"><thead><tr><th>Variant</th><th>SKU</th><th>Stock</th><th>Selling price</th></tr></thead>
                <tbody><tr v-for="variant in variants" :key="variant.id"><td class="font-semibold">{{ variant.name }}</td><td class="text-xs text-slate-500">{{ variant.sku }}</td><td>{{ variant.stock_quantity }}</td><td><template v-if="variant.price"><span :class="variant.sale_price ? 'text-xs text-slate-400 line-through' : 'font-semibold'">{{ money(variant.price) }}</span><span v-if="variant.sale_price" class="ml-1 font-semibold text-emerald-700">{{ money(variant.sale_price) }}</span></template><span v-else class="text-xs font-semibold text-amber-600">Not set</span></td></tr></tbody>
            </table>
        </div>

        <div v-if="tabs.length" class="mt-5 flex gap-2 overflow-x-auto rounded-[10px] border border-slate-200 bg-white p-2">
            <button v-for="item in tabs" :key="item[0]" :class="['inline-flex shrink-0 items-center gap-2 rounded-[8px] px-3 py-2 text-sm font-semibold transition', tab === item[0] ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100']" @click="tab = item[0]"><Icon :name="item[2]" :size="16" />{{ item[1] }}</button>
        </div>

        <!-- Opening stock -->
        <form v-if="tab === 'opening'" class="card mt-4 p-5" @submit.prevent="submitOpening">
            <h2 class="font-semibold">Opening stock</h2>
            <p class="mt-1 text-xs text-slate-500">Stock you already have on hand. It is added immediately, together with what you paid and the selling price.{{ hasVariants ? ' Leave quantity empty for variants you do not have.' : '' }}</p>
            <div class="mt-4 space-y-3">
                <div v-for="(line, index) in openingForm.lines" :key="index" class="rounded-[10px] border border-slate-200 p-3">
                    <p v-if="hasVariants" class="mb-2 text-sm font-semibold text-slate-900">{{ line.label }}</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        <div><label class="label">Quantity</label><input v-model.number="line.quantity" type="number" min="1" class="input" :required="!hasVariants"></div>
                        <div><label class="label">Purchase price (each)</label><input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="input" :required="Number(line.quantity) > 0"></div>
                        <div><label class="label">Selling price</label><input v-model.number="line.selling_price" type="number" min="0" step="0.01" class="input" :required="Number(line.quantity) > 0"></div>
                        <div><label class="label">Customer discount</label><select v-model="line.discount_type" data-native-select class="input"><option value="none">None</option><option value="amount">Amount</option><option value="percent">Percent</option></select></div>
                        <div><label class="label">{{ line.discount_type === 'percent' ? 'Discount %' : 'Discount amount' }}</label><input v-model.number="line.discount_value" type="number" min="0" step="0.01" class="input" :disabled="line.discount_type === 'none'"></div>
                    </div>
                    <p v-if="salePrice(line) !== null" class="mt-2 text-xs text-emerald-700">Customers pay {{ money(salePrice(line)) }} instead of {{ money(line.selling_price) }}.</p>
                    <p v-if="belowCost(line)" class="mt-1 text-xs text-amber-700">Selling price is at or below the purchase price.</p>
                    <p v-for="field in ['product_variant_id', 'quantity', 'unit_cost', 'selling_price', 'discount_value']" v-show="lineError(openingForm, index, field)" :key="field" class="mt-1 text-xs text-red-600">{{ lineError(openingForm, index, field) }}</p>
                </div>
            </div>
            <p v-if="hasVariants && openingVariants.length < variants.length" class="mt-3 text-xs text-slate-500">Variants that already have stock are not listed. Use Purchase to add more of those.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                <div><label class="label">Notes</label><input v-model="openingForm.notes" class="input" placeholder="Optional, e.g. stock count on 5 Nov"></div>
                <label v-if="product.status === 'draft'" class="flex h-10 items-center gap-2 text-sm"><input v-model="openingForm.publish" type="checkbox"> Publish on the store</label>
            </div>
            <p v-if="openingForm.errors.lines" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ openingForm.errors.lines }}</p>
            <button class="btn-primary mt-4" :disabled="openingForm.processing"><Icon name="tick-circle" :size="17" /> {{ openingForm.processing ? 'Saving…' : 'Add opening stock' }}</button>
        </form>

        <!-- Purchase -->
        <form v-if="tab === 'purchase'" class="card mt-4 p-5" @submit.prevent="submitPurchase">
            <h2 class="font-semibold">Purchase</h2>
            <p class="mt-1 text-xs text-slate-500">Stock bought from a supplier. If it has arrived, mark it Received and stock and prices update now; otherwise they update when you receive it.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between"><label class="label">Supplier</label><button v-if="suppliers.length" type="button" class="text-xs font-semibold text-prinx-600" @click="oneTimeSupplier = !oneTimeSupplier">{{ oneTimeSupplier ? 'Choose saved supplier' : 'Use one-time supplier' }}</button></div>
                    <select v-if="!oneTimeSupplier" v-model="purchaseForm.supplier_id" class="input" required><option value="">Select supplier</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select>
                    <input v-else v-model="purchaseForm.one_time_supplier_name" class="input" placeholder="Supplier name" required>
                    <p v-if="purchaseForm.errors.supplier_id || purchaseForm.errors.one_time_supplier_name" class="mt-1 text-xs text-red-600">{{ purchaseForm.errors.supplier_id || purchaseForm.errors.one_time_supplier_name }}</p>
                </div>
                <div><label class="label">Purchase date</label><input v-model="purchaseForm.purchase_date" type="date" class="input" required></div>
                <div><label class="label">Invoice number</label><input v-model="purchaseForm.invoice_number" class="input" placeholder="Optional"></div>
                <div><label class="label">Status</label><select v-model="purchaseForm.status" data-native-select class="input"><option v-if="can.receive" value="received">Received — add to stock now</option><option value="ordered">Ordered — not yet arrived</option></select><p v-if="purchaseForm.errors.status" class="mt-1 text-xs text-red-600">{{ purchaseForm.errors.status }}</p></div>
                <div><label class="label">Payment method</label><select v-model="purchaseForm.payment_method" data-native-select class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="credit">Credit</option></select></div>
            </div>
            <div class="mt-4 space-y-3">
                <div v-for="(line, index) in purchaseForm.lines" :key="index" class="rounded-[10px] border border-slate-200 p-3">
                    <p v-if="hasVariants" class="mb-2 text-sm font-semibold text-slate-900">{{ line.label }}</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        <div><label class="label">Quantity</label><input v-model.number="line.quantity" type="number" min="1" class="input" :required="!hasVariants"></div>
                        <div><label class="label">Purchase price (each)</label><input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="input" :required="Number(line.quantity) > 0"></div>
                        <div><label class="label">Selling price</label><input v-model.number="line.selling_price" type="number" min="0" step="0.01" class="input" :required="Number(line.quantity) > 0 && !currentPrice(variants.find((variant) => variant.id === line.product_variant_id))"></div>
                        <div><label class="label">Customer discount</label><select v-model="line.discount_type" data-native-select class="input"><option value="none">None</option><option value="amount">Amount</option><option value="percent">Percent</option></select></div>
                        <div><label class="label">{{ line.discount_type === 'percent' ? 'Discount %' : 'Discount amount' }}</label><input v-model.number="line.discount_value" type="number" min="0" step="0.01" class="input" :disabled="line.discount_type === 'none'"></div>
                    </div>
                    <p v-if="salePrice(line) !== null" class="mt-2 text-xs text-emerald-700">Customers pay {{ money(salePrice(line)) }} instead of {{ money(line.selling_price) }}.</p>
                    <p v-if="belowCost(line)" class="mt-1 text-xs text-amber-700">Selling price is at or below the purchase price.</p>
                    <p v-for="field in ['product_variant_id', 'quantity', 'unit_cost', 'selling_price', 'discount_value']" v-show="lineError(purchaseForm, index, field)" :key="field" class="mt-1 text-xs text-red-600">{{ lineError(purchaseForm, index, field) }}</p>
                </div>
            </div>
            <p v-if="purchaseForm.errors.items" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ purchaseForm.errors.items }}</p>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <div><p class="text-xs text-slate-500">Purchase total</p><b class="text-xl">{{ money(purchaseTotal) }}</b></div>
                <div class="flex items-center gap-3"><Link href="/admin/stock?tab=purchases" class="text-xs font-semibold text-slate-500 hover:text-prinx-600">Buying several products? Use Stock management →</Link><button class="btn-primary" :disabled="purchaseForm.processing"><Icon name="tick-circle" :size="17" /> {{ purchaseForm.processing ? 'Saving…' : (purchaseForm.status === 'received' ? 'Save & add to stock' : 'Save purchase') }}</button></div>
            </div>
        </form>

        <!-- Update price -->
        <form v-if="tab === 'price'" class="card mt-4 p-5" @submit.prevent="submitPrice">
            <h2 class="font-semibold">Update price</h2>
            <p class="mt-1 text-xs text-slate-500">Change the selling price or customer discount without adding stock. Every change is recorded below.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-if="hasVariants" class="sm:col-span-2 lg:col-span-4"><label class="label">Variant</label><select v-model="priceForm.product_variant_id" data-native-select class="input" @change="loadPriceTarget"><option v-for="variant in variants" :key="variant.id" :value="variant.id">{{ variant.name }}</option></select><p v-if="priceForm.errors.product_variant_id" class="mt-1 text-xs text-red-600">{{ priceForm.errors.product_variant_id }}</p></div>
                <div><label class="label">Selling price</label><input v-model.number="priceForm.selling_price" type="number" min="0" step="0.01" class="input" required><p v-if="priceForm.errors.selling_price" class="mt-1 text-xs text-red-600">{{ priceForm.errors.selling_price }}</p></div>
                <div><label class="label">Customer discount</label><select v-model="priceForm.discount_type" data-native-select class="input"><option value="none">None</option><option value="amount">Amount</option><option value="percent">Percent</option></select></div>
                <div><label class="label">{{ priceForm.discount_type === 'percent' ? 'Discount %' : 'Discount amount' }}</label><input v-model.number="priceForm.discount_value" type="number" min="0" step="0.01" class="input" :disabled="priceForm.discount_type === 'none'"><p v-if="priceForm.errors.discount_value" class="mt-1 text-xs text-red-600">{{ priceForm.errors.discount_value }}</p></div>
                <div class="flex items-end"><p class="text-xs text-slate-500">Customers pay <strong class="text-slate-900">{{ money(salePrice(priceForm) ?? priceForm.selling_price) }}</strong></p></div>
                <template v-if="!hasVariants && priceForm.discount_type !== 'none'">
                    <div><label class="label">Discount starts <span class="text-xs text-slate-400">(optional)</span></label><input v-model="priceForm.sale_starts_at" type="datetime-local" class="input"></div>
                    <div><label class="label">Discount ends <span class="text-xs text-slate-400">(optional)</span></label><input v-model="priceForm.sale_ends_at" type="datetime-local" class="input"><p v-if="priceForm.errors.sale_ends_at" class="mt-1 text-xs text-red-600">{{ priceForm.errors.sale_ends_at }}</p></div>
                </template>
                <div class="sm:col-span-2"><label class="label">Reason</label><input v-model="priceForm.notes" class="input" placeholder="Optional, e.g. Black Friday"></div>
            </div>
            <button class="btn-primary mt-4" :disabled="priceForm.processing"><Icon name="tick-circle" :size="17" /> {{ priceForm.processing ? 'Saving…' : 'Update price' }}</button>
        </form>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Stock history</h2></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Type</th><th>Qty</th><th v-if="can.view_costs">Cost</th><th>Balance</th></tr></thead>
                    <tbody><tr v-for="movement in movements" :key="movement.id"><td class="whitespace-nowrap text-xs">{{ new Date(movement.created_at).toLocaleDateString() }}</td><td><p class="font-semibold">{{ movementLabel(movement.type) }}</p><p v-if="movement.variant_name" class="text-xs text-prinx-700">{{ movement.variant_name }}</p><p class="text-[11px] text-slate-400">{{ movement.reference }}</p></td><td :class="movement.quantity > 0 ? 'font-semibold text-emerald-600' : 'font-semibold text-red-600'">{{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}</td><td v-if="can.view_costs">{{ movement.unit_cost !== null ? money(movement.unit_cost) : '—' }}</td><td>{{ movement.balance_after ?? '—' }}</td></tr><tr v-if="!movements.length"><td :colspan="can.view_costs ? 5 : 4" class="py-10 text-center text-slate-400">No stock yet.</td></tr></tbody>
                </table></div>
            </section>
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Price history</h2></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Date</th><th>Source</th><th>Price</th><th>Customers pay</th></tr></thead>
                    <tbody><tr v-for="change in priceHistory" :key="change.id"><td class="whitespace-nowrap text-xs">{{ new Date(change.created_at).toLocaleDateString() }}</td><td><p class="font-semibold">{{ sourceLabel(change.source) }}</p><p v-if="change.variant_name" class="text-xs text-prinx-700">{{ change.variant_name }}</p><p class="text-[11px] text-slate-400">{{ change.changed_by || '' }}{{ change.notes ? ` · ${change.notes}` : '' }}</p></td><td><span v-if="change.old_price !== null && Number(change.old_price) !== Number(change.new_price)" class="mr-1 text-xs text-slate-400 line-through">{{ money(change.old_price) }}</span>{{ money(change.new_price) }}</td><td class="font-semibold">{{ money(change.new_sale_price ?? change.new_price) }}</td></tr><tr v-if="!priceHistory.length"><td colspan="4" class="py-10 text-center text-slate-400">No prices set yet.</td></tr></tbody>
                </table></div>
            </section>
        </div>

        <section v-if="purchases.length" class="card mt-5 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Purchases of this product</h2></div>
            <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Reference</th><th>Supplier</th><th>Date</th><th>Qty</th><th>Received</th><th>Status</th></tr></thead>
                <tbody><tr v-for="purchase in purchases" :key="`${purchase.id}-${purchase.product_variant_id}`"><td><Link :href="`/admin/stock/purchases/${purchase.id}`" class="font-semibold text-slate-900 hover:text-prinx-600">{{ purchase.reference }}</Link><p v-if="purchase.variant_name" class="text-xs text-prinx-700">{{ purchase.variant_name }}</p></td><td>{{ purchase.supplier_name || '—' }}</td><td class="text-xs">{{ purchase.purchase_date }}</td><td>{{ purchase.quantity }}</td><td>{{ purchase.quantity_received }}</td><td><span :class="['rounded-[7px] px-2 py-1 text-[11px] font-semibold capitalize', statusClass(purchase.status)]">{{ String(purchase.status).replaceAll('_', ' ') }}</span></td></tr></tbody>
            </table></div>
        </section>
    </AdminLayout>
</template>
