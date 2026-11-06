<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    recent: { type: Array, default: () => [] },
});

const page = usePage();
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store?.currency || 'TZS', maximumFractionDigits: 0 }).format(Number(value || 0));
const productById = (id) => props.products.find((product) => Number(product.id) === Number(id));
const variantFor = (line) => productById(line.product_id)?.variants.find((variant) => Number(variant.id) === Number(line.product_variant_id));
const newLine = () => ({ product_id: '', product_variant_id: '', quantity: 1, unit_price: '' });

const form = useForm({
    sale_date: props.today,
    sale_time: '',
    customer_id: '',
    customer_name: '',
    customer_phone: '',
    save_customer: false,
    payment_method_id: props.paymentMethods[0]?.id || '',
    payment_reference: '',
    discount: '',
    notes: '',
    items: [newLine()],
});
const customerMode = ref('walk_in');

const stockFor = (line) => {
    const product = productById(line.product_id);
    if (!product) return null;
    if (product.variants.length) return variantFor(line)?.stock_quantity ?? null;
    return product.track_stock ? product.stock_quantity : null;
};
const onProductChange = (line) => {
    line.product_variant_id = '';
    const product = productById(line.product_id);
    line.unit_price = product && !product.variants.length && product.price > 0 ? product.price : '';
};
const onVariantChange = (line) => {
    const variant = variantFor(line);
    if (variant?.price) line.unit_price = variant.price;
};
const addLine = () => form.items.push(newLine());
const removeLine = (index) => { if (form.items.length > 1) form.items.splice(index, 1); };

const subtotal = computed(() => form.items.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));
const short = (line) => {
    const stock = stockFor(line);
    return stock !== null && Number(line.quantity || 0) > stock;
};
const lineError = (index, field) => form.errors[`items.${index}.${field}`];

