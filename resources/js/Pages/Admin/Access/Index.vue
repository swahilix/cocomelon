<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ staff: Array, roles: Array, permissions: Array });
const page = usePage();
const editing = ref(null);
const editingRole = ref(null);

const createForm = useForm({ name: '', email: '', phone: '', password: '', role_id: props.roles[0]?.id || '' });
const editForm = useForm({ name: '', email: '', phone: '', password: '', role_id: '' });
const roleForm = useForm({ permissions: [] });

const groupedPermissions = computed(() => props.permissions.reduce((groups, permission) => {
    (groups[permission.group] ||= []).push(permission);
    return groups;
}, {}));

function createStaff() {
    createForm.post('/admin/access/staff', { preserveScroll: true, onSuccess: () => createForm.reset('name', 'email', 'phone', 'password') });
}
function openEdit(user) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.phone = user.phone || '';
    editForm.password = '';
    editForm.role_id = user.role_id || '';
    editForm.clearErrors();
}
function saveStaff() {
    editForm.patch(`/admin/access/staff/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; editForm.reset(); } });
}
function revoke(user) {
    if (!confirm(`Remove admin-panel access for ${user.name}? Their customer account will remain.`)) return;
    useForm({}).delete(`/admin/access/staff/${user.id}`, { preserveScroll: true });
}
function openRole(role) {
    editingRole.value = role;
    roleForm.permissions = role.permissions.map((permission) => permission.slug);
    roleForm.clearErrors();
}
function saveRole() {
    roleForm.put(`/admin/access/roles/${editingRole.value.id}/permissions`, { preserveScroll: true, onSuccess: () => { editingRole.value = null; } });
}
function togglePermission(slug) {
    roleForm.permissions = roleForm.permissions.includes(slug)
        ? roleForm.permissions.filter((item) => item !== slug)
        : [...roleForm.permissions, slug];
}
</script>

<template>
    <AdminLayout>
        <Head title="Staff Access" />

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="section-kicker">Security & access</p><h1 class="section-title">Staff roles & permissions</h1><p class="mt-2 max-w-3xl text-sm text-slate-500">Give each staff member only the access needed for their job. Administrator always retains full system access.</p></div>
            <div class="rounded-[9px] border border-slate-200 bg-white px-4 py-3 text-sm"><span class="text-slate-500">Your role</span><b class="ml-2">{{ page.props.auth.role?.name || 'Administrator' }}</b></div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[380px_1fr]">
            <form class="card p-5" @submit.prevent="createStaff">
                <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-prinx-50 text-prinx-600"><Icon name="user-add" :size="19" /></span><div><h2 class="font-semibold">Create staff account</h2><p class="mt-1 text-xs text-slate-500">Staff can sign in through the normal login page.</p></div></div>
                <div class="mt-5 space-y-3"><div><label class="label">Name</label><input v-model="createForm.name" class="input" required></div><div><label class="label">Email</label><input v-model="createForm.email" type="email" class="input" required></div><div><label class="label">Phone</label><input v-model="createForm.phone" class="input"></div><div><label class="label">Temporary password</label><input v-model="createForm.password" type="password" class="input" minlength="8" required></div><div><label class="label">Role</label><select v-model="createForm.role_id" class="input" required><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></div></div>
                <button class="btn-primary mt-4" :disabled="createForm.processing"><Icon name="add-circle" :size="16" /> Create staff</button>
            </form>

            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Staff with admin-panel access</h2><p class="mt-1 text-xs text-slate-500">Removing access preserves the underlying user/customer account.</p></div>
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Staff</th><th>Role</th><th>Contact</th><th>Added</th><th class="text-right">Actions</th></tr></thead><tbody>
                    <tr v-for="user in staff" :key="user.id"><td><p class="font-semibold">{{ user.name }}</p><p class="text-[11px] text-slate-500">#{{ user.id }}</p></td><td><span class="rounded-[7px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold">{{ user.role?.name || 'No role' }}</span></td><td><p>{{ user.email }}</p><p class="text-xs text-slate-500">{{ user.phone || '—' }}</p></td><td>{{ new Date(user.created_at).toLocaleDateString() }}</td><td class="text-right"><div class="inline-flex gap-2"><button class="btn-light px-3 py-2 text-xs" @click="openEdit(user)"><Icon name="edit-2" :size="14" /> Edit</button><button v-if="user.id !== page.props.auth.user?.id" class="rounded-[8px] border border-red-200 px-3 py-2 text-xs font-semibold text-red-600" @click="revoke(user)">Revoke</button></div></td></tr>
                </tbody></table></div>
            </section>
        </div>

        <section class="mt-6">
            <div><p class="section-kicker">Role matrix</p><h2 class="mt-1 text-xl font-semibold">Permission profiles</h2><p class="mt-1 text-sm text-slate-500">Edit the permissions granted to Manager, Cashier, Stock Keeper and Accountant.</p></div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <button v-for="role in roles" :key="role.id" class="card p-4 text-left transition hover:border-prinx-300" @click="openRole(role)"><div class="flex items-center justify-between"><span class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-slate-100"><Icon name="profile-2user" :size="18" /></span><span class="text-xs text-slate-400">{{ role.users_count }} staff</span></div><h3 class="mt-3 font-semibold">{{ role.name }}</h3><p class="mt-1 min-h-10 text-xs leading-5 text-slate-500">{{ role.description }}</p><p class="mt-3 text-xs font-semibold text-prinx-600">{{ role.permissions.length }} permissions</p></button>
            </div>
        </section>

        <div v-if="editing" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/50 p-4" @click.self="editing=null"><form class="w-full max-w-lg rounded-[10px] bg-white p-5 shadow-2xl" @submit.prevent="saveStaff"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Edit staff access</h2><button type="button" @click="editing=null"><Icon name="close-circle" :size="20" /></button></div><div class="mt-4 grid gap-3 sm:grid-cols-2"><div><label class="label">Name</label><input v-model="editForm.name" class="input"></div><div><label class="label">Email</label><input v-model="editForm.email" type="email" class="input"></div><div><label class="label">Phone</label><input v-model="editForm.phone" class="input"></div><div><label class="label">Role</label><select v-model="editForm.role_id" class="input"><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></div><div class="sm:col-span-2"><label class="label">New password <span class="font-normal text-slate-400">(optional)</span></label><input v-model="editForm.password" type="password" class="input" placeholder="Leave blank to keep current password"></div></div><button class="btn-primary mt-4" :disabled="editForm.processing">Save changes</button></form></div>

        <div v-if="editingRole" class="fixed inset-0 z-[90] overflow-y-auto bg-slate-950/50 p-4" @click.self="editingRole=null"><div class="mx-auto my-6 w-full max-w-3xl rounded-[10px] bg-white p-5 shadow-2xl"><div class="flex items-start justify-between"><div><p class="section-kicker">Permission profile</p><h2 class="mt-1 text-xl font-semibold">{{ editingRole.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ editingRole.slug === 'administrator' ? 'Administrator always has every system permission.' : 'Changes apply to every staff member assigned this role.' }}</p></div><button @click="editingRole=null"><Icon name="close-circle" :size="21" /></button></div><div class="mt-5 space-y-5"><div v-for="(items, group) in groupedPermissions" :key="group"><h3 class="mb-2 text-xs font-semibold uppercase tracking-[.14em] text-slate-400">{{ group }}</h3><div class="grid gap-2 sm:grid-cols-2"><label v-for="permission in items" :key="permission.slug" :class="['flex cursor-pointer items-start gap-3 rounded-[8px] border p-3', roleForm.permissions.includes(permission.slug) ? 'border-prinx-200 bg-prinx-50/50' : 'border-slate-200']"><input type="checkbox" class="mr-[10px] mt-1" :checked="roleForm.permissions.includes(permission.slug)" :disabled="editingRole.slug === 'administrator'" @change="togglePermission(permission.slug)"><span><b class="text-sm">{{ permission.name }}</b><span class="mt-1 block text-xs leading-5 text-slate-500">{{ permission.description }}</span></span></label></div></div></div><button v-if="editingRole.slug !== 'administrator'" class="btn-primary mt-5" :disabled="roleForm.processing" @click="saveRole">Save role permissions</button></div></div>
    </AdminLayout>
</template>
