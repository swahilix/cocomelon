<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ cart: Object });
const page = usePage();
const store = computed(() => page.props.store || {});
const termsAccepted = ref(false);
const saveInformation = ref(false);
const termsError = ref('');
const couponCode = ref(props.cart?.coupon?.code || '');
const couponProcessing = ref(false);

const form = useForm({
    customer_name: page.props.auth.user?.name || '',
    email: page.props.auth.user?.email || '',
    phone: page.props.auth.user?.phone || '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    region: '',
    notes: '',
    fulfillment_method: page.props.store?.delivery_enabled !== false ? 'delivery' : 'pickup',
    payment_method: store.value.payment_methods?.[0]?.value || '',
});

const money = (value) => new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: page.props.store.currency,
    maximumFractionDigits: 0,
}).format(value || 0);

const itemLabel = computed(() => `${props.cart.count} ${Number(props.cart.count) === 1 ? 'item' : 'items'}`);
const isPickup = computed(() => form.fulfillment_method === 'pickup');
const displayedShipping = computed(() => isPickup.value ? 0 : Number(props.cart.shipping || 0));
const displayedTotal = computed(() => Math.max(0, Number(props.cart.subtotal || 0) + displayedShipping.value - Number(props.cart.discount || 0)));

const paymentMethods = computed(() => store.value.payment_methods || []);

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem('prinx-checkout-profile') || 'null');
        if (!saved) return;
        saveInformation.value = true;
        if (!form.customer_name) form.customer_name = saved.customer_name || '';
        if (!form.email) form.email = saved.email || '';
        if (!form.phone) form.phone = saved.phone || '';
        form.address_line_1 = saved.address_line_1 || '';
        form.address_line_2 = saved.address_line_2 || '';
        form.city = saved.city || '';
        form.region = saved.region || '';
    } catch (_) {
        localStorage.removeItem('prinx-checkout-profile');
    }
});

const applyCoupon = () => {
    const code = couponCode.value.trim();
    if (!code || couponProcessing.value) return;
    router.post('/cart/coupon', { code }, {
        preserveScroll: true,
        onStart: () => { couponProcessing.value = true; },
        onFinish: () => { couponProcessing.value = false; },
    });
};

const removeCoupon = () => {
    if (couponProcessing.value) return;
    router.delete('/cart/coupon', {
        preserveScroll: true,
        onStart: () => { couponProcessing.value = true; },
        onSuccess: () => { couponCode.value = ''; },
        onFinish: () => { couponProcessing.value = false; },
    });
};

