<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const AUTO_PLAY_MS = 7000;
const activeIndex = ref(0);
const progressKey = ref(0);
const paused = ref(false);
const touchStartX = ref(null);
let timer = null;

const money = new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: 'TZS',
    maximumFractionDigits: 0,
});

function plainText(value) {
    return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function normalizedSlide(slide) {
    const item = slide.item || {};
    const isProduct = slide.item_type === 'product';
    const category = item.category?.name || (isProduct ? 'Electronics' : 'Technical support');
    const brand = item.brand?.name || 'Prinx Electronics';
    const stock = item.in_stock
        ? (item.track_stock ? `${item.stock_quantity} available` : 'Available now')
        : 'Currently unavailable';

    const price = isProduct
        ? money.format(Number(item.current_price || item.price || 0))
        : (item.price_label || (item.price_from ? `From ${money.format(Number(item.price_from))}` : 'Request a quote'));

    return {
        key: `${slide.item_type}-${slide.id}`,
        type: slide.item_type,
        eyebrow: slide.eyebrow || (isProduct ? 'Featured product' : 'Professional service'),
        badge: slide.badge || (isProduct ? `${brand} · ${category}` : category),
        title: slide.headline || item.name,
        description: plainText(slide.description || item.short_description || item.plain_description || item.description),
        image: item.image_url || (isProduct ? '/assets/accessory.svg' : '/assets/repair.svg'),
        imageAlt: item.name || 'Prinx Electronics showcase',
        primaryHref: isProduct ? `/products/${encodeURIComponent(item.slug || '')}` : `/services/${encodeURIComponent(item.slug || '')}`,
        primaryLabel: slide.primary_label || (isProduct ? 'View product' : 'View service'),
        secondaryHref: isProduct ? '/shop' : '/services',
        secondaryLabel: slide.secondary_label || (isProduct ? 'Browse products' : 'Explore services'),
        price,
        detailLabel: isProduct ? 'Availability' : 'Turnaround',
        detailValue: isProduct ? stock : (item.duration || 'Based on assessment'),
        extraLabel: isProduct ? 'Warranty' : 'Category',
        extraValue: isProduct ? (item.warranty || 'Ask for details') : category,
    };
}

const fallbackSlides = [
    {
        key: 'fallback-products',
        type: 'product',
        eyebrow: 'Prinx Electronics',
        badge: 'Technology for work, life and business',
        title: 'Better electronics. Smarter choices.',
        description: 'Explore carefully selected devices and accessories with clear specifications, dependable stock information and support from one trusted store.',
        image: '/assets/phone.svg',
        imageAlt: 'Electronics and mobile devices',
        primaryHref: '/shop',
        primaryLabel: 'Explore products',
        secondaryHref: '/services',
        secondaryLabel: 'Book a service',
        price: 'Selected technology',
        detailLabel: 'Information',
        detailValue: 'Clear specifications',
        extraLabel: 'Support',
        extraValue: 'Before and after sale',
    },
    {
        key: 'fallback-services',
        type: 'service',
        eyebrow: 'Prinx technical services',
        badge: 'Repair · Setup · Business support',
        title: 'Professional care for the technology you rely on.',
        description: 'Get diagnostics, repair, upgrades, installation and practical technical help for home, office and institutional technology.',
        image: '/assets/repair.svg',
        imageAlt: 'Professional electronics service',
        primaryHref: '/services',
        primaryLabel: 'Explore services',
        secondaryHref: '/shop',
        secondaryLabel: 'Shop electronics',
        price: 'Professional support',
        detailLabel: 'Service',
        detailValue: 'Skilled technicians',
        extraLabel: 'Solutions',
        extraValue: 'Home and business',
    },
];

const carouselSlides = computed(() => {
    const configured = props.slides
        .filter((slide) => slide?.item && slide?.item_type)
        .map(normalizedSlide);

    return configured.length ? configured : fallbackSlides;
});

const activeSlide = computed(() => carouselSlides.value[activeIndex.value] || carouselSlides.value[0]);

function clearTimer() {
    if (timer) {
        window.clearInterval(timer);
        timer = null;
    }
}

function scheduleTimer() {
    clearTimer();
    if (typeof window === 'undefined' || paused.value || carouselSlides.value.length < 2) return;

    timer = window.setInterval(() => {
        activeIndex.value = (activeIndex.value + 1) % carouselSlides.value.length;
        progressKey.value += 1;
    }, AUTO_PLAY_MS);
}

function goTo(index) {
    const total = carouselSlides.value.length;
    if (!total) return;

    activeIndex.value = (index + total) % total;
    progressKey.value += 1;
    scheduleTimer();
}

const next = () => goTo(activeIndex.value + 1);
const previous = () => goTo(activeIndex.value - 1);

function pauseCarousel() {
    paused.value = true;
    clearTimer();
}

function resumeCarousel() {
    paused.value = false;
    progressKey.value += 1;
    scheduleTimer();
}

function handleTouchStart(event) {
    touchStartX.value = event.touches?.[0]?.clientX ?? null;
    pauseCarousel();
}

function handleTouchEnd(event) {
    const endX = event.changedTouches?.[0]?.clientX;
    if (touchStartX.value !== null && typeof endX === 'number') {
        const difference = touchStartX.value - endX;
        if (Math.abs(difference) > 45) difference > 0 ? next() : previous();
    }

    touchStartX.value = null;
    resumeCarousel();
}

watch(() => carouselSlides.value.length, (length) => {
    if (activeIndex.value >= length) activeIndex.value = 0;
    progressKey.value += 1;
    scheduleTimer();
});

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        paused.value = true;
        return;
    }

    scheduleTimer();
});

