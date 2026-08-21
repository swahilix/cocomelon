<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const current = ref(0);
let timer = null;

const slide = computed(() => props.slides?.[current.value] || null);
const itemUrl = computed(() => {
    if (!slide.value?.item) return '/shop';
    return slide.value.item_type === 'service'
        ? `/services/${slide.value.item.slug}`
        : `/products/${slide.value.item.slug}`;
});

const secondaryUrl = computed(() => slide.value?.item_type === 'service' ? '/services' : '/shop');

const go = (index) => {
    const total = props.slides?.length || 0;
    if (!total) return;
    current.value = (index + total) % total;
};

const next = () => go(current.value + 1);
const previous = () => go(current.value - 1);

const start = () => {
    stop();
    if ((props.slides?.length || 0) > 1) timer = window.setInterval(next, 6500);
};

const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
};

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <section class="relative overflow-hidden bg-slate-950 text-white" @mouseenter="stop" @mouseleave="start">
        <div v-if="slide" class="container-shell relative min-h-[520px] py-8 sm:py-10 lg:min-h-[570px]">
            <Transition name="hero-fade" mode="out-in">
                <div :key="slide.id" class="grid min-h-[455px] items-center gap-8 lg:grid-cols-[1.03fr_.97fr]">
                    <div class="relative z-10 max-w-2xl py-4 lg:py-10">
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="slide.badge" class="rounded-full border border-prinx-400/30 bg-prinx-500/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[.16em] text-prinx-300">{{ slide.badge }}</span>
                            <span class="text-xs font-bold uppercase tracking-[.18em] text-prinx-400">{{ slide.eyebrow || (slide.item_type === 'service' ? 'Professional service' : 'Featured technology') }}</span>
                        </div>

                        <h1 class="mt-5 max-w-2xl text-4xl font-extrabold leading-[1.04] tracking-[-.035em] sm:text-5xl lg:text-6xl">
                            {{ slide.headline || slide.item.name }}
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                            {{ slide.description || slide.item.short_description || slide.item.plain_description }}
                        </p>

                        <div v-if="slide.item_type === 'product'" class="mt-5 flex flex-wrap items-center gap-4">
                            <p v-if="slide.item.current_price" class="text-2xl font-extrabold">TZS {{ Number(slide.item.current_price).toLocaleString() }}</p>
                            <span v-if="slide.item.brand?.name" class="text-sm font-semibold text-slate-400">{{ slide.item.brand.name }}</span>
                            <span v-if="slide.item.category?.name" class="text-sm text-slate-500">{{ slide.item.category.name }}</span>
                        </div>
                        <div v-else-if="slide.item.price_label || slide.item.price_from" class="mt-5 text-lg font-bold text-white">
                            {{ slide.item.price_label || `From TZS ${Number(slide.item.price_from).toLocaleString()}` }}
                        </div>

                        <div class="mt-7 flex flex-wrap gap-3">
                            <Link :href="itemUrl" class="btn-primary inline-flex items-center gap-2">
                                {{ slide.primary_label || (slide.item_type === 'service' ? 'View service' : 'View product') }}
                                <Icon name="arrow-right" size="17" />
                            </Link>
                            <Link :href="secondaryUrl" class="inline-flex items-center gap-2 rounded-[8px] border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/10">
                                {{ slide.secondary_label || (slide.item_type === 'service' ? 'All services' : 'Shop all') }}
                            </Link>
                        </div>
                    </div>

                    <div class="relative flex min-h-[310px] items-center justify-center sm:min-h-[390px] lg:min-h-[460px]">
                        <div class="absolute inset-x-[8%] bottom-[3%] top-[12%] rounded-full bg-prinx-500/10 blur-3xl"></div>
                        <img :src="slide.item.image_url" :alt="slide.item.name" class="relative z-10 max-h-[430px] w-full object-contain drop-shadow-2xl" />
                    </div>
                </div>
            </Transition>

            <div v-if="slides.length > 1" class="absolute inset-x-0 bottom-5 z-20 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <button v-for="(_, index) in slides" :key="index" type="button" class="h-1.5 rounded-full transition-all" :class="index === current ? 'w-8 bg-prinx-500' : 'w-3 bg-white/30 hover:bg-white/60'" :aria-label="`Go to slide ${index + 1}`" @click="go(index)"></button>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-white/15 bg-black/20 transition hover:bg-white/10" aria-label="Previous slide" @click="previous"><Icon name="arrow-left-2" size="19" /></button>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-white/15 bg-black/20 transition hover:bg-white/10" aria-label="Next slide" @click="next"><Icon name="arrow-right-3" size="19" /></button>
                </div>
            </div>
        </div>

        <div v-else class="container-shell grid min-h-[470px] items-center gap-8 py-12 md:grid-cols-2">
            <div>
                <p class="section-kicker text-prinx-400">Electronics · Services · Support</p>
                <h1 class="mt-3 text-4xl font-extrabold tracking-tight sm:text-5xl">Technology that works for you.</h1>
                <p class="mt-4 max-w-xl text-slate-300">Reliable electronics, accessories and professional technical services in one place.</p>
                <div class="mt-7 flex gap-3"><Link href="/shop" class="btn-primary">Shop products</Link><Link href="/services" class="btn-light">Explore services</Link></div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-fade-enter-active,.hero-fade-leave-active{transition:opacity .35s ease,transform .35s ease}.hero-fade-enter-from{opacity:0;transform:translateX(14px)}.hero-fade-leave-to{opacity:0;transform:translateX(-14px)}
</style>