const submit = () => {
    termsError.value = '';
    if (!termsAccepted.value) {
        termsError.value = 'Please agree to the Terms & Conditions and Privacy Policy.';
        return;
    }

    if (saveInformation.value) {
        localStorage.setItem('prinx-checkout-profile', JSON.stringify({
            customer_name: form.customer_name,
            email: form.email,
            phone: form.phone,
            address_line_1: form.address_line_1,
            address_line_2: form.address_line_2,
            city: form.city,
            region: form.region,
        }));
    } else {
        localStorage.removeItem('prinx-checkout-profile');
    }

    form.post('/checkout', { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <Head title="Checkout">
            <meta head-key="robots" name="robots" content="noindex,nofollow" />
        </Head>

        <main class="bg-[#fbfbfb]">
            <div class="container-shell pb-8 pt-4 sm:pt-5">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                    <span>/</span>
                    <Link href="/cart" class="transition hover:text-prinx-600">Cart</Link>
                    <span>/</span>
                    <span class="text-prinx-600">Checkout</span>
                </nav>

                <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px] xl:grid-cols-[minmax(0,1fr)_410px]">
                    <div class="min-w-0">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h1 class="text-[28px] font-semibold tracking-[-.03em] text-slate-950 sm:text-[32px]">Checkout</h1>
                                <p class="mt-1 text-xs text-slate-500">Almost there! Please fill in your details and confirm your order.</p>
                            </div>

                            <div class="hidden min-w-[430px] items-start sm:flex">
                                <div class="flex flex-1 items-start">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full border border-prinx-400 bg-white text-xs font-semibold text-prinx-600">1</span>
                                        <span class="text-xs font-medium text-slate-600">Cart</span>
                                    </div>
                                    <span class="mt-[13px] h-px flex-1 bg-prinx-400"></span>
                                </div>
                                <div class="flex flex-1 items-start">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-prinx-500 text-xs font-semibold text-white">2</span>
                                        <span class="text-xs font-semibold text-slate-900">Checkout</span>
                                    </div>
                                    <span class="mt-[13px] h-px flex-1 bg-slate-200"></span>
                                </div>
                                <div class="flex flex-1 items-start">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-500">3</span>
                                        <span class="text-xs font-medium text-slate-500">Payment</span>
                                    </div>
                                    <span class="mt-[13px] h-px flex-1 bg-slate-200"></span>
                                </div>
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-500">4</span>
                                    <span class="text-xs font-medium text-slate-500">Complete</span>
                                </div>
                            </div>
                        </div>

                        <form class="mt-5 overflow-hidden rounded-[9px] border border-slate-200 bg-white" @submit.prevent="submit">
                            <section class="border-b border-slate-200 p-4 sm:p-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-prinx-500 text-xs font-semibold text-white">1</span>
                                    <h2 class="text-base font-semibold text-slate-950">Customer Information</h2>
                                </div>

                                <div class="mt-4 grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Full Name <span class="text-prinx-500">*</span></label>
                                        <input v-model="form.customer_name" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="John Doe">
                                        <p v-if="form.errors.customer_name" class="mt-1.5 text-xs text-red-600">{{ form.errors.customer_name }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Phone Number <span class="text-prinx-500">*</span></label>
                                        <div class="flex h-11 overflow-hidden rounded-[6px] border border-slate-200 bg-white transition focus-within:border-prinx-400">
                                            <span class="flex shrink-0 items-center gap-1.5 border-r border-slate-100 px-3 text-xs text-slate-600"><span aria-hidden="true">🇹🇿</span><span>+255</span></span>
                                            <input v-model="form.phone" class="min-w-0 flex-1 border-0 px-3 text-sm text-slate-900 outline-none" placeholder="712 345 678">
                                        </div>
                                        <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-600">{{ form.errors.phone }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Email Address <span class="text-prinx-500">*</span></label>
                                        <input v-model="form.email" type="email" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="johndoe@email.com">
                                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                                    </div>
                                </div>
                            </section>

                            <section class="border-b border-slate-200 p-4 sm:p-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-prinx-500 text-xs font-semibold text-white">2</span>
                                    <h2 class="text-base font-semibold text-slate-950">{{ isPickup ? 'Pickup Details' : 'Delivery Address' }}</h2>
                                </div>

                                <div v-if="!isPickup" class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Region / City <span class="text-prinx-500">*</span></label>
                                        <input v-model="form.region" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="e.g. Dodoma">
                                        <p v-if="form.errors.region" class="mt-1.5 text-xs text-red-600">{{ form.errors.region }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Area / District <span class="text-prinx-500">*</span></label>
                                        <input v-model="form.city" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="e.g. City Centre">
                                        <p v-if="form.errors.city" class="mt-1.5 text-xs text-red-600">{{ form.errors.city }}</p>
                                    </div>
                                    <div class="md:col-span-2 lg:col-span-1">
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Street Address <span class="text-prinx-500">*</span></label>
                                        <input v-model="form.address_line_1" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="Street, building or landmark">
                                        <p v-if="form.errors.address_line_1" class="mt-1.5 text-xs text-red-600">{{ form.errors.address_line_1 }}</p>
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Apartment, suite, unit <span class="text-slate-400">(optional)</span></label>
                                        <input v-model="form.address_line_2" class="h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="Apartment / office">
                                    </div>
                                    <div class="md:col-span-1 lg:col-span-2">
                                        <label class="mb-2 block text-xs font-medium text-slate-800">Delivery Notes <span class="text-slate-400">(optional)</span></label>
                                        <textarea v-model="form.notes" rows="2" class="min-h-11 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-prinx-400" placeholder="Any delivery instructions"></textarea>
                                    </div>
                                </div>
                                <div v-else class="mt-4 rounded-[8px] border border-emerald-200 bg-emerald-50 p-4">
                                    <strong class="block text-sm font-semibold text-emerald-900">Collect from Prinx Tech</strong>
                                    <p class="mt-1 text-xs leading-5 text-emerald-800">{{ store.pickup_location || store.address || 'Store pickup location' }}</p>
                                    <p v-if="store.pickup_instructions" class="mt-1 text-xs text-emerald-700">{{ store.pickup_instructions }}</p>
                                    <p v-if="store.working_hours" class="mt-1 text-xs text-emerald-700">{{ store.working_hours }}</p>
                                </div>
                            </section>

                            <section class="border-b border-slate-200 p-4 sm:p-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-prinx-500 text-xs font-semibold text-white">3</span>
                                    <h2 class="text-base font-semibold text-slate-950">Delivery Method</h2>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <button
                                        v-if="store.delivery_enabled"
                                        type="button"
                                        :class="['flex min-h-[92px] items-center gap-3 rounded-[8px] border p-4 text-left transition', form.fulfillment_method === 'delivery' ? 'border-prinx-400 bg-prinx-50/35' : 'border-slate-200 bg-white hover:border-prinx-200']"
                                        @click="form.fulfillment_method = 'delivery'"
                                    >
                                        <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2', form.fulfillment_method === 'delivery' ? 'border-prinx-500' : 'border-slate-300']"><span v-if="form.fulfillment_method === 'delivery'" class="h-2.5 w-2.5 rounded-full bg-prinx-500"></span></span>
                                        <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-slate-600" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                        <span class="min-w-0 flex-1"><strong class="block text-xs font-semibold text-slate-900">Delivery</strong><span class="mt-1 block text-xs leading-5 text-slate-500">{{ store.delivery_eta || 'Delivery time confirmed with your order' }}<template v-if="store.delivery_area"> · {{ store.delivery_area }}</template></span></span>
                                        <strong class="text-xs font-semibold text-prinx-600">{{ cart.shipping ? money(cart.shipping) : 'Included' }}</strong>
                                    </button>

                                    <button
                                        v-if="store.pickup_enabled"
                                        type="button"
                                        :class="['flex min-h-[92px] items-center gap-3 rounded-[8px] border p-4 text-left transition', form.fulfillment_method === 'pickup' ? 'border-emerald-400 bg-emerald-50/60' : 'border-slate-200 bg-white hover:border-emerald-200']"
                                        @click="form.fulfillment_method = 'pickup'"
                                    >
                                        <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2', form.fulfillment_method === 'pickup' ? 'border-emerald-500' : 'border-slate-300']"><span v-if="form.fulfillment_method === 'pickup'" class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span>
                                        <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-slate-600" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16l-1 11H5L4 9Zm1-5h14l2 5H3l2-5Z"/></svg>
                                        <span class="min-w-0 flex-1"><strong class="block text-xs font-semibold text-slate-900">Store Pickup</strong><span class="mt-1 block text-xs leading-5 text-slate-500">{{ store.pickup_location || store.address || 'Prinx Tech store' }}</span></span>
                                        <strong class="text-xs font-semibold text-emerald-700">Free</strong>
                                    </button>
                                </div>
                                <p v-if="form.errors.fulfillment_method" class="mt-2 text-xs text-red-600">{{ form.errors.fulfillment_method }}</p>
                            </section>

                            <section class="border-b border-slate-200 p-4 sm:p-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-prinx-500 text-xs font-semibold text-white">4</span>
                                    <h2 class="text-base font-semibold text-slate-950">Payment Method</h2>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    <button
                                        v-for="method in paymentMethods"
                                        :key="method.value"
                                        type="button"
                                        :class="['min-h-[100px] rounded-[8px] border p-4 text-left transition', form.payment_method === method.value ? 'border-prinx-400 bg-prinx-50/35' : 'border-slate-200 bg-white hover:border-prinx-200']"
                                        @click="form.payment_method = method.value"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span :class="['mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2', form.payment_method === method.value ? 'border-prinx-500' : 'border-slate-300']"><span v-if="form.payment_method === method.value" class="h-2.5 w-2.5 rounded-full bg-prinx-500"></span></span>
                                            <svg v-if="method.icon === 'phone'" viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-slate-600" stroke-width="1.6"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M10 5h4M11 19h2"/></svg>
                                            <svg v-else-if="method.icon === 'bank'" viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-slate-600" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18M5 9v8m4-8v8m6-8v8m4-8v8M3 18h18M12 3l9 4H3l9-4Z"/></svg>
                                            <svg v-else viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-slate-600" stroke-width="1.6"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9h.01M18 15h.01"/></svg>
                                            <span class="min-w-0"><strong class="block text-xs font-semibold text-slate-900">{{ method.title }}</strong><span class="mt-1 block text-xs leading-5 text-slate-500">{{ method.description }}</span></span>
                                        </div>
                                    </button>
                                </div>
                                <p v-if="form.errors.payment_method" class="mt-2 text-xs text-red-600">{{ form.errors.payment_method }}</p>
                                <div v-if="store.payment_safety_note" class="mt-3 flex items-start gap-2 rounded-[7px] border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs leading-5 text-emerald-800">
                                    <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg>
                                    <span>{{ store.payment_safety_note }}</span>
                                </div>
                            </section>

                            <div class="space-y-3 p-4 sm:p-5">
                                <label class="flex cursor-pointer items-center gap-3 text-xs text-slate-700">
                                    <input v-model="saveInformation" type="checkbox" class="h-4 w-4 rounded border-slate-300 accent-prinx-500">
                                    <span>Save this information on this device for faster checkout next time</span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 text-xs text-slate-700">
                                    <input v-model="termsAccepted" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-prinx-500" @change="termsError = ''">
                                    <span>I agree to the <Link href="/pages/terms" class="font-semibold text-prinx-600 hover:text-prinx-700">Terms & Conditions</Link> and <Link href="/pages/privacy" class="font-semibold text-prinx-600 hover:text-prinx-700">Privacy Policy</Link> <span class="text-prinx-500">*</span></span>
                                </label>
                                <p v-if="termsError" class="text-xs text-red-600">{{ termsError }}</p>
                                <p v-if="form.errors.checkout" class="rounded-[6px] bg-red-50 px-3 py-2 text-xs text-red-700">{{ form.errors.checkout }}</p>
                            </div>
                        </form>
                    </div>

                    <aside class="h-fit rounded-[9px] border border-slate-200 bg-white p-4 lg:sticky lg:top-5 sm:p-5">
                        <h2 class="text-xl font-semibold tracking-[-.02em] text-slate-950">Order Summary</h2>

                        <div class="mt-4 divide-y divide-slate-100">
                            <div v-for="line in cart.items" :key="line.cart_key" class="flex items-center gap-3 py-3 first:pt-0">
                                <img :src="line.image_url || line.product.image_url" :alt="line.product.name" class="h-[62px] w-[62px] shrink-0 rounded-[6px] bg-slate-50 object-contain p-1.5">
                                <div class="min-w-0 flex-1">
                                    <Link :href="`/products/${line.product.slug}`" class="line-clamp-1 text-sm font-semibold text-slate-900 transition hover:text-prinx-600">{{ line.product.name }}</Link>
                                    <p v-if="line.variant" class="mt-1 line-clamp-1 text-xs font-medium text-prinx-600">{{ line.variant.display_name }}</p>
                                    <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ line.variant?.sku || line.product.sku }} · Qty: {{ line.quantity }}</p>
                                </div>
                                <strong class="shrink-0 text-xs font-semibold text-slate-900">{{ money(line.line_total) }}</strong>
                            </div>
                        </div>

                        <div class="mt-2 space-y-3 border-t border-slate-200 pt-4 text-xs">
                            <div class="flex items-center justify-between"><span class="text-slate-600">Subtotal ({{ itemLabel }})</span><strong class="text-slate-900">{{ money(cart.subtotal) }}</strong></div>
                            <div class="flex items-center justify-between"><span class="text-slate-600">{{ isPickup ? 'Store Pickup' : 'Estimated Shipping' }}</span><strong class="text-slate-900">{{ isPickup ? 'Free' : (displayedShipping ? money(displayedShipping) : 'Included') }}</strong></div>
                            <div v-if="cart.discount" class="flex items-center justify-between text-emerald-700"><span>Coupon discount <strong v-if="cart.coupon">({{ cart.coupon.code }})</strong></span><strong>-{{ money(cart.discount) }}</strong></div>
                        </div>

                        <div class="mt-4 flex items-end justify-between border-t border-slate-200 pt-4">
                            <div><p class="text-base font-semibold text-slate-950">Grand Total</p><p class="mt-1 text-xs text-slate-400">Taxes confirmed where applicable</p></div>
                            <strong class="text-xl font-semibold text-prinx-600">{{ money(displayedTotal) }}</strong>
                        </div>

                        <div v-if="cart.coupon" class="mt-4 flex items-center justify-between gap-3 rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                            <div class="min-w-0"><p class="text-[10px] font-semibold uppercase tracking-[.08em] text-emerald-700">Coupon applied</p><p class="mt-1 truncate text-xs font-semibold text-slate-800">{{ cart.coupon.code }} · Save {{ money(cart.discount) }}</p></div>
                            <button type="button" class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700 disabled:opacity-50" :disabled="couponProcessing" @click="removeCoupon">Remove</button>
                        </div>
                        <form v-else class="mt-4 flex h-10 overflow-hidden rounded-[6px] border border-slate-200 bg-white" @submit.prevent="applyCoupon">
                            <input v-model="couponCode" class="min-w-0 flex-1 border-0 bg-transparent px-3 text-xs uppercase outline-none placeholder:normal-case" placeholder="Enter coupon code">
                            <button type="submit" class="mr-[2px] mt-[2px] h-[34px] w-[82px] rounded-[5px] bg-prinx-500 text-xs font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:opacity-50" :disabled="couponProcessing || !couponCode.trim()">{{ couponProcessing ? 'Applying...' : 'Apply' }}</button>
                        </form>

                        <div class="mt-4 flex items-start gap-3 rounded-[8px] bg-prinx-50 px-4 py-3.5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] bg-white text-prinx-600">
                                <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                            </span>
                            <div v-if="!isPickup"><strong class="block text-xs font-semibold text-prinx-700">Estimated Delivery</strong><span class="mt-1 block text-xs font-medium text-slate-800">{{ store.delivery_eta || 'Delivery time confirmed with your order' }}</span><span class="mt-1 block text-xs text-slate-500">{{ store.delivery_area || store.address || 'Tanzania' }}</span></div><div v-else><strong class="block text-xs font-semibold text-prinx-700">Store Pickup</strong><span class="mt-1 block text-xs font-medium text-slate-800">{{ store.pickup_location || store.address || 'Prinx Tech store' }}</span><span v-if="store.pickup_instructions" class="mt-1 block text-xs text-slate-500">{{ store.pickup_instructions }}</span></div>
                        </div>

                        <button type="button" class="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-5 text-sm font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing" @click="submit">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            {{ form.processing ? 'Placing Order...' : 'Place Order' }}
                        </button>
                        <Link href="/cart" class="mt-2.5 flex h-11 w-full items-center justify-center gap-2 rounded-[6px] border border-prinx-300 bg-white px-5 text-xs font-semibold text-prinx-700 transition hover:bg-prinx-50">← Return to Cart</Link>

                        <div v-if="store.payment_strip_url" class="mt-5 flex items-center justify-center gap-3 border-t border-slate-100 pt-4">
                            <span class="text-xs text-slate-500">We accept</span>
                            <img :src="store.payment_strip_url" alt="Accepted payment methods" class="h-auto max-h-[30px] w-auto max-w-[230px] object-contain">
                        </div>
                    </aside>
                </div>

                <section class="mt-5 grid gap-3 rounded-[8px] border border-prinx-100 bg-gradient-to-r from-[#fff8f4] via-white to-[#fff8f4] p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="flex items-center justify-center gap-3 sm:justify-start lg:justify-center">
                        <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15" r="1"/></svg>
                        <div><strong class="block text-xs font-semibold text-slate-900">Payment Options</strong><span class="mt-1 block text-xs text-slate-500">{{ paymentMethods.map((method) => method.title).join(' · ') }}</span></div>
                    </div>
                    <div class="flex items-center justify-center gap-3 sm:justify-start lg:justify-center">
                        <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.3 2 3-.2.7 3 2.5 1.7-1.2 2.7 1.2 2.7-2.5 1.7-.7 3-3-.2-2.3 2-2.3-2-3 .2-.7-3-2.5-1.7 1.2-2.7-1.2-2.7L6 7.8l.7-3 3 .2L12 3Z"/><path d="m9 12 2 2 4-4"/></svg>
                        <div><strong class="block text-xs font-semibold text-slate-900">Genuine Products</strong><span class="mt-1 block text-xs text-slate-500">Official warranty where offered</span></div>
                    </div>
                    <div class="flex items-center justify-center gap-3 sm:justify-start lg:justify-center">
                        <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        <div><strong class="block text-xs font-semibold text-slate-900">Fast Delivery</strong><span class="mt-1 block text-xs text-slate-500">Fast local delivery options</span></div>
                    </div>
                    <div class="flex items-center justify-center gap-3 sm:justify-start lg:justify-center">
                        <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 fill-none stroke-prinx-500" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                        <div><strong class="block text-xs font-semibold text-slate-900">Customer Support</strong><span class="mt-1 block text-xs text-slate-500">We're here when you need us</span></div>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
