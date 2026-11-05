<script setup>
import { Head } from '@inertiajs/vue3';
import AdminHubGrid from '../../../Components/AdminHubGrid.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { adminTools } from '../../../lib/adminHubs';
</script>

<template>
    <AdminLayout>
        <Head title="Tools" />

        <div class="mx-auto max-w-[1200px] space-y-4">
            <div>
                <p class="text-[9px] font-semibold uppercase tracking-[.16em] text-prinx-600">Store management</p>
                <h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950">Tools</h1>
                <p class="mt-1 text-[12px] text-slate-500">Storefront content and catalogue helpers in one place.</p>
            </div>

            <AdminHubGrid :items="adminTools" empty="You do not have access to any tools." />
        </div>
    </AdminLayout>
</template>
