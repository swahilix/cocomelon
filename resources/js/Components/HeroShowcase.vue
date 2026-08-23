<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const AUTO_PLAY_MS = 6500;
const activeIndex = ref(0);
let timer = null;

const carouselSlides = computed(() => props.slides.filter((slide) => Boolean(slide?.image_url)));
const activeSlide = computed(() => carouselSlides.value[activeIndex.value] || null);

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
    <section v-if="activeSlide" class="bg-[#fbfbfb] pt-7 sm:pt-8" aria-label="Homepage banners">
        <div class="container-shell">
            <div
                class="relative"
                @mouseenter="clearTimer"
                @mouseleave="restart"
                @focusin="clearTimer"
                @focusout="restart"
            >
                <div class="relative overflow-hidden rounded-[16px] border border-slate-200 bg-white shadow-[0_14px_44px_rgba(15,23,42,.08)]">
                    <Transition name="banner-fade" mode="out-in">
                        <a
                            v-if="activeSlide.link_url"
                            :key="`${activeSlide.id}-link`"
                            :href="activeSlide.link_url"
                            class="block"
                            :aria-label="activeSlide.image_alt || activeSlide.headline || 'Open banner link'"
                        >
                            <picture class="block aspect-[16/8] overflow-hidden sm:aspect-[16/6] lg:aspect-[16/5]">
                                <img
                                    :src="activeSlide.image_url"
                                    :alt="activeSlide.image_alt || activeSlide.headline || 'Prinx Electronics banner'"
                                    class="h-full w-full object-cover"
                                    fetchpriority="high"
                                    decoding="async"
                                >
                            </picture>
                        </a>

                        <picture v-else :key="`${activeSlide.id}-image`" class="block aspect-[16/8] overflow-hidden sm:aspect-[16/6] lg:aspect-[16/5]">
                            <img
                                :src="activeSlide.image_url"
                                :alt="activeSlide.image_alt || activeSlide.headline || 'Prinx Electronics banner'"
                                class="h-full w-full object-cover"
                                fetchpriority="high"
                                decoding="async"
                            >
                        </picture>
                    </Transition>

                    <div v-if="carouselSlides.length > 1" class="absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2 rounded-full bg-white/75 px-3 py-2 shadow-sm backdrop-blur" role="tablist" aria-label="Choose banner">
                        <button
                            v-for="(slide, index) in carouselSlides"
                            :key="slide.id"
                            type="button"
                            :aria-label="`Show banner ${index + 1}`"
                            :aria-selected="index === activeIndex"
                            :class="['h-1.5 rounded-full transition-all', index === activeIndex ? 'w-7 bg-prinx-500' : 'w-3 bg-slate-400 hover:bg-slate-600']"
                            @click="goTo(index)"
                        ></button>
                    </div>
                </div>

                <button
                    v-if="carouselSlides.length > 1"
                    type="button"
                    class="absolute left-0 top-1/2 z-20 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-[0_7px_22px_rgba(15,23,42,.10)] transition hover:border-prinx-200 hover:text-prinx-600"
                    aria-label="Previous banner"
                    @click="previous"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 6-6 6 6 6"/></svg>
                </button>
                <button
                    v-if="carouselSlides.length > 1"
                    type="button"
                    class="absolute right-0 top-1/2 z-20 flex h-11 w-11 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-[0_7px_22px_rgba(15,23,42,.10)] transition hover:border-prinx-200 hover:text-prinx-600"
                    aria-label="Next banner"
                    @click="next"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m10 6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.banner-fade-enter-active,.banner-fade-leave-active{transition:opacity .32s ease}.banner-fade-enter-from,.banner-fade-leave-to{opacity:0}@media(prefers-reduced-motion:reduce){.banner-fade-enter-active,.banner-fade-leave-active{transition:none}}
</style>
