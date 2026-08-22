<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ products: Array, categories: Array, customers: Array, heldSales: Array, resumeHold: Object, currentRegister: Object });
const page = usePage();
const search = ref('');
const category = ref('');
const showHeld = ref(false);
const showCustomer = ref(false);
const permissions = computed(() => page.props.auth.permissions || []);
const canDiscount = computed(() => permissions.value.includes('discount_pos'));
const canOverridePrice = computed(() => permissions.value.includes('override_pos_price'));
const canViewStock = computed(() => permissions.value.includes('view_inventory') || permissions.value.includes('manage_purchases'));
const money = (value) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: page.props.store.currency, maximumFractionDigits: 0 }).format(Number(value || 0));

const resumeItems = Array.isArray(props.resumeHold?.cart) ? props.resumeHold.cart : [];
const form = useForm({
    hold_id: props.resumeHold?.id || '',
    customer_id: props.resumeHold?.customer_id || '',
    customer_name: props.resumeHold?.customer_name || '',
    customer_phone: props.resumeHold?.customer_phone || '',
    customer_email: '', save_customer: false,
    discount: canDiscount.value ? Number(props.resumeHold?.discount || 0) : 0,
    amount_paid: '', payment_method: props.resumeHold?.payment_method || 'cash',
    payment_reference: props.resumeHold?.payment_reference || '', notes: props.resumeHold?.notes || '',
    items: resumeItems.map((item) => ({ product_id: Number(item.product_id), quantity: Number(item.quantity), unit_price: Number(item.unit_price) })),
});

const filtered = computed(() => props.products.filter((product) => {
    const q = search.value.trim().toLowerCase();
    if (category.value && product.category !== category.value) return false;
    return !q || `${product.name} ${product.sku}`.toLowerCase().includes(q);
}));
const subtotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_price), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));
const change = computed(() => Math.max(0, Number(form.amount_paid || 0) - total.value));
const cartCount = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

function product(id) { return props.products.find((item) => Number(item.id) === Number(id)); }
function addProduct(item) {
    if (!item.available) return;
    const existing = form.items.find((line) => Number(line.product_id) === Number(item.id));
    if (existing) { if (!item.track_stock || existing.quantity < item.stock_quantity) existing.quantity++; return; }
    form.items.push({ product_id: item.id, quantity: 1, unit_price: Number(item.price) });
}
function adjustQty(line, delta) {
    const item = product(line.product_id); const next = Number(line.quantity) + delta;
    if (next <= 0) return removeLine(line.product_id);
    if (item?.track_stock && next > item.stock_quantity) return;
    line.quantity = next;
}
function removeLine(id) { form.items = form.items.filter((line) => Number(line.product_id) !== Number(id)); }
function scanEnter() { const q = search.value.trim().toLowerCase(); if (!q) return; const exact = props.products.find((item) => String(item.sku || '').toLowerCase() === q); if (exact) { addProduct(exact); search.value = ''; } }
function selectCustomer() { const customer = props.customers.find((item) => Number(item.id) === Number(form.customer_id)); if (customer) { form.customer_name = customer.name; form.customer_phone = customer.phone || ''; form.customer_email = customer.email || ''; } }
function completeSale() { form.post('/admin/pos', { preserveScroll: true }); }
function holdSale() { if (!form.items.length) return; form.post('/admin/pos/hold', { preserveScroll: true }); }
function clearSale() { form.reset(); form.items = []; form.payment_method = 'cash'; form.discount = 0; }
</script>

