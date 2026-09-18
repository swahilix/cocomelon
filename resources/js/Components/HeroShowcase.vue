<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const AUTO_PLAY_MS = 6500;
const activeIndex = ref(0);
let timer = null;

const carouselSlides = computed(() => props.slides.filter((slide) => Boolean(slide?.image_url)));
const activeSlide = computed(() => carouselSlides.value[activeIndex.value] || null);
const firstSlide = computed(() => carouselSlides.value[0] || null);

function clearTimer() {
    if (timer) {
        window.clearInterval(timer);
        timer = null;
    }
}

function restart() {
    clearTimer();
    if (typeof window === 'undefined' || carouselSlides.value.length < 2) return;

    timer = window.setInterval(() => {
        activeIndex.value = (activeIndex.value + 1) % carouselSlides.value.length;
    }, AUTO_PLAY_MS);
}

function goTo(index) {
    const total = carouselSlides.value.length;
    if (!total) return;
    activeIndex.value = (index + total) % total;
    restart();
}

const next = () => goTo(activeIndex.value + 1);
const previous = () => goTo(activeIndex.value - 1);

watch(() => carouselSlides.value.length, (length) => {
    if (activeIndex.value >= length) activeIndex.value = 0;
    restart();
});

onMounted(() => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) restart();
});

onBeforeUnmount(clearTimer);
</script>

<template>
    <Head v-if="firstSlide?.image_url">
        <link
            head-key="homepage-hero-preload"
            rel="preload"
            as="image"
            :href="firstSlide.image_url"
            :imagesrcset="firstSlide.image_srcset || undefined"
            imagesizes="(max-width: 640px) calc(100vw - 16px), (max-width: 1280px) calc(100vw - 48px), 1280px"
            fetchpriority="high"
        >
    </Head>

    <section v-if="activeSlide" class="bg-[#fbfbfb] pt-7 sm:pt-8" aria-label="Homepage banners">
        <div class="container-shell">
            <div
                class="relative"
                @mouseenter="clearTimer"
                @mouseleave="restart"
                @focusin="clearTimer"
                @focusout="restart"
            >
                <div class="relative aspect-[16/5] overflow-hidden rounded-[16px] border border-slate-200 bg-white shadow-[0_14px_44px_rgba(15,23,42,.08)]">
                    <Transition name="banner-fade" mode="out-in">
                        <a
                            v-if="activeSlide.link_url"
                            :key="`${activeSlide.id}-link`"
                            :href="activeSlide.link_url"
                            class="absolute inset-0 block h-full w-full"
                            :aria-label="activeSlide.image_alt || activeSlide.headline || 'Open banner link'"
                        >
                            <picture class="block h-full w-full overflow-hidden bg-white">
                                <img
                                    :src="activeSlide.image_url"
                                    :srcset="activeSlide.image_srcset || undefined"
                                    sizes="(max-width: 640px) calc(100vw - 16px), (max-width: 1280px) calc(100vw - 48px), 1280px"
                                    :alt="activeSlide.image_alt || activeSlide.headline || 'Prinx Electronics banner'"
                                    class="block h-full w-full object-contain sm:object-cover"
                                    width="1600"
                                    height="500"
                                    loading="eager"
                                    fetchpriority="high"
                                    decoding="async"
                                >
                            </picture>
                        </a>

                        <picture v-else :key="`${activeSlide.id}-image`" class="absolute inset-0 block h-full w-full overflow-hidden bg-white">
                            <img
                                :src="activeSlide.image_url"
                                :srcset="activeSlide.image_srcset || undefined"
                                sizes="(max-width: 640px) calc(100vw - 16px), (max-width: 1280px) calc(100vw - 48px), 1280px"
                                :alt="activeSlide.image_alt || activeSlide.headline || 'Prinx Electronics banner'"
                                class="block h-full w-full object-contain sm:object-cover"
                                width="1600"
                                height="500"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                            >
                        </picture>
                    </Transition>

                    <div v-if="carouselSlides.length > 1" class="absolute bottom-2 left-1/2 z-20 flex -translate-x-1/2 items-center rounded-full bg-white/80 px-1 py-0.5 shadow-sm backdrop-blur sm:bottom-4 sm:px-1.5 sm:py-1" role="group" aria-label="Choose banner">
                        <button
                            v-for="(slide, index) in carouselSlides"
                            :key="slide.id"
                            type="button"
                            :aria-label="`Show banner ${index + 1}`"
                            :aria-current="index === activeIndex ? 'true' : undefined"
                            class="flex h-11 w-11 items-center justify-center rounded-full"
                            @click="goTo(index)"
                        >
                            <span :class="['block h-1.5 rounded-full transition-all', index === activeIndex ? 'w-6 bg-prinx-500 sm:w-7' : 'w-3 bg-slate-500 hover:bg-slate-700']"></span>
                        </button>
                    </div>

                    <div v-if="carouselSlides.length > 1" class="absolute bottom-2 right-2 z-20 flex items-center gap-1.5 sm:bottom-4 sm:right-4 sm:gap-2">
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white/95 text-slate-700 shadow-[0_5px_16px_rgba(15,23,42,.10)] backdrop-blur transition hover:border-prinx-200 hover:text-prinx-700 sm:bg-white"
                            aria-label="Previous banner"
                            @click="previous"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current sm:h-[18px] sm:w-[18px]" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 6-6 6 6 6"/></svg>
                        </button>
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white/95 text-slate-700 shadow-[0_5px_16px_rgba(15,23,42,.10)] backdrop-blur transition hover:border-prinx-200 hover:text-prinx-700 sm:bg-white"
                            aria-label="Next banner"
                            @click="next"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current sm:h-[18px] sm:w-[18px]" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m10 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.banner-fade-enter-active,.banner-fade-leave-active{transition:opacity .32s ease}.banner-fade-enter-from,.banner-fade-leave-to{opacity:0}@media(prefers-reduced-motion:reduce){.banner-fade-enter-active,.banner-fade-leave-active{transition:none}}
</style>
