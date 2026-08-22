<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PosLayout from '../../../Layouts/PosLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    products: Array,
    categories: Array,
    brands: Array,
    customers: Array,
    heldSales: Array,
    resumeHold: Object,
    currentRegister: Object,
});

const page = usePage();
const scanSearch = ref('');
const catalogSearch = ref('');
const category = ref('');
const brand = ref('');
const showHeld = ref(false);
const showNewCustomer = ref(false);
const permissions = computed(() => page.props.auth.permissions || []);
const canDiscount = computed(() => permissions.value.includes('discount_pos'));
const canOverridePrice = computed(() => permissions.value.includes('override_pos_price'));
const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(Number(value || 0));
const newOperationId = () => globalThis.crypto?.randomUUID?.() || `pos-${Date.now()}-${Math.random().toString(36).slice(2)}`;

const resumeItems = Array.isArray(props.resumeHold?.cart) ? props.resumeHold.cart : [];
const form = useForm({
    _operation_id: newOperationId(),
    hold_id: props.resumeHold?.id || '',
    customer_id: props.resumeHold?.customer_id || '',
    customer_name: props.resumeHold?.customer_name || '',
    customer_phone: props.resumeHold?.customer_phone || '',
    customer_email: '',
    save_customer: false,
    discount: canDiscount.value ? Number(props.resumeHold?.discount || 0) : 0,
    amount_paid: '',
    payment_method: props.resumeHold?.payment_method || 'cash',
    payment_reference: props.resumeHold?.payment_reference || '',
    notes: props.resumeHold?.notes || '',
    items: resumeItems.map((item) => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
    })),
});

const filteredProducts = computed(() => props.products.filter((item) => {
    const q = catalogSearch.value.trim().toLowerCase();
    if (category.value && item.category !== category.value) return false;
    if (brand.value && item.brand !== brand.value) return false;
    return !q || `${item.name} ${item.sku} ${item.category || ''} ${item.brand || ''}`.toLowerCase().includes(q);
}));
const subtotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_price), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));
const change = computed(() => Math.max(0, Number(form.amount_paid || 0) - total.value));
const cartCount = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

function product(id) {
    return props.products.find((item) => Number(item.id) === Number(id));
}

function addProduct(item) {
    if (!item.available) return;
    const existing = form.items.find((line) => Number(line.product_id) === Number(item.id));
    if (existing) {
        if (!item.track_stock || existing.quantity < item.stock_quantity) existing.quantity++;
        return;
    }
    form.items.push({ product_id: item.id, quantity: 1, unit_price: Number(item.price) });
}

function adjustQty(line, delta) {
    const item = product(line.product_id);
    const next = Number(line.quantity) + delta;
    if (next <= 0) return removeLine(line.product_id);
    if (item?.track_stock && next > item.stock_quantity) return;
    line.quantity = next;
}

function removeLine(id) {
    form.items = form.items.filter((line) => Number(line.product_id) !== Number(id));
}

function scanEnter() {
    const q = scanSearch.value.trim().toLowerCase();
    if (!q) return;
    const exact = props.products.find((item) => String(item.sku || '').toLowerCase() === q)
        || props.products.find((item) => String(item.name || '').toLowerCase() === q);
    if (exact) {
        addProduct(exact);
        scanSearch.value = '';
    }
}

function selectCustomer() {
    const customer = props.customers.find((item) => Number(item.id) === Number(form.customer_id));
    if (customer) {
        form.customer_name = customer.name;
        form.customer_phone = customer.phone || '';
        form.customer_email = customer.email || '';
        showNewCustomer.value = false;
    } else {
        form.customer_name = '';
        form.customer_phone = '';
        form.customer_email = '';
    }
}

function completeSale() {
    form.post('/admin/pos', {
        preserveScroll: true,
        onError: () => { form._operation_id = newOperationId(); },
    });
}

function payExactCash() {
    if (!form.items.length || !props.currentRegister || form.processing) return;
    form.payment_method = 'cash';
    form.amount_paid = total.value;
    form.payment_reference = '';
    completeSale();
}

function holdSale() {
    if (!form.items.length || form.processing) return;
    form.post('/admin/pos/hold', { preserveScroll: true });
}

function clearSale() {
    form.reset();
    form.hold_id = '';
    form.items = [];
    form.customer_id = '';
    form.customer_name = '';
    form.customer_phone = '';
    form.customer_email = '';
    form.payment_method = 'cash';
    form.payment_reference = '';
    form.amount_paid = '';
    form.discount = 0;
    form._operation_id = newOperationId();
    scanSearch.value = '';
}
</script>

