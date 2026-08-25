<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({ phone: '' });
const submit = () => form.post('/login', { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <Head title="Customer Login"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-8 sm:py-12">
            <div class="container-shell">
                <div class="mx-auto max-w-[470px] overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-[0_12px_36px_rgba(15,23,42,.05)]">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Customer account</p>
                        <h1 class="mt-1.5 text-2xl font-semibold tracking-[-.025em] text-slate-950">Sign in with your phone</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Enter the phone number you use when shopping with Prinx. We’ll send you a one-time verification code.</p>
                    </div>

                    <form class="p-5 sm:p-6" @submit.prevent="submit">
                        <label class="mb-2 block text-xs font-semibold text-slate-800">Phone number</label>
                        <div class="flex h-12 overflow-hidden rounded-[7px] border border-slate-200 bg-white transition focus-within:border-prinx-400">
                            <span class="flex shrink-0 items-center border-r border-slate-100 px-3 text-xs font-medium text-slate-500">🇹🇿 +255</span>
                            <input v-model="form.phone" inputmode="tel" autocomplete="tel" class="min-w-0 flex-1 border-0 px-3 text-sm text-slate-900 outline-none" placeholder="712 345 678" autofocus>
                        </div>
                        <p v-if="form.errors.phone" class="mt-2 text-xs text-red-600">{{ form.errors.phone }}</p>
                        <p class="mt-3 text-[11px] leading-5 text-slate-400">No password is required. The verification code expires after 5 minutes.</p>

                        <button class="mt-5 flex h-11 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-5 text-sm font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing || !form.phone.trim()">
                            {{ form.processing ? 'Sending code...' : 'Send verification code' }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
