<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    account: { type: Object, required: true },
});

const showProfilePassword = ref(false);
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const profileForm = useForm({
    name: props.account.name || '',
    email: props.account.email || '',
    phone: props.account.phone || '',
    current_password: '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const emailChanged = computed(() =>
    String(profileForm.email || '').trim().toLowerCase() !== String(props.account.email || '').trim().toLowerCase()
);

const passwordChecks = computed(() => ({
    length: passwordForm.password.length >= 8,
    letter: /[A-Za-z]/.test(passwordForm.password),
    number: /\d/.test(passwordForm.password),
    match: passwordForm.password.length > 0 && passwordForm.password === passwordForm.password_confirmation,
}));

const updatedLabel = computed(() => {
    if (!props.account.updated_at) return 'Not available';
    return new Date(props.account.updated_at).toLocaleString();
});

function saveProfile() {
    profileForm.patch('/admin/account/profile', {
        preserveScroll: true,
        onSuccess: () => {
            profileForm.current_password = '';
            profileForm.clearErrors();
        },
    });
}

function changePassword() {
    passwordForm.patch('/admin/account/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="Admin account" />

        <div class="mx-auto max-w-5xl">
            <div>
                <p class="section-kicker">Account & security</p>
                <h1 class="section-title">Admin account</h1>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500 sm:text-sm">Manage the details you use to sign in and keep your administrator account secure.</p>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_310px]">
                <div class="space-y-4">
                    <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                        <div class="flex items-start gap-3 border-b border-slate-200 p-3.5 sm:p-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600">
                                <Icon name="profile" :size="19" />
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Profile details</h2>
                                <p class="mt-0.5 text-xs leading-5 text-slate-500">Update your administrator name, login email and phone number.</p>
                            </div>
                        </div>

                        <form class="space-y-4 p-3.5 sm:p-4" @submit.prevent="saveProfile">
                            <div>
                                <label class="label" for="admin-name">Full name</label>
                                <input id="admin-name" v-model.trim="profileForm.name" class="input min-h-11 text-base sm:text-sm" autocomplete="name" maxlength="120" placeholder="Administrator name">
                                <p v-if="profileForm.errors.name" class="error">{{ profileForm.errors.name }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label" for="admin-email">Login email</label>
                                    <input id="admin-email" v-model.trim="profileForm.email" type="email" class="input min-h-11 text-base sm:text-sm" autocomplete="email" maxlength="190" placeholder="admin@example.com">
                                    <p v-if="profileForm.errors.email" class="error">{{ profileForm.errors.email }}</p>
                                </div>
                                <div>
                                    <label class="label" for="admin-phone">Phone number</label>
                                    <input id="admin-phone" v-model.trim="profileForm.phone" type="tel" class="input min-h-11 text-base sm:text-sm" autocomplete="tel" maxlength="30" placeholder="+255 ...">
                                    <p v-if="profileForm.errors.phone" class="error">{{ profileForm.errors.phone }}</p>
                                </div>
                            </div>

                            <div v-if="emailChanged" class="rounded-[8px] border border-amber-200 bg-amber-50 p-3">
                                <div class="flex items-start gap-2.5">
                                    <Icon name="shield-security" :size="18" class="mt-0.5 shrink-0 text-amber-700" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-amber-900">Confirm email change</p>
                                        <p class="mt-0.5 text-[11px] leading-5 text-amber-800">Because the email is used to sign in, enter your current password before saving it.</p>
                                        <div class="relative mt-2.5">
                                            <input
                                                v-model="profileForm.current_password"
                                                :type="showProfilePassword ? 'text' : 'password'"
                                                class="input min-h-11 pr-12 text-base sm:text-sm"
                                                autocomplete="current-password"
                                                placeholder="Current password"
                                            >
                                            <button type="button" class="absolute right-1 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[7px] text-slate-500 hover:bg-white" :aria-label="showProfilePassword ? 'Hide password' : 'Show password'" @click="showProfilePassword = !showProfilePassword">
                                                <Icon :name="showProfilePassword ? 'eye-slash' : 'eye'" :size="18" />
                                            </button>
                                        </div>
                                        <p v-if="profileForm.errors.current_password" class="error">{{ profileForm.errors.current_password }}</p>
                                    </div>
                                </div>
                            </div>

                            <button class="btn-primary min-h-11 w-full sm:w-auto" :disabled="profileForm.processing">
                                <Icon name="tick-circle" :size="17" />
                                {{ profileForm.processing ? 'Saving...' : 'Save account details' }}
                            </button>
                        </form>
                    </section>

                    <section class="overflow-hidden rounded-[9px] border border-slate-200 bg-white">
                        <div class="flex items-start gap-3 border-b border-slate-200 p-3.5 sm:p-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-slate-100 text-slate-700">
                                <Icon name="lock" :size="19" />
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Change password</h2>
                                <p class="mt-0.5 text-xs leading-5 text-slate-500">Changing your password also signs this account out from other active sessions.</p>
                            </div>
                        </div>

                        <form class="space-y-4 p-3.5 sm:p-4" @submit.prevent="changePassword">
                            <div>
                                <label class="label" for="current-password">Current password</label>
                                <div class="relative">
                                    <input id="current-password" v-model="passwordForm.current_password" :type="showCurrentPassword ? 'text' : 'password'" class="input min-h-11 pr-12 text-base sm:text-sm" autocomplete="current-password" placeholder="Enter current password">
                                    <button type="button" class="absolute right-1 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[7px] text-slate-500 hover:bg-slate-50" :aria-label="showCurrentPassword ? 'Hide password' : 'Show password'" @click="showCurrentPassword = !showCurrentPassword"><Icon :name="showCurrentPassword ? 'eye-slash' : 'eye'" :size="18" /></button>
                                </div>
                                <p v-if="passwordForm.errors.current_password" class="error">{{ passwordForm.errors.current_password }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label" for="new-password">New password</label>
                                    <div class="relative">
                                        <input id="new-password" v-model="passwordForm.password" :type="showNewPassword ? 'text' : 'password'" class="input min-h-11 pr-12 text-base sm:text-sm" autocomplete="new-password" placeholder="New password">
                                        <button type="button" class="absolute right-1 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[7px] text-slate-500 hover:bg-slate-50" :aria-label="showNewPassword ? 'Hide password' : 'Show password'" @click="showNewPassword = !showNewPassword"><Icon :name="showNewPassword ? 'eye-slash' : 'eye'" :size="18" /></button>
                                    </div>
                                    <p v-if="passwordForm.errors.password" class="error">{{ passwordForm.errors.password }}</p>
                                </div>
                                <div>
                                    <label class="label" for="confirm-password">Confirm new password</label>
                                    <div class="relative">
                                        <input id="confirm-password" v-model="passwordForm.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" class="input min-h-11 pr-12 text-base sm:text-sm" autocomplete="new-password" placeholder="Repeat new password">
                                        <button type="button" class="absolute right-1 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[7px] text-slate-500 hover:bg-slate-50" :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'" @click="showConfirmPassword = !showConfirmPassword"><Icon :name="showConfirmPassword ? 'eye-slash' : 'eye'" :size="18" /></button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="passwordForm.password" class="grid grid-cols-2 gap-2 text-[11px] sm:grid-cols-4">
                                <div :class="['rounded-[7px] px-2.5 py-2 font-medium', passwordChecks.length ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500']">8+ characters</div>
                                <div :class="['rounded-[7px] px-2.5 py-2 font-medium', passwordChecks.letter ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500']">Has a letter</div>
                                <div :class="['rounded-[7px] px-2.5 py-2 font-medium', passwordChecks.number ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500']">Has a number</div>
                                <div :class="['rounded-[7px] px-2.5 py-2 font-medium', passwordChecks.match ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500']">Passwords match</div>
                            </div>

                            <button class="btn-primary min-h-11 w-full sm:w-auto" :disabled="passwordForm.processing">
                                <Icon name="shield-tick" :size="17" />
                                {{ passwordForm.processing ? 'Changing...' : 'Change password' }}
                            </button>
                        </form>
                    </section>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-[9px] border border-slate-200 bg-white p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] bg-slate-950 text-base font-semibold text-white">{{ account.name?.charAt(0)?.toUpperCase() || 'A' }}</div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-950">{{ account.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ account.email }}</p>
                            </div>
                        </div>
                        <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Role</dt><dd class="text-right font-semibold text-slate-800">{{ account.role }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Phone</dt><dd class="max-w-[170px] truncate text-right font-medium text-slate-700">{{ account.phone || 'Not set' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Last updated</dt><dd class="max-w-[180px] text-right font-medium leading-5 text-slate-700">{{ updatedLabel }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-[9px] bg-slate-950 p-4 text-white">
                        <div class="flex items-center gap-2"><Icon name="shield-security" :size="18" class="text-prinx-400" /><h2 class="text-sm font-semibold">Security tips</h2></div>
                        <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-300">
                            <li>Use a password you do not use on another website.</li>
                            <li>Keep the administrator email under your control.</li>
                            <li>Password changes close other active admin sessions.</li>
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>