<template>
    <AdminLayout>
        <Head title="Point of Sale" />
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div><p class="section-kicker">Counter sales</p><h1 class="section-title">Point of sale</h1><p class="mt-2 text-sm text-slate-500">Search, scan SKU, build the cart and complete a counter sale.</p></div>
            <div class="flex flex-wrap gap-2"><Link v-if="canViewStock" href="/admin/stock" class="btn-light"><Icon name="arrow-left" :size="16" /> Stock management</Link><Link href="/admin/registers" class="btn-light"><Icon name="wallet" :size="16" /> Register</Link><button class="btn-light" @click="showHeld=!showHeld"><Icon name="pause-circle" :size="17" /> Held sales <span v-if="heldSales.length">({{ heldSales.length }})</span></button></div>
        </div>

        <div v-if="!currentRegister" class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Open your cash register before completing a sale.</div>
        <div v-if="form.errors.pos" class="mt-4 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ form.errors.pos }}</div>
        <div v-if="resumeHold" class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">Resuming held sale {{ resumeHold.reference }}.</div>

        <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_390px]">
            <section>
                <div class="sticky top-[88px] z-10 rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm"><div class="relative"><Icon name="search-normal" :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="input pl-10" placeholder="Search product or scan SKU" autofocus @keyup.enter="scanEnter"></div><div class="mt-3 flex gap-2 overflow-x-auto pb-1"><button :class="['shrink-0 rounded-[8px] border px-3 py-2 text-xs font-semibold', category===''?'border-slate-950 bg-slate-950 text-white':'border-slate-200 bg-white text-slate-600']" @click="category=''">All</button><button v-for="item in categories" :key="item" :class="['shrink-0 rounded-[8px] border px-3 py-2 text-xs font-semibold', category===item?'border-slate-950 bg-slate-950 text-white':'border-slate-200 bg-white text-slate-600']" @click="category=item">{{ item }}</button></div></div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5"><button v-for="item in filtered" :key="item.id" type="button" :disabled="!item.available" class="overflow-hidden rounded-[10px] border border-slate-200 bg-white text-left transition hover:border-prinx-300 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50" @click="addProduct(item)"><img :src="item.image_url" :alt="item.name" class="aspect-square w-full object-cover"><div class="p-3"><p class="line-clamp-2 text-sm font-semibold text-slate-900">{{ item.name }}</p><p class="mt-1 text-[11px] text-slate-500">{{ item.sku }}</p><div class="mt-2 flex items-end justify-between gap-2"><b class="text-sm text-prinx-600">{{ money(item.price) }}</b><span :class="['text-[10px] font-semibold', item.available?'text-slate-500':'text-red-600']">{{ item.track_stock ? `${item.stock_quantity} left` : 'In stock' }}</span></div></div></button></div>
                <div v-if="!filtered.length" class="mt-10 text-center text-sm text-slate-400">No products match your search.</div>
            </section>

            <aside class="xl:sticky xl:top-[88px] xl:self-start"><form class="rounded-[10px] border border-slate-200 bg-white shadow-sm" @submit.prevent="completeSale">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><div><h2 class="font-semibold">Current sale</h2><p class="text-xs text-slate-500">{{ cartCount }} item{{ cartCount===1?'':'s' }}</p></div><button type="button" class="text-xs font-semibold text-red-600" @click="clearSale">Clear</button></div>
                <div class="max-h-[42vh] space-y-2 overflow-y-auto p-3"><div v-for="line in form.items" :key="line.product_id" class="rounded-[8px] border border-slate-200 p-3"><div class="flex gap-3"><img :src="product(line.product_id)?.image_url" class="h-12 w-12 rounded-[7px] object-cover"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ product(line.product_id)?.name }}</p><p class="text-[11px] text-slate-500">{{ product(line.product_id)?.sku }}</p><div class="mt-2 flex items-center justify-between gap-2"><div class="flex items-center rounded-[7px] border border-slate-200"><button type="button" class="h-8 w-8" @click="adjustQty(line,-1)">−</button><span class="w-8 text-center text-sm font-semibold">{{ line.quantity }}</span><button type="button" class="h-8 w-8" @click="adjustQty(line,1)">+</button></div><button type="button" class="text-red-500" @click="removeLine(line.product_id)"><Icon name="trash" :size="17" /></button></div></div></div><div class="mt-2 grid grid-cols-2 gap-2"><div><label class="text-[10px] font-semibold uppercase text-slate-400">Unit price</label><input v-model.number="line.unit_price" type="number" min="0" step="0.01" :disabled="!canOverridePrice" class="input h-9 py-1 disabled:bg-slate-100 disabled:text-slate-500"></div><div class="text-right"><p class="text-[10px] font-semibold uppercase text-slate-400">Line total</p><b class="mt-2 block text-sm">{{ money(line.quantity*line.unit_price) }}</b></div></div></div><div v-if="!form.items.length" class="py-10 text-center text-sm text-slate-400"><Icon name="shopping-cart" :size="28" class="mx-auto mb-2" />Add products to start a sale.</div></div>
                <div class="border-t border-slate-200 p-4">
                    <button type="button" class="mb-3 flex w-full items-center justify-between rounded-[8px] border border-slate-200 px-3 py-2 text-left text-sm" @click="showCustomer=!showCustomer"><span><b>{{ form.customer_name || 'Walk-in customer' }}</b><span v-if="form.customer_phone" class="ml-2 text-xs text-slate-500">{{ form.customer_phone }}</span></span><Icon name="profile" :size="17" /></button>
                    <div v-if="showCustomer" class="mb-3 space-y-2 rounded-[8px] bg-slate-50 p-3"><select v-model="form.customer_id" class="input" @change="selectCustomer"><option value="">Walk-in / new customer</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` · ${customer.phone}` : '' }}</option></select><div class="grid grid-cols-2 gap-2"><input v-model="form.customer_name" class="input" placeholder="Customer name"><input v-model="form.customer_phone" class="input" placeholder="Phone"></div><input v-model="form.customer_email" class="input" placeholder="Email (optional)"><label v-if="!form.customer_id" class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.save_customer" type="checkbox" class="mr-[10px]"> Save customer</label></div>
                    <div class="space-y-2 text-sm"><div class="flex justify-between"><span class="text-slate-500">Subtotal</span><b>{{ money(subtotal) }}</b></div><div class="flex items-center justify-between gap-4"><span class="text-slate-500">Discount</span><div class="text-right"><input v-model.number="form.discount" type="number" min="0" :max="subtotal" :disabled="!canDiscount" class="input h-9 w-32 py-1 text-right disabled:bg-slate-100 disabled:text-slate-500"><p v-if="!canDiscount" class="mt-1 text-[10px] text-slate-400">Not permitted</p></div></div><div class="flex justify-between border-t border-slate-200 pt-2 text-lg"><b>Total</b><b>{{ money(total) }}</b></div></div>
                    <div class="mt-4 grid grid-cols-2 gap-2"><select v-model="form.payment_method" class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank transfer</option><option value="card">Card</option><option value="other">Other</option></select><input v-model.number="form.amount_paid" type="number" min="0" step="0.01" class="input" placeholder="Amount paid"></div><input v-if="form.payment_method!=='cash'" v-model="form.payment_reference" class="input mt-2" placeholder="Payment reference"><div class="mt-2 flex justify-between rounded-[8px] bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-500">Change</span><b>{{ money(change) }}</b></div><input v-model="form.notes" class="input mt-2" placeholder="Sale note (optional)">
                    <div class="mt-4 grid grid-cols-[.8fr_1.2fr] gap-2"><button type="button" class="btn-light justify-center" :disabled="!form.items.length || form.processing" @click="holdSale"><Icon name="pause-circle" :size="17" /> Hold</button><button class="btn-primary justify-center" :disabled="!form.items.length || !currentRegister || form.processing"><Icon name="tick-circle" :size="18" /> Complete {{ money(total) }}</button></div>
                </div>
            </form></aside>
        </div>

        <div v-if="showHeld" class="fixed inset-0 z-[80] bg-slate-950/50 p-4" @click.self="showHeld=false"><div class="ml-auto h-full w-full max-w-md overflow-y-auto bg-white p-4 shadow-2xl"><div class="flex items-center justify-between"><div><p class="section-kicker">POS</p><h2 class="text-xl font-semibold">Held sales</h2></div><button class="h-9 w-9 rounded-[8px] border border-slate-200" @click="showHeld=false"><Icon name="close-circle" :size="18" /></button></div><div class="mt-4 space-y-2"><div v-for="hold in heldSales" :key="hold.id" class="rounded-[8px] border border-slate-200 p-3"><div class="flex justify-between gap-3"><div><b class="text-sm">{{ hold.reference }}</b><p class="mt-1 text-xs text-slate-500">{{ hold.customer_name || 'Walk-in customer' }} · {{ new Date(hold.created_at).toLocaleString() }}</p></div><div class="flex gap-1"><Link :href="`/admin/pos?hold=${hold.id}`" class="btn-light px-2 py-1.5 text-xs">Resume</Link><button class="rounded-[7px] px-2 text-red-600" @click="router.delete(`/admin/pos/hold/${hold.id}`)"><Icon name="trash" :size="16" /></button></div></div></div><div v-if="!heldSales.length" class="py-12 text-center text-sm text-slate-400">No held sales.</div></div></div></div>
    </AdminLayout>
</template>
