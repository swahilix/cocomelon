<script setup>
import { computed } from 'vue';

const props = defineProps({
    rating: { type: [Number, String], default: 0 },
    count: { type: [Number, String], default: 0 },
});

const normalizedCount = computed(() => Math.max(0, Number(props.count || 0)));
const normalizedRating = computed(() => Math.max(0, Math.min(5, Number(props.rating || 0))));
const filledStars = computed(() => normalizedCount.value > 0 ? Math.round(normalizedRating.value) : 0);
const stars = computed(() => '★'.repeat(filledStars.value) + '☆'.repeat(5 - filledStars.value));
const label = computed(() => normalizedCount.value
    ? `${normalizedRating.value.toFixed(1)} out of 5 from ${normalizedCount.value} ${normalizedCount.value === 1 ? 'review' : 'reviews'}`
    : 'No reviews yet');
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap" :aria-label="label">
        <span class="tracking-[-1px] text-amber-500" aria-hidden="true">{{ stars }}</span>
        <span class="text-slate-400">({{ normalizedCount }})</span>
    </span>
</template>
