<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const AUTO_PLAY_MS = 6500;
const activeIndex = ref(0);
let timer = null;

function plainText(value) {
    return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function normalizedSlide(slide) {
    const item = slide.item || {};
    const isProduct = slide.item_type === 'product';

    return {
        key: `${slide.item_type}-${slide.id}`,
        eyebrow: slide.eyebrow || 'Welcome to Prinx',
        title: slide.headline || item.name || 'Your One-Stop Tech Destination',
        description: plainText(slide.description || item.short_description || item.plain_description || item.description) || 'Original gadgets, accessories and more. Fast delivery. Best prices. Trusted support.',
        image: item.image_url || (isProduct ? '/assets/phone.svg' : '/assets/repair.svg'),
        imageAlt: item.name || 'Prinx Electronics showcase',
        primaryHref: isProduct ? `/products/${encodeURIComponent(item.slug || '')}` : `/services/${encodeURIComponent(item.slug || '')}`,
        primaryLabel: slide.primary_label || (isProduct ? 'Shop Now' : 'View Service'),
        secondaryHref: isProduct ? '/shop' : '/services',
        secondaryLabel: slide.secondary_label || (isProduct ? 'View Offers' : 'Explore Services'),
    };
}

const fallbackSlides = [
    {
        key: 'prinx-home',
        eyebrow: 'Welcome to Prinx',
        title: 'Your One-Stop Tech Destination',
        description: 'Original gadgets, accessories and more. Fast delivery. Best prices. Trusted by thousands.',
        image: '/assets/phone.svg',
        imageAlt: 'Prinx electronics and accessories',
        primaryHref: '/shop',
        primaryLabel: 'Shop Now',
        secondaryHref: '/shop',
        secondaryLabel: 'View Offers',
    },
    {
        key: 'prinx-support',
        eyebrow: 'Technology made easier',
        title: 'Products and Support in One Place',
        description: 'Shop dependable electronics and get professional technical support for home, work and business.',
        image: '/assets/laptop.svg',
        imageAlt: 'Prinx electronics and technical support',
        primaryHref: '/shop',
        primaryLabel: 'Shop Now',
        secondaryHref: '/services',
        secondaryLabel: 'Our Services',
    },
];

const carouselSlides = computed(() => {
    const configured = props.slides.filter((slide) => slide?.item && slide?.item_type).map(normalizedSlide);
    return configured.length ? configured : fallbackSlides;
});
const activeSlide = computed(() => carouselSlides.value[activeIndex.value] || carouselSlides.value[0]);

const titleParts = computed(() => {
    const title = activeSlide.value?.title || '';
    const match = title.match(/^(.*?)(Tech|Technology|Electronics)(.*)$/i);
    if (!match) return { before: title, accent: '', after: '' };
    return { before: match[1], accent: match[2], after: match[3] };
});

function goTo(index) {
    const total = carouselSlides.value.length;
    if (!total) return;
    activeIndex.value = (index + total) % total;
    restart();
}
const next = () => goTo(activeIndex.value + 1);
const previous = () => goTo(activeIndex.value - 1);

function restart() {
    if (timer) window.clearInterval(timer);
    if (typeof window === 'undefined' || carouselSlides.value.length < 2) return;
    timer = window.setInterval(() => {
        activeIndex.value = (activeIndex.value + 1) % carouselSlides.value.length;
    }, AUTO_PLAY_MS);
}

onMounted(() => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) restart();
});
onBeforeUnmount(() => timer && window.clearInterval(timer));
</script>