onBeforeUnmount(clearTimer);
</script>

<template>
    <section
        v-if="activeSlide"
        class="bg-slate-50 py-4 sm:py-6"
        role="region"
        aria-roledescription="carousel"
        aria-label="Selected products and services"
    >
        <div class="container-shell">
            <div
                class="hero-showcase relative mx-auto max-w-[1160px] overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-[0_18px_55px_rgba(15,23,42,.10)]"
                tabindex="0"
                @mouseenter="pauseCarousel"
                @mouseleave="resumeCarousel"
                @focusin="pauseCarousel"
                @focusout="resumeCarousel"
                @keydown.left.prevent="previous"
                @keydown.right.prevent="next"
                @touchstart.passive="handleTouchStart"
                @touchend.passive="handleTouchEnd"
            >
                <div class="grid lg:min-h-[390px] lg:grid-cols-[1.08fr_.92fr]">
                    <Transition name="hero-content" mode="out-in">
                        <div :key="`${activeSlide.key}-content`" class="relative order-2 flex min-w-0 flex-col justify-center overflow-hidden bg-[#07111f] px-5 py-6 text-white sm:px-7 sm:py-8 lg:order-1 lg:px-10 lg:py-9">
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                <div class="absolute -left-20 -top-24 h-64 w-64 rounded-full bg-prinx-500/15 blur-3xl"></div>
                                <div class="absolute inset-0 opacity-[.14]" style="background-image:linear-gradient(rgba(255,255,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.08) 1px,transparent 1px);background-size:34px 34px;mask-image:linear-gradient(to right,black,transparent 88%)"></div>
                            </div>

                            <div class="relative max-w-[590px]">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="inline-flex shrink-0 items-center gap-2 rounded-[8px] border border-prinx-400/30 bg-prinx-500/10 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-[.15em] text-prinx-300 sm:text-[10px]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-prinx-400"></span>
                                        {{ activeSlide.eyebrow }}
                                    </span>
                                    <span class="truncate text-[9px] font-extrabold uppercase tracking-[.12em] text-slate-400 sm:text-[10px]">{{ activeSlide.badge }}</span>
                                </div>

                                <h1 class="hero-title-clamp mt-4 max-w-[580px] text-[1.9rem] font-black leading-[1.03] tracking-[-.04em] sm:text-[2.35rem] lg:text-[2.7rem]" :title="activeSlide.title">
                                    {{ activeSlide.title }}
                                </h1>

                                <p class="hero-description-clamp mt-3 max-w-[560px] text-[13px] leading-6 text-slate-300 sm:text-sm sm:leading-6">
                                    {{ activeSlide.description }}
                                </p>

                                <div class="mt-5 flex flex-wrap gap-2.5">
                                    <Link :href="activeSlide.primaryHref" class="btn-primary px-5 py-2.5">
                                        {{ activeSlide.primaryLabel }} <span aria-hidden="true">→</span>
                                    </Link>
                                    <Link :href="activeSlide.secondaryHref" class="inline-flex items-center justify-center rounded-[10px] border border-white/15 bg-white/[.05] px-5 py-2.5 text-sm font-extrabold text-white transition hover:border-white/30 hover:bg-white/10">
                                        {{ activeSlide.secondaryLabel }}
                                    </Link>
                                </div>

                                <div class="mt-5 grid grid-cols-2 overflow-hidden rounded-[10px] border border-white/10 bg-white/[.035] sm:grid-cols-3">
                                    <div class="min-w-0 px-3.5 py-3">
                                        <span class="block text-[8px] font-black uppercase tracking-[.14em] text-slate-500">{{ activeSlide.type === 'product' ? 'Price' : 'Pricing' }}</span>
                                        <strong class="mt-1 block truncate text-xs font-black text-white sm:text-sm">{{ activeSlide.price }}</strong>
                                    </div>
                                    <div class="min-w-0 border-l border-white/10 px-3.5 py-3">
                                        <span class="block text-[8px] font-black uppercase tracking-[.14em] text-slate-500">{{ activeSlide.detailLabel }}</span>
                                        <strong class="mt-1 block truncate text-xs font-black text-white sm:text-sm">{{ activeSlide.detailValue }}</strong>
                                    </div>
                                    <div class="hidden min-w-0 border-l border-white/10 px-3.5 py-3 sm:block">
                                        <span class="block text-[8px] font-black uppercase tracking-[.14em] text-slate-500">{{ activeSlide.extraLabel }}</span>
                                        <strong class="mt-1 block truncate text-xs font-black text-white sm:text-sm">{{ activeSlide.extraValue }}</strong>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between gap-4">
                                    <div class="flex min-w-0 items-center gap-2" role="tablist" aria-label="Choose carousel slide">
                                        <button
                                            v-for="(slide, index) in carouselSlides"
                                            :key="slide.key"
                                            type="button"
                                            class="group flex h-7 items-center"
                                            :aria-label="`Show slide ${index + 1}: ${slide.title}`"
                                            :aria-selected="index === activeIndex"
                                            role="tab"
                                            @click="goTo(index)"
                                        >
                                            <span :class="['block h-1 rounded-full transition-all duration-300', index === activeIndex ? 'w-8 bg-prinx-400' : 'w-3 bg-slate-600 group-hover:bg-slate-400']"></span>
                                        </button>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="mr-1 hidden text-[9px] font-black tracking-[.12em] text-slate-500 sm:inline">{{ String(activeIndex + 1).padStart(2, '0') }} / {{ String(carouselSlides.length).padStart(2, '0') }}</span>
                                        <button type="button" class="hero-arrow" aria-label="Previous slide" @click="previous">←</button>
                                        <button type="button" class="hero-arrow" aria-label="Next slide" @click="next">→</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <Transition name="hero-media" mode="out-in">
                        <div :key="`${activeSlide.key}-media`" class="relative order-1 min-h-[215px] overflow-hidden bg-[linear-gradient(145deg,#fff_0%,#fff8f1_55%,#ffead7_100%)] sm:min-h-[260px] lg:order-2 lg:min-h-0">
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                <div class="absolute -right-14 -top-20 h-64 w-64 rounded-full border-[34px] border-prinx-100/60"></div>
                                <div class="absolute bottom-0 left-0 h-36 w-36 bg-[radial-gradient(circle,rgba(249,115,22,.18)_1px,transparent_1.5px)] [background-size:15px_15px] [mask-image:linear-gradient(45deg,black,transparent_80%)]"></div>
                                <div class="absolute left-1/2 top-[48%] h-[65%] w-[65%] -translate-x-1/2 -translate-y-1/2 rounded-full bg-prinx-400/15 blur-3xl"></div>
                                <div class="absolute bottom-[13%] left-1/2 h-6 w-[52%] -translate-x-1/2 rounded-full bg-slate-950/15 blur-xl"></div>
                                <div class="absolute inset-y-0 left-0 hidden w-px bg-slate-200 lg:block"></div>
                            </div>

                            <div class="absolute left-4 top-4 z-20 rounded-[8px] border border-prinx-200 bg-white/90 px-2.5 py-1.5 text-[8px] font-black uppercase tracking-[.14em] text-prinx-700 shadow-sm backdrop-blur sm:left-5 sm:top-5 sm:text-[9px]">
                                {{ activeSlide.type === 'product' ? 'Product spotlight' : 'Service spotlight' }}
                            </div>

                            <img
                                :src="activeSlide.image"
                                :alt="activeSlide.imageAlt"
                                :title="activeSlide.title"
                                class="showcase-product absolute left-1/2 top-1/2 z-10 h-[72%] w-[82%] -translate-x-1/2 -translate-y-[50%] object-contain drop-shadow-[0_22px_24px_rgba(15,23,42,.22)] sm:h-[76%] sm:w-[80%] lg:h-[78%] lg:w-[84%]"
                                fetchpriority="high"
                                decoding="async"
                            >

                            <div class="absolute bottom-4 right-4 z-20 rounded-[8px] border border-white/80 bg-white/90 px-3 py-2 text-right shadow-[0_10px_25px_rgba(15,23,42,.10)] backdrop-blur sm:bottom-5 sm:right-5">
                                <span class="block text-[8px] font-black uppercase tracking-[.13em] text-slate-400">{{ activeSlide.type === 'product' ? 'Current price' : 'Starting from' }}</span>
                                <strong class="mt-0.5 block max-w-[185px] truncate text-sm font-black text-slate-950 sm:text-base">{{ activeSlide.price }}</strong>
                            </div>
                        </div>
                    </Transition>
                </div>

                <div class="absolute inset-x-0 bottom-0 z-30 h-[3px] bg-slate-200/70" aria-hidden="true">
                    <div v-if="!paused && carouselSlides.length > 1" :key="progressKey" class="hero-progress h-full origin-left bg-prinx-500" :style="{ animationDuration: `${AUTO_PLAY_MS}ms` }"></div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-title-clamp {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.hero-description-clamp {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.hero-arrow {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, .14);
    background: rgba(255, 255, 255, .06);
    color: white;
    font-size: .875rem;
    font-weight: 900;
    transition: border-color .2s ease, background-color .2s ease, color .2s ease;
}

.hero-arrow:hover {
    border-color: rgba(249, 115, 22, .65);
    background: #f97316;
}

.hero-progress {
    animation-name: hero-progress;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}

.showcase-product {
    animation: product-float 5.8s ease-in-out infinite;
}

@keyframes hero-progress {
    from { transform: scaleX(0); }
    to { transform: scaleX(1); }
}

@keyframes product-float {
    0%, 100% { transform: translate(-50%, -50%) translateY(0); }
    50% { transform: translate(-50%, -50%) translateY(-7px); }
}

.hero-content-enter-active,
.hero-content-leave-active,
.hero-media-enter-active,
.hero-media-leave-active {
    transition: opacity .36s ease, transform .36s ease;
}

.hero-content-enter-from { opacity: 0; transform: translateY(9px); }
.hero-content-leave-to { opacity: 0; transform: translateY(-6px); }
.hero-media-enter-from { opacity: 0; transform: translateX(12px) scale(.995); }
.hero-media-leave-to { opacity: 0; transform: translateX(-8px) scale(.995); }

@media (max-width: 639px) {
    .hero-title-clamp { -webkit-line-clamp: 3; }
    .hero-description-clamp { -webkit-line-clamp: 2; }
}

@media (prefers-reduced-motion: reduce) {
    .hero-progress,
    .showcase-product { animation: none; }
    .hero-content-enter-active,
    .hero-content-leave-active,
    .hero-media-enter-active,
    .hero-media-leave-active { transition: none; }
}
</style>
