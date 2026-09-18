<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    reviews: Object,
    status: String,
    counts: Object,
});

const tabs = [
    ['pending', 'Pending'],
    ['approved', 'Approved'],
    ['rejected', 'Rejected'],
    ['all', 'All'],
];

function moderate(review, status) {
    router.patch(`/admin/reviews/${review.id}`, { status }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <div class="admin-toolbar">
            <div>
                <p class="section-kicker">Store trust</p>
                <h1 class="section-title">Product Reviews</h1>
                <p class="mt-1 text-[12px] text-slate-500">Approve genuine verified-purchase reviews before they appear publicly.</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="tab in tabs"
                :key="tab[0]"
                :href="`/admin/reviews?status=${tab[0]}`"
                :class="['tab-button border', status === tab[0] ? 'border-prinx-500 bg-prinx-500 text-white' : 'border-slate-200 bg-white text-slate-600']"
            >
                {{ tab[1] }}
                <span :class="['rounded-full px-2 py-0.5 text-[10px]', status === tab[0] ? 'bg-white/15' : 'bg-slate-100']">{{ counts?.[tab[0]] || 0 }}</span>
            </Link>
        </div>

        <div class="mt-4 space-y-3">
            <article v-for="review in reviews.data" :key="review.id" class="card p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-[13px] font-semibold text-slate-900">{{ review.product?.name || 'Deleted product' }}</strong>
                            <span class="rounded-[6px] bg-emerald-50 px-2 py-1 text-[9px] font-semibold text-emerald-700" v-if="review.is_verified_purchase">Verified Purchase</span>
                            <span class="rounded-[6px] bg-slate-100 px-2 py-1 text-[9px] font-semibold capitalize text-slate-600">{{ review.status }}</span>
                        </div>
                        <p class="mt-2 text-[13px] tracking-[-1px] text-amber-500">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</p>
                        <strong v-if="review.title" class="mt-2 block text-[12px] font-semibold text-slate-800">{{ review.title }}</strong>
                        <p class="mt-1 whitespace-pre-line text-[12px] leading-5 text-slate-600">{{ review.body }}</p>
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-400">
                            <span>Customer: {{ review.reviewer_name }}</span>
                            <span v-if="review.order">Order: {{ review.order.order_number }}</span>
                            <span>{{ review.created_at }}</span>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button type="button" class="btn-light" @click="moderate(review, 'pending')">Pending</button>
                        <button type="button" class="btn-primary" @click="moderate(review, 'approved')">Approve</button>
                        <button type="button" class="btn-danger" @click="moderate(review, 'rejected')">Reject</button>
                    </div>
                </div>
            </article>

            <div v-if="!reviews.data?.length" class="empty-state card">No reviews in this section.</div>
        </div>

        <Pagination :links="reviews.links || []" />
    </AdminLayout>
</template>
