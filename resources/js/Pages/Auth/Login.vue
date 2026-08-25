<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Flash from '../../Components/Flash.vue';

const form = useForm({ email: '', password: '', remember: false });
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-950 p-4">
        <Flash />
        <form class="w-full max-w-sm rounded-[10px] bg-white p-6" @submit.prevent="form.post('/admin/login')">
            <img src="/assets/prinx-logo.svg" class="h-10" alt="Prinx Electronics">
            <p class="mt-6 text-[10px] font-semibold uppercase tracking-[.14em] text-prinx-600">Prinx administration</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-[-.02em] text-slate-950">Staff sign in</h1>
            <p class="mt-2 text-xs leading-5 text-slate-500">Use your staff email and password to access the administration panel.</p>

            <div class="mt-5 grid gap-4">
                <div>
                    <label class="label">Email</label>
                    <input v-model="form.email" type="email" autocomplete="username" class="input">
                    <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="label">Password</label>
                    <input v-model="form.password" type="password" autocomplete="current-password" class="input">
                    <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="form.remember" type="checkbox"> Remember me</label>
                <button class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Signing in...' : 'Sign in' }}</button>
            </div>

            <Link href="/" class="mt-5 inline-flex text-xs font-semibold text-prinx-600 hover:text-prinx-700">← Return to public store</Link>
        </form>
    </div>
</template>
