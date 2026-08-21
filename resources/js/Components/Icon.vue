<script setup>
defineProps({
    name: { type: String, required: true },
    size: { type: [String, Number], default: 18 },
});
</script>

<template>
    <i
        class="iconsax inline-block shrink-0 align-middle leading-none"
        :icon-name="name"
        :style="{ fontSize: `${size}px` }"
        aria-hidden="true"
    ></i>
</template>