<template>
    <section v-if="activeSlide" class="bg-[#fbfbfb] pt-7 sm:pt-8" aria-label="Prinx featured products and services">
        <div class="container-shell">
            <div class="relative">
                <div class="relative overflow-hidden rounded-[16px] border border-slate-200 bg-white shadow-[0_14px_44px_rgba(15,23,42,.08)]">
                    <div class="grid min-h-[370px] lg:min-h-[420px] lg:grid-cols-[1.02fr_.98fr]">
                        <Transition name="hero-fade" mode="out-in">
                            <div :key="`${activeSlide.key}-copy`" class="order-2 flex items-center px-6 py-8 sm:px-9 lg:order-1 lg:px-14 lg:py-10 xl:px-16">
                                <div class="max-w-[540px]">
                                    <div class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[.04em] text-[#e2a700]">
                                        <span class="h-[2px] w-6 bg-[#f7b900]"></span>
                                        {{ activeSlide.eyebrow }}
                                    </div>

                                    <h1 class="mt-6 text-[2.4rem] font-semibold leading-[1.06] tracking-[-.035em] text-[#17191f] sm:text-[3rem] lg:text-[3.45rem]">
                                        <template v-if="titleParts.accent">
                                            {{ titleParts.before }}<span class="text-[#f0ad00]">{{ titleParts.accent }}</span>{{ titleParts.after }}
                                        </template>
                                        <template v-else>{{ activeSlide.title }}</template>
                                    </h1>

                                    <p class="mt-5 max-w-[500px] text-[14px] leading-7 text-slate-500 sm:text-[15px]">{{ activeSlide.description }}</p>

                                    <div class="mt-7 flex flex-wrap gap-3">
                                        <Link :href="activeSlide.primaryHref" class="inline-flex h-[49px] items-center gap-3 rounded-[9px] bg-[#f7b900] px-6 text-[13px] font-semibold text-slate-950 shadow-sm transition hover:bg-[#eaae00]">
                                            {{ activeSlide.primaryLabel }}
                                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/></svg>
                                        </Link>
                                        <Link :href="activeSlide.secondaryHref" class="inline-flex h-[49px] items-center gap-3 rounded-[9px] border border-slate-200 bg-white px-6 text-[13px] font-medium text-slate-800 transition hover:border-amber-300 hover:text-amber-700">
                                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 12 8-8 8 8-8 8-8-8Z"/><path stroke-linecap="round" d="M9 12h6"/></svg>
                                            {{ activeSlide.secondaryLabel }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </Transition>

                        <Transition name="hero-media" mode="out-in">
                            <div :key="`${activeSlide.key}-media`" class="relative order-1 min-h-[300px] overflow-hidden bg-[linear-gradient(135deg,#fff_0%,#fffef7_58%,#fff9dc_100%)] lg:order-2 lg:min-h-0">
                                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                    <div class="absolute left-[10%] top-[5%] h-[88%] w-[88%] rounded-full border-[3px] border-[#ffe687] opacity-70"></div>
                                    <div class="absolute left-[17%] top-[13%] h-[74%] w-[74%] rounded-full border border-[#fff2bd]"></div>
                                    <div class="absolute bottom-0 left-[10%] h-[26%] w-[78%] rounded-t-[28px] bg-white shadow-[0_-8px_22px_rgba(15,23,42,.03)]"></div>
                                    <div class="absolute -right-8 top-3 h-[150px] w-[36px] rotate-[27deg] rounded-full bg-[linear-gradient(180deg,#79a93b,#295e22)] opacity-90"></div>
                                    <div class="absolute -right-2 top-[95px] h-[135px] w-[32px] rotate-[52deg] rounded-full bg-[linear-gradient(180deg,#6ca53d,#285c25)] opacity-85"></div>
                                    <div class="absolute -right-12 top-[165px] h-[145px] w-[34px] rotate-[73deg] rounded-full bg-[linear-gradient(180deg,#84b748,#2f6b28)] opacity-80"></div>
                                </div>
                                <img :src="activeSlide.image" :alt="activeSlide.imageAlt" class="hero-product absolute left-1/2 top-1/2 z-10 h-[72%] w-[78%] -translate-x-1/2 -translate-y-[48%] object-contain drop-shadow-[0_24px_22px_rgba(15,23,42,.19)]" fetchpriority="high" decoding="async">
                            </div>
                        </Transition>
                    </div>

                    <div v-if="carouselSlides.length > 1" class="absolute bottom-5 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2" role="tablist" aria-label="Hero slides">
                        <button v-for="(slide, index) in carouselSlides" :key="slide.key" type="button" :aria-label="`Show slide ${index + 1}`" :class="['h-1.5 rounded-full transition-all', index === activeIndex ? 'w-7 bg-[#f7b900]' : 'w-4 bg-slate-400']" @click="goTo(index)"></button>
                    </div>
                </div>

                <button v-if="carouselSlides.length > 1" type="button" class="absolute left-0 top-1/2 z-20 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-[0_7px_22px_rgba(15,23,42,.10)] transition hover:text-amber-600" aria-label="Previous slide" @click="previous">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 6-6 6 6 6"/></svg>
                </button>
                <button v-if="carouselSlides.length > 1" type="button" class="absolute right-0 top-1/2 z-20 flex h-11 w-11 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-[0_7px_22px_rgba(15,23,42,.10)] transition hover:text-amber-600" aria-label="Next slide" @click="next">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m10 6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-fade-enter-active,.hero-fade-leave-active,.hero-media-enter-active,.hero-media-leave-active{transition:opacity .3s ease,transform .3s ease}.hero-fade-enter-from{opacity:0;transform:translateY(7px)}.hero-fade-leave-to{opacity:0;transform:translateY(-5px)}.hero-media-enter-from{opacity:0;transform:translateX(10px)}.hero-media-leave-to{opacity:0;transform:translateX(-8px)}.hero-product{animation:hero-float 5.6s ease-in-out infinite}@keyframes hero-float{0%,100%{transform:translate(-50%,-48%) translateY(0)}50%{transform:translate(-50%,-48%) translateY(-7px)}}@media(prefers-reduced-motion:reduce){.hero-product{animation:none}.hero-fade-enter-active,.hero-fade-leave-active,.hero-media-enter-active,.hero-media-leave-active{transition:none}}
</style>
