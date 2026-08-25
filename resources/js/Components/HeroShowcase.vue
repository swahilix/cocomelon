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
                            <picture class="block overflow-hidden bg-white sm:aspect-[16/6] lg:aspect-[16/5]">
                                <img
                                    :src="activeSlide.image_url"
                                    :alt="activeSlide.image_alt || activeSlide.headline || 'Prinx Electronics banner'"
                                    class="block h-auto w-full object-contain sm:h-full sm:object-cover"
                                    fetchpriority="high"
                                    decoding="async"
                                >
                            </picture>
                        </a>

                        <picture v-else :key="`${activeSlide.id}-image`" class="block overflow-hidden bg-white sm:aspect-[16/6] lg:aspect-[16/5]">
                            <img
                                :src="activeSlide.image_url"
                                :alt="activeSlide.image_alt || activeSlide.headline || 'Prinx Electronics banner'"
                                class="block h-auto w-full object-contain sm:h-full sm:object-cover"
                                fetchpriority="high"
                                decoding="async"
                            >
                        </picture>
                    </Transition>

                    <div v-if="carouselSlides.length > 1" class="absolute bottom-2 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1.5 shadow-sm backdrop-blur sm:bottom-4 sm:gap-2 sm:px-3 sm:py-2" role="tablist" aria-label="Choose banner">
                        <button
                            v-for="(slide, index) in carouselSlides"
                            :key="slide.id"
                            type="button"
                            :aria-label="`Show banner ${index + 1}`"
                            :aria-selected="index === activeIndex"
                            :class="['h-1.5 rounded-full transition-all', index === activeIndex ? 'w-6 bg-prinx-500 sm:w-7' : 'w-2.5 bg-slate-400 hover:bg-slate-600 sm:w-3']"
                            @click="goTo(index)"
                        ></button>
                    </div>
                </div>

                <button
                    v-if="carouselSlides.length > 1"
                    type="button"
                    class="absolute left-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-600 shadow-[0_5px_16px_rgba(15,23,42,.10)] transition hover:border-prinx-200 hover:text-prinx-600 sm:left-0 sm:h-11 sm:w-11 sm:-translate-x-1/2 sm:bg-white sm:shadow-[0_7px_22px_rgba(15,23,42,.10)]"
                    aria-label="Previous banner"
                    @click="previous"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current sm:h-5 sm:w-5" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 6-6 6 6 6"/></svg>
                </button>
                <button
                    v-if="carouselSlides.length > 1"
                    type="button"
                    class="absolute right-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-600 shadow-[0_5px_16px_rgba(15,23,42,.10)] transition hover:border-prinx-200 hover:text-prinx-600 sm:right-0 sm:h-11 sm:w-11 sm:translate-x-1/2 sm:bg-white sm:shadow-[0_7px_22px_rgba(15,23,42,.10)]"
                    aria-label="Next banner"
                    @click="next"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current sm:h-5 sm:w-5" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m10 6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.banner-fade-enter-active,.banner-fade-leave-active{transition:opacity .32s ease}.banner-fade-enter-from,.banner-fade-leave-to{opacity:0}@media(prefers-reduced-motion:reduce){.banner-fade-enter-active,.banner-fade-leave-active{transition:none}}
</style>
