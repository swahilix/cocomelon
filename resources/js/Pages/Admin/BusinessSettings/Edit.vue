<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    settings: Object,
    paymentStripUrl: String,
    smsAvailable: Boolean,
    smsSecrets: { type: Object, default: () => ({}) },
});

const form = useForm({
    _method: 'put',
    business_name: props.settings?.business_name || 'Prinx Tech',
    legal_name: props.settings?.legal_name || '',
    email: props.settings?.email || '',
    phone: props.settings?.phone || '',
    secondary_phone: props.settings?.secondary_phone || '',
    whatsapp: props.settings?.whatsapp || '',
    address: props.settings?.address || '',
    city: props.settings?.city || '',
    region: props.settings?.region || '',
    country: props.settings?.country || '',
    working_hours: props.settings?.working_hours || '',
    delivery_enabled: props.settings?.delivery_enabled !== false,
    delivery_area: props.settings?.delivery_area || '',
    delivery_eta: props.settings?.delivery_eta || '',
    pickup_enabled: Boolean(props.settings?.pickup_enabled),
    pickup_location: props.settings?.pickup_location || '',
    pickup_instructions: props.settings?.pickup_instructions || '',
    description: props.settings?.description || '',
    facebook_url: props.settings?.facebook_url || '',
    instagram_url: props.settings?.instagram_url || '',
    tiktok_url: props.settings?.tiktok_url || '',
    youtube_url: props.settings?.youtube_url || '',
    x_url: props.settings?.x_url || '',
    maps_url: props.settings?.maps_url || '',
    payment_strip: null,
    sms_enabled: Boolean(props.settings?.sms_enabled),
    sms_provider: ['nextsms', 'momo'].includes(props.settings?.sms_provider) ? props.settings.sms_provider : 'nextsms',
    sms_api_key: '',
    sms_sender_id: props.settings?.sms_sender_id || '',
    sms_bearer_token: '',
});

const save = () => form.post('/admin/business-settings', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
        form.payment_strip = null;
        form.sms_api_key = '';
        form.sms_bearer_token = '';
    },
});
</script>