const submit = () => {
    form.transform((data) => ({
        ...data,
        customer_id: customerMode.value === 'existing' ? (data.customer_id || null) : null,
        customer_name: customerMode.value === 'new' ? data.customer_name : null,
        customer_phone: customerMode.value === 'new' ? data.customer_phone : null,
        save_customer: customerMode.value === 'new' && data.save_customer,
        sale_time: data.sale_time || null,
        discount: data.discount || 0,
        items: data.items.filter((line) => line.product_id).map((line) => ({ ...line, product_variant_id: line.product_variant_id || null })),
    })).post('/admin/sales/past', {
        preserveScroll: true,
        onSuccess: () => {
            const date = form.sale_date;
            const method = form.payment_method_id;
            form.reset();
            form.sale_date = date;
            form.payment_method_id = method;
            form.items = [newLine()];
            customerMode.value = 'walk_in';
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Record past sale" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Sales</p>
                <h1 class="section-title">Record past sale</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Enter a sale that already happened, with its real date. It reduces stock and counts in sales and profit reports for that date. It is not added to any cash register.</p>
            </div>
            <Link href="/admin/sales" class="btn-light"><Icon name="basket-1" :size="16" /> All sales</Link>
        </div>

        <div class="mt-4 flex items-start gap-2 rounded-[10px] border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">
            <Icon name="info-circle" :size="16" class="mt-0.5 shrink-0" />
            <span>Enter purchases first (in <Link href="/admin/stock/entry?mode=purchase" class="font-semibold underline">Add stock</Link>, marked Received) so there is stock to sell. Work in date order: older purchases, then the sales that used them.</span>
        </div>

        <form class="mt-5 grid gap-5 xl:grid-cols-[1fr_320px]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="card p-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div><label class="label">Sale date</label><input v-model="form.sale_date" type="date" :max="today" class="input" required><p v-if="form.errors.sale_date" class="error">{{ form.errors.sale_date }}</p></div>
                        <div><label class="label">Time <span class="font-normal text-slate-400">(optional)</span></label><input v-model="form.sale_time" type="time" class="input"><p v-if="form.errors.sale_time" class="error">{{ form.errors.sale_time }}</p></div>
                        <div><label class="label">Payment method</label><select v-model="form.payment_method_id" data-native-select class="input" required><option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option></select><p v-if="form.errors.payment_method_id" class="error">{{ form.errors.payment_method_id }}</p></div>
                        <div><label class="label">Payment reference <span class="font-normal text-slate-400">(optional)</span></label><input v-model="form.payment_reference" class="input" placeholder="e.g. M-Pesa code"></div>
                    </div>

                    <div class="mt-4">
                        <label class="label">Customer</label>
                        <div class="grid grid-cols-3 gap-1 rounded-[8px] bg-slate-100 p-1 text-xs font-semibold sm:max-w-md">
                            <button v-for="[value, label] in [['walk_in', 'Walk-in'], ['existing', 'Saved customer'], ['new', 'New customer']]" :key="value" type="button" :class="['rounded-[6px] px-2 py-1.5', customerMode === value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500']" @click="customerMode = value">{{ label }}</button>
                        </div>
                        <div v-if="customerMode === 'existing'" class="mt-2 sm:max-w-md"><select v-model="form.customer_id" class="input"><option value="">Choose customer</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` · ${customer.phone}` : '' }}</option></select></div>
                        <div v-else-if="customerMode === 'new'" class="mt-2 grid gap-2 sm:grid-cols-2">
                            <input v-model="form.customer_name" class="input" placeholder="Customer name">
                            <input v-model="form.customer_phone" class="input" placeholder="Phone (optional)" inputmode="tel">
                            <label class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2"><input v-model="form.save_customer" type="checkbox"> Save to the customers list</label>
                        </div>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="font-semibold">Items sold</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="(line, index) in form.items" :key="index" class="rounded-[10px] border border-slate-200 p-3">
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_40px]">
                                <div :class="productById(line.product_id)?.variants.length ? '' : 'sm:col-span-2'">
                                    <select v-model="line.product_id" class="input" required @change="onProductChange(line)"><option value="">Choose product</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} · {{ product.sku }} · Stock {{ product.stock_quantity }}</option></select>
                                </div>
                                <div v-if="productById(line.product_id)?.variants.length">
                                    <select v-model="line.product_variant_id" data-native-select class="input" required @change="onVariantChange(line)"><option value="">Choose variant</option><option v-for="variant in productById(line.product_id).variants" :key="variant.id" :value="variant.id">{{ variant.name }} · Stock {{ variant.stock_quantity }}</option></select>
                                    <p v-if="lineError(index, 'product_variant_id')" class="error">{{ lineError(index, 'product_variant_id') }}</p>
                                </div>
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-[8px] text-red-500 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" title="Remove item" @click="removeLine(index)"><Icon name="trash" :size="18" /></button>
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-[100px_1fr_1fr]">
                                <div><label class="text-[10px] font-semibold text-slate-500">Quantity</label><input v-model.number="line.quantity" type="number" min="1" class="input" required></div>
                                <div><label class="text-[10px] font-semibold text-slate-500">Price sold at (each)</label><input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="input" required></div>
                                <div><label class="text-[10px] font-semibold text-slate-500">Line total</label><div class="input flex items-center bg-slate-50 font-semibold">{{ money(Number(line.quantity || 0) * Number(line.unit_price || 0)) }}</div></div>
                            </div>
                            <p v-if="stockFor(line) !== null" :class="['mt-2 text-[11px]', short(line) ? 'font-semibold text-red-600' : 'text-slate-500']">In stock: {{ stockFor(line) }}<template v-if="short(line)"> — not enough. Enter the purchase for this item first.</template></p>
                        </div>
                    </div>
                    <p v-if="form.errors.items" class="mt-3 rounded-[8px] bg-red-50 px-3 py-2 text-sm text-red-700">{{ form.errors.items }}</p>
                    <button type="button" class="btn-light mt-3" @click="addLine"><Icon name="add-circle" :size="16" /> Add item</button>
                    <div class="mt-4"><label class="label">Notes <span class="font-normal text-slate-400">(optional)</span></label><input v-model="form.notes" class="input"></div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="card h-fit p-5">
                    <h2 class="font-semibold">Total</h2>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><b>{{ money(subtotal) }}</b></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Discount</span><input v-model.number="form.discount" type="number" min="0" class="input h-9 w-32 text-right"></div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span class="font-semibold">Total paid</span><b>{{ money(total) }}</b></div>
                    </div>
                    <button class="btn-primary mt-5 w-full justify-center" :disabled="form.processing"><Icon name="tick-circle" :size="17" /> {{ form.processing ? 'Saving…' : 'Record past sale' }}</button>
                </section>

                <section v-if="recent.length" class="card p-5">
                    <h2 class="text-sm font-semibold">Recently recorded</h2>
                    <ul class="mt-3 space-y-2 text-xs">
                        <li v-for="sale in recent" :key="sale.id" class="flex justify-between gap-2"><Link :href="`/admin/sales/pos/${sale.id}`" class="min-w-0 truncate text-prinx-700 hover:underline">{{ sale.reference }}<span class="text-slate-400"> · {{ String(sale.created_at).slice(0, 10) }}</span></Link><span class="shrink-0 font-semibold">{{ money(sale.total) }}</span></li>
                    </ul>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
