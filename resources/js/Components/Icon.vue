<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [String, Number], default: 18 },
});

const px = computed(() => Number(props.size) || 18);
</script>

<template>
    <i
        class="iconsax inline-flex shrink-0 items-center justify-center align-middle leading-none"
        :icon-name="name"
        :style="{
            fontSize: `${px}px`,
            width: `${px}px`,
            minWidth: `${px}px`,
            height: `${px}px`,
            lineHeight: 1,
        }"
        aria-hidden="true"
    ></i>
</template>