<template>
    <AdminLayout>
        <Head title="Business Settings" />

        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Website information</p>
                <h1 class="section-title">Business settings</h1>
                <p class="mt-1 text-[12px] text-slate-500">Manage public business information and customer login SMS.</p>
            </div>
            <button type="button" class="btn-primary" :disabled="form.processing" @click="save">
                <Icon :name="form.processing ? 'refresh-2' : 'tick-circle'" :size="15" />
                {{ form.processing ? 'Saving...' : 'Save settings' }}
            </button>
        </div>

        <form class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="save">
            <div class="space-y-4">
                <section class="form-section">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="building" :size="17" /></span>
                        <div><h2 class="font-semibold text-slate-900">Business identity</h2><p class="text-[11px] text-slate-500">Main company details used on the website.</p></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">Business name</label><input v-model="form.business_name" class="input" placeholder="Prinx Tech"><p v-if="form.errors.business_name" class="error">{{ form.errors.business_name }}</p></div>
                        <div><label class="label">Legal / registered name</label><input v-model="form.legal_name" class="input" placeholder="Registered company name"><p v-if="form.errors.legal_name" class="error">{{ form.errors.legal_name }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Business description</label><textarea v-model="form.description" class="input min-h-24 resize-y" placeholder="Short description of the business"></textarea><p v-if="form.errors.description" class="error">{{ form.errors.description }}</p></div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="call" :size="17" /></span>
                        <div><h2 class="font-semibold text-slate-900">Contact information</h2><p class="text-[11px] text-slate-500">These details can be shown in the footer, contact page and other public sections.</p></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">Business email</label><input v-model="form.email" type="email" class="input" placeholder="sales@example.com"><p v-if="form.errors.email" class="error">{{ form.errors.email }}</p></div>
                        <div><label class="label">Primary phone</label><input v-model="form.phone" class="input" placeholder="+255 ..."><p v-if="form.errors.phone" class="error">{{ form.errors.phone }}</p></div>
                        <div><label class="label">Secondary phone</label><input v-model="form.secondary_phone" class="input" placeholder="+255 ..."><p v-if="form.errors.secondary_phone" class="error">{{ form.errors.secondary_phone }}</p></div>
                        <div><label class="label">WhatsApp number</label><input v-model="form.whatsapp" class="input" placeholder="+255 ..."><p v-if="form.errors.whatsapp" class="error">{{ form.errors.whatsapp }}</p></div>
                        <div class="sm:col-span-2"><label class="label">Working hours</label><input v-model="form.working_hours" class="input" placeholder="Mon–Sat, 08:00–19:00"><p v-if="form.errors.working_hours" class="error">{{ form.errors.working_hours }}</p></div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="sms" :size="17" /></span>
                            <div><h2 class="font-semibold text-slate-900">Customer login SMS</h2><p class="text-[11px] text-slate-500">Choose the SMS gateway used for customer phone verification codes.</p></div>
                        </div>
                        <label v-if="smsAvailable" class="flex shrink-0 cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700"><input v-model="form.sms_enabled" type="checkbox" class="h-4 w-4 accent-prinx-500"> Enabled</label>
                    </div>

                    <div v-if="!smsAvailable" class="rounded-[7px] border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">Run the latest database migrations before configuring customer SMS login.</div>
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">Active SMS provider</label>
                            <select v-model="form.sms_provider" class="input">
                                <option value="nextsms">Next SMS</option>
                                <option value="momo">Momo SMS</option>
                            </select>
                            <p class="mt-1 text-[10px] text-slate-500">Save settings after changing this. New OTP messages immediately use the saved provider.</p>
                            <p v-if="form.errors.sms_provider" class="error">{{ form.errors.sms_provider }}</p>
                        </div>
                        <div>
                            <label class="label">Sender ID <span v-if="form.sms_provider === 'momo'" class="font-normal text-slate-400">(optional)</span></label>
                            <input v-model="form.sms_sender_id" class="input" placeholder="PRINX">
                            <p v-if="form.sms_provider === 'nextsms'" class="mt-1 text-[10px] text-slate-500">Required for Next SMS.</p>
                            <p v-else class="mt-1 text-[10px] text-slate-500">Momo can use the account default when left blank.</p>
                            <p v-if="form.errors.sms_sender_id" class="error">{{ form.errors.sms_sender_id }}</p>
                        </div>

                        <template v-if="form.sms_provider === 'nextsms'">
                            <div class="sm:col-span-2 rounded-[8px] border border-slate-200 bg-slate-50 p-3">
                                <div class="mb-3 flex items-start justify-between gap-3"><div><p class="text-xs font-semibold text-slate-900">Next SMS credentials</p><p class="mt-1 text-[10px] leading-4 text-slate-500">Uses the same Messaging Service API V2 single-message integration as the microfinance system.</p></div><span class="rounded-[6px] bg-white px-2 py-1 text-[9px] font-semibold text-slate-500">messaging-service.co.tz</span></div>
                                <label class="label">Basic API key / token</label>
                                <input v-model="form.sms_api_key" type="password" autocomplete="new-password" class="input" :placeholder="smsSecrets.nextsms_api_key ? 'Configured — leave blank to keep' : 'Enter Next SMS Basic API key/token'">
                                <p v-if="smsSecrets.nextsms_api_key" class="mt-1 text-[10px] font-medium text-emerald-600">Next SMS API key is configured.</p>
                                <p v-if="form.errors.sms_api_key" class="error">{{ form.errors.sms_api_key }}</p>
                            </div>
                        </template>

                        <template v-else>
                            <div class="sm:col-span-2 rounded-[8px] border border-slate-200 bg-slate-50 p-3">
                                <div class="mb-3 flex items-start justify-between gap-3"><div><p class="text-xs font-semibold text-slate-900">Momo SMS credentials</p><p class="mt-1 text-[10px] leading-4 text-slate-500">Uses Momo Business Communications API v3 with Bearer token authentication.</p></div><span class="rounded-[6px] bg-white px-2 py-1 text-[9px] font-semibold text-slate-500">business.momo.tz</span></div>
                                <label class="label">Momo Business API token</label>
                                <input v-model="form.sms_bearer_token" type="password" autocomplete="new-password" class="input" :placeholder="smsSecrets.momo_api_token ? 'Configured — leave blank to keep' : 'Enter Momo Business API token'">
                                <p v-if="smsSecrets.momo_api_token" class="mt-1 text-[10px] font-medium text-emerald-600">Momo API token is configured.</p>
                                <p v-if="form.errors.sms_bearer_token" class="error">{{ form.errors.sms_bearer_token }}</p>
                            </div>
                        </template>

                        <p v-if="form.errors.sms_enabled" class="error sm:col-span-2">{{ form.errors.sms_enabled }}</p>
                        <div class="sm:col-span-2 rounded-[7px] border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] leading-5 text-slate-600"><strong class="text-slate-800">Provider switching:</strong> both gateway credentials can remain saved. The value in <strong>Active SMS provider</strong> is the only gateway used after you save the form.</div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="location" :size="17" /></span>
                        <div><h2 class="font-semibold text-slate-900">Location</h2><p class="text-[11px] text-slate-500">Business address and map information.</p></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label">Street / business address</label><input v-model="form.address" class="input" placeholder="Street, building or landmark"><p v-if="form.errors.address" class="error">{{ form.errors.address }}</p></div>
                        <div><label class="label">City</label><input v-model="form.city" class="input" placeholder="Dodoma"><p v-if="form.errors.city" class="error">{{ form.errors.city }}</p></div>
                        <div><label class="label">Region</label><input v-model="form.region" class="input" placeholder="Dodoma"><p v-if="form.errors.region" class="error">{{ form.errors.region }}</p></div>
                        <div><label class="label">Country</label><input v-model="form.country" class="input" placeholder="Tanzania"><p v-if="form.errors.country" class="error">{{ form.errors.country }}</p></div>
                        <div><label class="label">Google Maps link</label><input v-model="form.maps_url" class="input" placeholder="https://maps.google.com/..."><p v-if="form.errors.maps_url" class="error">{{ form.errors.maps_url }}</p></div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="truck-fast" :size="17" /></span>
                        <div><h2 class="font-semibold text-slate-900">Delivery & pickup</h2><p class="text-[11px] text-slate-500">Control the fulfillment promises shown beside products and at checkout.</p></div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-3">
                            <label class="flex cursor-pointer items-center justify-between gap-3">
                                <span><strong class="block text-xs font-semibold text-slate-900">Local delivery</strong><span class="mt-1 block text-[10px] text-slate-500">Customers can choose delivery at checkout.</span></span>
                                <input v-model="form.delivery_enabled" type="checkbox" class="h-4 w-4 accent-prinx-500">
                            </label>
                            <div class="mt-3 space-y-3" :class="{ 'opacity-50': !form.delivery_enabled }">
                                <div><label class="label">Delivery area</label><input v-model="form.delivery_area" class="input" :disabled="!form.delivery_enabled" placeholder="e.g. Dodoma City and nearby areas"><p v-if="form.errors.delivery_area" class="error">{{ form.errors.delivery_area }}</p></div>
                                <div><label class="label">Estimated delivery time</label><input v-model="form.delivery_eta" class="input" :disabled="!form.delivery_enabled" placeholder="e.g. 20–40 minutes for selected local orders"><p v-if="form.errors.delivery_eta" class="error">{{ form.errors.delivery_eta }}</p></div>
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-3">
                            <label class="flex cursor-pointer items-center justify-between gap-3">
                                <span><strong class="block text-xs font-semibold text-slate-900">Store pickup</strong><span class="mt-1 block text-[10px] text-slate-500">Customers can collect confirmed orders from the store.</span></span>
                                <input v-model="form.pickup_enabled" type="checkbox" class="h-4 w-4 accent-prinx-500">
                            </label>
                            <div class="mt-3 space-y-3" :class="{ 'opacity-50': !form.pickup_enabled }">
                                <div><label class="label">Pickup location</label><input v-model="form.pickup_location" class="input" :disabled="!form.pickup_enabled" placeholder="Store address or pickup counter"><p v-if="form.errors.pickup_location" class="error">{{ form.errors.pickup_location }}</p></div>
                                <div><label class="label">Pickup instructions</label><input v-model="form.pickup_instructions" class="input" :disabled="!form.pickup_enabled" placeholder="e.g. Available after confirmation during business hours"><p v-if="form.errors.pickup_instructions" class="error">{{ form.errors.pickup_instructions }}</p></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="global" :size="17" /></span>
                        <div><h2 class="font-semibold text-slate-900">Social media</h2><p class="text-[11px] text-slate-500">Leave a field blank to hide that social profile from website integrations.</p></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">Facebook</label><input v-model="form.facebook_url" class="input" placeholder="https://facebook.com/..."><p v-if="form.errors.facebook_url" class="error">{{ form.errors.facebook_url }}</p></div>
                        <div><label class="label">Instagram</label><input v-model="form.instagram_url" class="input" placeholder="https://instagram.com/..."><p v-if="form.errors.instagram_url" class="error">{{ form.errors.instagram_url }}</p></div>
                        <div><label class="label">TikTok</label><input v-model="form.tiktok_url" class="input" placeholder="https://tiktok.com/@..."><p v-if="form.errors.tiktok_url" class="error">{{ form.errors.tiktok_url }}</p></div>
                        <div><label class="label">YouTube</label><input v-model="form.youtube_url" class="input" placeholder="https://youtube.com/..."><p v-if="form.errors.youtube_url" class="error">{{ form.errors.youtube_url }}</p></div>
                        <div class="sm:col-span-2"><label class="label">X / Twitter</label><input v-model="form.x_url" class="input" placeholder="https://x.com/..."><p v-if="form.errors.x_url" class="error">{{ form.errors.x_url }}</p></div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="card" :size="17" /></span>
                        <div><h2 class="font-semibold text-slate-900">Footer payment methods</h2><p class="text-[11px] text-slate-500">Upload one wide “We Accept” strip containing all supported payment logos.</p></div>
                    </div>
                    <div><label class="label">We Accept strip</label><input type="file" class="input" accept="image/jpeg,image/png,image/webp" @change="form.payment_strip = $event.target.files[0] || null"><p class="image-spec">Recommended: wide transparent image similar to 2048 × 256 px · PNG, JPG or WebP · max 4 MB.</p><p v-if="form.errors.payment_strip" class="error">{{ form.errors.payment_strip }}</p></div>
                    <div v-if="paymentStripUrl" class="mt-4"><p class="label">Current footer strip</p><div class="flex min-h-24 items-center justify-center overflow-hidden rounded-[9px] border border-slate-200 bg-white p-4"><img :src="paymentStripUrl" alt="Accepted payment methods" class="max-h-20 max-w-full object-contain"></div></div>
                </section>

                <div class="flex justify-end pb-2"><button type="submit" class="btn-primary" :disabled="form.processing"><Icon name="tick-circle" :size="15" /> Save business settings</button></div>
            </div>

            <aside class="space-y-4">
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2.5"><p class="text-[10px] font-semibold uppercase tracking-[.1em] text-slate-500">Website preview</p></div>
                    <div class="p-4">
                        <p class="text-base font-semibold text-slate-950">{{ form.business_name || 'Business name' }}</p>
                        <p v-if="form.description" class="mt-1 text-[11px] leading-5 text-slate-500">{{ form.description }}</p>
                        <div class="mt-4 space-y-2 text-[12px]"><div v-if="form.phone" class="flex items-center gap-2"><Icon name="call" :size="14" class="text-prinx-600" /><span>{{ form.phone }}</span></div><div v-if="form.email" class="flex items-center gap-2"><Icon name="sms" :size="14" class="text-prinx-600" /><span class="break-all">{{ form.email }}</span></div><div v-if="form.address || form.city || form.region" class="flex items-start gap-2"><Icon name="location" :size="14" class="mt-0.5 shrink-0 text-prinx-600" /><span>{{ [form.address, form.city, form.region, form.country].filter(Boolean).join(', ') }}</span></div><div v-if="form.working_hours" class="flex items-center gap-2"><Icon name="clock" :size="14" class="text-prinx-600" /><span>{{ form.working_hours }}</span></div></div>
                        <div v-if="paymentStripUrl" class="mt-5 border-t border-slate-100 pt-4"><img :src="paymentStripUrl" alt="Accepted payment methods" class="max-h-12 max-w-full object-contain"></div>
                    </div>
                </section>

                <section class="rounded-[9px] border p-3 text-[11px] leading-5" :class="form.sms_enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-600'">
                    <div class="flex gap-2"><Icon name="sms" :size="16" class="mt-0.5 shrink-0" /><div><p class="font-semibold">Customer phone login: {{ form.sms_enabled ? 'Enabled' : 'Disabled' }}</p><p class="mt-1">Active provider: {{ form.sms_provider === 'nextsms' ? 'Next SMS' : 'Momo SMS' }}</p></div></div>
                </section>

                <section class="rounded-[9px] border border-prinx-100 bg-prinx-50 p-3 text-[11px] leading-5 text-prinx-900"><div class="flex gap-2"><Icon name="info-circle" :size="16" class="mt-0.5 shrink-0" /><p>Next SMS and Momo API credentials are encrypted before storage. Saving the provider selection switches all new customer OTP messages to that gateway.</p></div></section>
            </aside>
        </form>
    </AdminLayout>
</template>
