<script setup>
import { Head } from '@inertiajs/vue3';
import AdminHubGrid from '../../../Components/AdminHubGrid.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { adminSettings } from '../../../lib/adminHubs';
</script>

<template>
    <AdminLayout>
        <Head title="Settings" />

        <div class="space-y-4">
            <div>
                <p class="text-[9px] font-semibold uppercase tracking-[.16em] text-prinx-600">System</p>
                <h1 class="mt-1 text-[24px] font-semibold tracking-[-.025em] text-slate-950">Settings</h1>
                <p class="mt-1 text-[12px] text-slate-500">Business, map and mail configuration for the store.</p>
            </div>

            <AdminHubGrid :items="adminSettings" empty="You do not have access to any settings." />
        </div>
    </AdminLayout>
</template>