<template>
    <PosLayout>
        <Head title="Point of Sale" />

        <template #header-actions>
            <button type="button" class="flex h-9 items-center gap-1.5 rounded-[7px] px-2.5 text-xs font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white" @click="showHeld = true">
                <Icon name="pause-circle" :size="16" />
                <span class="hidden sm:inline">Held</span>
                <span v-if="heldSales.length" class="rounded-full bg-red-500 px-1.5 py-0.5 text-[9px] font-semibold text-white">{{ heldSales.length }}</span>
            </button>
        </template>

        <div v-if="form.errors.pos" class="mb-2 flex items-center gap-2 rounded-[8px] border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
            <Icon name="danger" :size="16" class="shrink-0" />{{ form.errors.pos }}
        </div>
        <div v-if="resumeHold" class="mb-2 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
            Resuming held sale {{ resumeHold.reference }}.
        </div>

        <div class="grid gap-2 lg:h-[calc(100vh-82px)] lg:min-h-[620px] lg:grid-cols-[minmax(0,7fr)_minmax(360px,5fr)]">
            <!-- LEFT: SALES INVOICE -->
            <section class="order-2 flex min-h-[620px] min-w-0 flex-col overflow-hidden rounded-[9px] border border-slate-200 bg-white shadow-sm lg:order-1 lg:min-h-0">
                <div class="flex h-11 shrink-0 items-center justify-between border-b border-slate-200 px-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-[7px] bg-prinx-50 text-prinx-600"><Icon name="shopping-cart" :size="16" /></span>
                        <div><h1 class="text-sm font-semibold text-slate-950">Sales invoice</h1><p class="text-[10px] text-slate-400">{{ cartCount }} unit{{ cartCount === 1 ? '' : 's' }} in current sale</p></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="!currentRegister" href="/admin/registers" class="rounded-[7px] bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">Open register</Link>
                        <span v-else class="inline-flex items-center gap-1 rounded-[7px] bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ currentRegister.reference }}</span>
                        <button v-if="form.items.length" type="button" class="rounded-[7px] px-2 py-1 text-[10px] font-semibold text-red-600 hover:bg-red-50" @click="clearSale">Clear</button>
                    </div>
                </div>

                <div class="shrink-0 border-b border-slate-200 p-2.5">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="flex min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-l-[8px] border border-r-0 border-slate-300 bg-slate-50 text-slate-500"><Icon name="profile" :size="16" /></span>
                            <select v-model="form.customer_id" class="h-9 min-w-0 flex-1 rounded-none border border-slate-300 bg-white px-2 text-xs outline-none focus:border-prinx-500" @change="selectCustomer">
                                <option value="">Walk-in customer</option>
                                <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` · ${customer.phone}` : '' }}</option>
                            </select>
                            <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-r-[8px] border border-l-0 border-slate-300 text-prinx-600 hover:bg-prinx-50" title="New customer" @click="showNewCustomer = !showNewCustomer"><Icon name="profile-add" :size="16" /></button>
                        </div>

                        <div class="flex min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-l-[8px] border border-r-0 border-slate-300 bg-slate-50 text-slate-500"><Icon name="scanner" :size="16" /></span>
                            <input v-model="scanSearch" class="h-9 min-w-0 flex-1 rounded-r-[8px] border border-slate-300 px-2.5 text-xs outline-none placeholder:text-slate-400 focus:border-prinx-500" placeholder="Item name / SKU / barcode" autofocus @keyup.enter="scanEnter">
                        </div>
                    </div>

                    <div v-if="showNewCustomer" class="mt-2 grid gap-2 rounded-[8px] bg-slate-50 p-2 sm:grid-cols-3">
                        <input v-model="form.customer_name" class="input" placeholder="Customer name">
                        <input v-model="form.customer_phone" class="input" placeholder="Phone">
                        <input v-model="form.customer_email" class="input" placeholder="Email">
                        <label class="flex items-center gap-2 text-[11px] text-slate-600 sm:col-span-3"><input v-model="form.save_customer" type="checkbox"> Save this customer</label>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full min-w-[720px] border-collapse text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-950 text-white">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Item</th>
                                <th class="w-20 px-2 py-2 text-center font-semibold">Stock</th>
                                <th class="w-32 px-2 py-2 text-center font-semibold">Quantity</th>
                                <th class="w-32 px-2 py-2 text-right font-semibold">Price</th>
                                <th class="w-32 px-2 py-2 text-right font-semibold">Subtotal</th>
                                <th class="w-10 px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="line in form.items" :key="line.product_id" class="hover:bg-slate-50">
                                <td class="px-3 py-2.5"><p class="font-semibold text-slate-900">{{ product(line.product_id)?.name }}</p><p class="mt-0.5 text-[10px] text-slate-400">{{ product(line.product_id)?.sku }}</p></td>
                                <td class="px-2 py-2.5 text-center text-slate-500">{{ product(line.product_id)?.track_stock ? product(line.product_id)?.stock_quantity : '∞' }}</td>
                                <td class="px-2 py-2.5"><div class="mx-auto flex w-fit items-center overflow-hidden rounded-[7px] border border-slate-200"><button type="button" class="h-8 w-8 text-base text-red-500 hover:bg-red-50" @click="adjustQty(line, -1)">−</button><span class="min-w-9 px-1 text-center font-semibold">{{ line.quantity }}</span><button type="button" class="h-8 w-8 text-base text-emerald-600 hover:bg-emerald-50" @click="adjustQty(line, 1)">+</button></div></td>
                                <td class="px-2 py-2.5 text-right"><input v-model.number="line.unit_price" type="number" min="0" step="0.01" :disabled="!canOverridePrice" class="h-8 w-28 rounded-[7px] border border-slate-200 px-2 text-right text-xs outline-none focus:border-prinx-500 disabled:bg-transparent disabled:text-slate-700"></td>
                                <td class="px-2 py-2.5 text-right font-semibold text-slate-900">{{ money(line.quantity * line.unit_price) }}</td>
                                <td class="px-2 py-2.5 text-center"><button type="button" class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-500 hover:bg-red-50" @click="removeLine(line.product_id)"><Icon name="trash" :size="15" /></button></td>
                            </tr>
                            <tr v-if="!form.items.length"><td colspan="6" class="py-16 text-center"><Icon name="shopping-cart" :size="28" class="mx-auto text-slate-300" /><p class="mt-2 text-sm font-semibold text-slate-500">Invoice is empty</p><p class="mt-1 text-xs text-slate-400">Choose an item from the product panel.</p></td></tr>
                        </tbody>
                    </table>
                </div>

                <form id="pos-checkout-form" class="shrink-0 border-t border-slate-200 bg-slate-50/80" @submit.prevent="completeSale">
                    <div class="grid grid-cols-2 divide-x divide-y divide-slate-200 border-b border-slate-200 sm:grid-cols-4 sm:divide-y-0">
                        <div class="px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Quantity</p><b class="mt-0.5 block text-base text-slate-800">{{ cartCount }}</b></div>
                        <div class="px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Total amount</p><b class="mt-0.5 block text-base text-slate-800">{{ money(subtotal) }}</b></div>
                        <div class="px-3 py-2 text-right"><div class="flex items-center justify-end gap-1"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-slate-400">Discount</p><Icon name="edit-2" :size="11" class="text-prinx-500" /></div><input v-model.number="form.discount" type="number" min="0" :max="subtotal" :disabled="!canDiscount" class="mt-0.5 h-6 w-24 rounded-[5px] border border-slate-200 bg-white px-1.5 text-right text-sm font-semibold outline-none disabled:border-transparent disabled:bg-transparent"></div>
                        <div class="px-3 py-2 text-right"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-prinx-600">Grand total</p><b class="mt-0.5 block text-lg text-prinx-600">{{ money(total) }}</b></div>
                    </div>

                    <div class="grid gap-2 p-2.5 md:grid-cols-[150px_150px_1fr_auto]">
                        <select v-model="form.payment_method" class="input"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank transfer</option><option value="card">Card</option><option value="other">Other</option></select>
                        <input v-model.number="form.amount_paid" type="number" min="0" step="0.01" class="input" placeholder="Amount paid">
                        <input v-if="form.payment_method !== 'cash'" v-model="form.payment_reference" class="input" placeholder="Payment reference">
                        <div v-else class="flex h-9 items-center justify-between rounded-[8px] border border-slate-200 bg-white px-3 text-xs"><span class="text-slate-400">Change</span><b class="ml-4">{{ money(change) }}</b></div>
                        <input v-model="form.notes" class="input md:col-span-3" placeholder="Sale note (optional)">
                        <div class="grid grid-cols-3 gap-2 md:col-span-4">
                            <button type="button" class="flex h-10 items-center justify-center gap-1.5 rounded-[8px] bg-rose-700 px-3 text-xs font-semibold text-white transition hover:bg-rose-800 disabled:opacity-50" :disabled="!form.items.length || form.processing" @click="holdSale"><Icon name="pause-circle" :size="16" />Hold</button>
                            <button type="button" class="flex h-10 items-center justify-center gap-1.5 rounded-[8px] bg-emerald-600 px-3 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50" :disabled="!form.items.length || !currentRegister || form.processing" @click="payExactCash"><Icon name="wallet-money" :size="16" />Exact cash</button>
                            <button type="submit" class="flex h-10 items-center justify-center gap-1.5 rounded-[8px] bg-prinx-500 px-3 text-xs font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!form.items.length || !currentRegister || form.processing"><Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="16" />{{ form.processing ? 'Processing...' : 'Complete sale' }}</button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- RIGHT: PRODUCT BROWSER -->
            <aside class="order-1 flex min-h-[470px] min-w-0 flex-col overflow-hidden rounded-[9px] border border-slate-200 bg-white shadow-sm lg:order-2 lg:min-h-0">
                <div class="shrink-0 border-b border-slate-200 p-2.5">
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="category" class="input"><option value="">All categories</option><option v-for="item in categories" :key="item" :value="item">{{ item }}</option></select>
                        <select v-model="brand" class="input"><option value="">All brands</option><option v-for="item in brands" :key="item" :value="item">{{ item }}</option></select>
                    </div>
                    <div class="relative mt-2"><Icon name="search-normal" :size="16" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="catalogSearch" class="input pl-8" placeholder="Item name / SKU"></div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2.5">
                    <div class="grid grid-cols-2 gap-2 2xl:grid-cols-3">
                        <button v-for="item in filteredProducts" :key="item.id" type="button" :disabled="!item.available" class="group min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-white text-left transition hover:border-prinx-300 hover:shadow-sm active:scale-[.99] disabled:cursor-not-allowed disabled:opacity-45" @click="addProduct(item)">
                            <div class="flex gap-2 p-2">
                                <img :src="item.image_url" :alt="item.name" class="h-[62px] w-[62px] shrink-0 rounded-[7px] bg-slate-100 object-cover">
                                <div class="min-w-0 flex-1"><p class="line-clamp-2 text-[11px] font-semibold leading-4 text-slate-900">{{ item.name }}</p><p class="mt-0.5 truncate text-[9px] text-slate-400">{{ item.sku }}</p><p class="mt-1 text-xs font-semibold text-prinx-600">{{ money(item.price) }}</p></div>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-2 py-1.5"><span class="truncate text-[9px] text-slate-400">{{ item.brand || item.category }}</span><span :class="['shrink-0 text-[9px] font-semibold', item.available ? 'text-slate-500' : 'text-red-600']">{{ item.track_stock ? `${item.stock_quantity} left` : 'In stock' }}</span></div>
                        </button>
                    </div>
                    <div v-if="!filteredProducts.length" class="flex min-h-48 flex-col items-center justify-center text-center"><Icon name="search-normal" :size="28" class="text-slate-300" /><p class="mt-2 text-sm font-semibold text-slate-500">No matching items</p><p class="mt-1 text-xs text-slate-400">Change the category, brand or search term.</p></div>
                </div>
            </aside>
        </div>

        <div v-if="showHeld" class="fixed inset-0 z-[90] bg-slate-950/55 p-0 sm:p-3" @click.self="showHeld = false">
            <div class="ml-auto h-full w-full max-w-md overflow-y-auto bg-white p-3 shadow-2xl sm:rounded-[9px]">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3"><div><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-prinx-600">POS</p><h2 class="mt-0.5 text-lg font-semibold">Held invoices</h2></div><button class="flex h-9 w-9 items-center justify-center rounded-[7px] border border-slate-200" @click="showHeld = false"><Icon name="close-circle" :size="17" /></button></div>
                <div class="mt-3 space-y-2">
                    <div v-for="hold in heldSales" :key="hold.id" class="rounded-[8px] border border-slate-200 p-3"><div class="flex justify-between gap-3"><div class="min-w-0"><b class="text-xs">{{ hold.reference }}</b><p class="mt-1 text-[10px] text-slate-500">{{ hold.customer_name || 'Walk-in customer' }} · {{ new Date(hold.created_at).toLocaleString() }}</p></div><div class="flex shrink-0 gap-1"><Link :href="`/admin/pos?hold=${hold.id}`" class="flex h-8 items-center rounded-[7px] border border-slate-200 px-2 text-[10px] font-semibold">Resume</Link><button class="flex h-8 w-8 items-center justify-center rounded-[7px] text-red-600 hover:bg-red-50" @click="router.delete(`/admin/pos/hold/${hold.id}`)"><Icon name="trash" :size="14" /></button></div></div></div>
                    <div v-if="!heldSales.length" class="py-12 text-center text-xs text-slate-400">No held invoices.</div>
                </div>
            </div>
        </div>
    </PosLayout>
</template>
