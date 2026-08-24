<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [String, Number], default: 18 },
});

const aliases = {
    'arrow-left-2': 'arrow-left',
    'arrow-right-3': 'arrow-right',
    'close-circle': 'x-circle',
    'menu': 'hamburger-menu',
};

const resolvedName = computed(() => aliases[props.name] || props.name);
const px = computed(() => Number(props.size) || 18);
</script>

<template>
    <i
        class="iconsax inline-block shrink-0 align-middle leading-none"
        :icon-name="resolvedName"
        :style="{ fontSize: `${px}px` }"
        aria-hidden="true"
    ></i>
</template>
