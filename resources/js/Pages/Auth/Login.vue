<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Flash from '../../Components/Flash.vue';

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);

const submit = () => form.post('/admin/login');
</script>

<template>
    <Head title="Staff Sign In" />

    <div class="relative min-h-screen overflow-hidden bg-[#f5f7fa] text-slate-900">
        <Flash />

        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-20 -top-20 h-72 w-72 rounded-full bg-prinx-100/60 blur-3xl"></div>
            <div class="absolute -bottom-24 right-[-70px] h-80 w-80 rounded-full bg-slate-200/70 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen w-full items-center justify-center px-4 py-6 sm:px-6">
            <main class="w-full max-w-[440px] border border-slate-200 bg-white px-5 py-7 shadow-[0_18px_50px_rgba(15,23,42,.08)] sm:px-8 sm:py-9">
                <div class="flex items-center justify-between gap-4">
                    <img src="/assets/prinx-logo.svg" class="h-9 w-auto" alt="Prinx Electronics">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-prinx-100 bg-prinx-50 text-prinx-600">
                        <svg viewBox="0 0 24 24" class="h-[19px] w-[19px] fill-none stroke-current" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    </span>
                </div>

                <div class="mt-7">
                    <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-prinx-600">Prinx administration</p>
                    <h1 class="mt-1.5 text-[27px] font-semibold tracking-[-.03em] text-slate-950">Welcome back</h1>
                    <p class="mt-2 text-[12px] leading-5 text-slate-500">Sign in with your staff email and password to continue to the admin panel.</p>
                </div>

                <form class="mt-7" @submit.prevent="submit">
                    <div>
                        <label for="admin-email" class="mb-1.5 block text-[11px] font-semibold text-slate-700">Email address</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                            </span>
                            <input id="admin-email" v-model="form.email" type="email" autocomplete="username" autofocus placeholder="staff@prinx.co.tz" class="h-11 w-full rounded-[7px] border bg-white pl-10 pr-3 text-[13px] text-slate-900 outline-none transition placeholder:text-slate-300 focus:border-prinx-400 focus:ring-2 focus:ring-prinx-100" :class="form.errors.email ? 'border-red-300' : 'border-slate-200'">
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-[11px] font-medium text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div class="mt-4">
                        <label for="admin-password" class="mb-1.5 block text-[11px] font-semibold text-slate-700">Password</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            </span>
                            <input id="admin-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" class="h-11 w-full rounded-[7px] border bg-white pl-10 pr-11 text-[13px] text-slate-900 outline-none transition placeholder:text-slate-300 focus:border-prinx-400 focus:ring-2 focus:ring-prinx-100" :class="form.errors.password ? 'border-red-300' : 'border-slate-200'">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-slate-700" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
                                <svg v-if="!showPassword" viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                                <svg v-else viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.8"><path d="m3 3 18 18M10.6 6.1A9.9 9.9 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.4 3.1M6.2 6.2C3.8 7.9 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.4-.5 4.7-1.2M9.8 9.8a3 3 0 0 0 4.4 4.4"/></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-[11px] font-medium text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <label class="mt-4 flex w-fit cursor-pointer items-center gap-2.5 text-[11px] font-medium text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded-[4px] border-slate-300 accent-[#ff4b18]">
                        Keep me signed in on this device
                    </label>

                    <button type="submit" class="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-[7px] bg-prinx-500 px-4 text-[12px] font-semibold text-white transition hover:bg-prinx-600 focus:outline-none focus:ring-2 focus:ring-prinx-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
                        <svg v-if="!form.processing" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8"><path d="M10 17l5-5-5-5"/><path d="M4 12h11M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5"/></svg>
                        <svg v-else viewBox="0 0 24 24" class="h-4 w-4 animate-spin fill-none stroke-current" stroke-width="1.8"><circle cx="12" cy="12" r="9" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg>
                        {{ form.processing ? 'Signing in...' : 'Sign in to admin' }}
                    </button>
                </form>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <Link href="/" class="inline-flex items-center gap-2 text-[11px] font-semibold text-slate-500 transition hover:text-prinx-600">
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg>
                        Return to Prinx store
                    </Link>
                </div>

                <p class="mt-7 text-[10px] leading-4 text-slate-400">This portal is restricted to authorized Prinx staff.</p>
            </main>
        </div>
    </div>
</template>
