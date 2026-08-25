<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ phone: String });
const form = useForm({ code: '' });
const resending = ref(false);
const submit = () => form.post('/login/verify', { preserveScroll: true });
const resend = () => router.post('/login/resend', {}, {
    preserveScroll: true,
    onStart: () => { resending.value = true; },
    onFinish: () => { resending.value = false; },
});
</script>

<template>
    <AppLayout>
        <Head title="Verify Phone"><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section class="bg-[#fbfbfb] py-8 sm:py-12">
            <div class="container-shell">
                <div class="mx-auto max-w-[470px] overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-[0_12px_36px_rgba(15,23,42,.05)]">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Phone verification</p>
                        <h1 class="mt-1.5 text-2xl font-semibold tracking-[-.025em] text-slate-950">Enter your 6-digit code</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-500">We sent a verification code to <strong class="font-semibold text-slate-800">{{ phone }}</strong>.</p>
                    </div>

                    <form class="p-5 sm:p-6" @submit.prevent="submit">
                        <label class="mb-2 block text-xs font-semibold text-slate-800">Verification code</label>
                        <input v-model="form.code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" class="h-12 w-full rounded-[7px] border border-slate-200 bg-white px-4 text-center text-xl font-semibold tracking-[.32em] text-slate-950 outline-none transition focus:border-prinx-400" placeholder="000000" autofocus>
                        <p v-if="form.errors.code" class="mt-2 text-xs text-red-600">{{ form.errors.code }}</p>

                        <button class="mt-5 flex h-11 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-5 text-sm font-semibold text-white transition hover:bg-prinx-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing || form.code.length !== 6">
                            {{ form.processing ? 'Verifying...' : 'Verify and continue' }}
                        </button>

                        <div class="mt-4 flex items-center justify-between gap-3 text-xs">
                            <Link href="/login" class="font-medium text-slate-500 hover:text-prinx-600">Change phone number</Link>
                            <button type="button" class="font-semibold text-prinx-600 hover:text-prinx-700 disabled:opacity-50" :disabled="resending" @click="resend">{{ resending ? 'Sending...' : 'Resend code' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
