<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ reels: { type: Array, default: () => [] } });
const track = ref(null);

const scroll = (direction) => {
    if (!track.value) return;
    track.value.scrollBy({ left: direction * Math.min(track.value.clientWidth * 0.8, 760), behavior: 'smooth' });
};
</script>

<template>
    <section v-if="reels.length" class="container-shell py-9 sm:py-12">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="section-kicker">Product reels</p>
                <h2 class="section-title">See products in action</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Quick product and service highlights from Prinx.</p>
            </div>
            <div class="hidden gap-2 sm:flex">
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white transition hover:border-prinx-300 hover:text-prinx-600" aria-label="Previous reels" @click="scroll(-1)"><Icon name="arrow-left-2" size="18" /></button>
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white transition hover:border-prinx-300 hover:text-prinx-600" aria-label="Next reels" @click="scroll(1)"><Icon name="arrow-right-3" size="18" /></button>
            </div>
        </div>

        <div ref="track" class="mt-5 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <Link v-for="reel in reels" :key="reel.id" :href="reel.item_url" class="group relative w-[66vw] max-w-[250px] shrink-0 snap-start overflow-hidden rounded-[10px] bg-slate-950 text-white sm:w-[225px]">
                <div class="relative aspect-[9/14] overflow-hidden">
                    <video v-if="reel.media_type === 'video' && reel.media_url" :src="reel.media_url" :poster="reel.cover_url" muted loop playsinline preload="metadata" class="h-full w-full object-cover" @mouseenter="$event.currentTarget.play()" @mouseleave="$event.currentTarget.pause()"></video>
                    <img v-else :src="reel.cover_url || reel.media_url || reel.item?.image_url" :alt="reel.title" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>
                    <span v-if="reel.media_type === 'video'" class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/40 backdrop-blur"><Icon name="play" size="18" /></span>
                    <div class="absolute inset-x-0 bottom-0 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[.14em] text-prinx-300">{{ reel.item?.category?.name || (reel.item_type === 'service' ? 'Service' : 'Product') }}</p>
                        <h3 class="mt-1 line-clamp-2 text-base font-extrabold leading-5">{{ reel.title }}</h3>
                        <p v-if="reel.caption" class="mt-1.5 line-clamp-2 text-xs leading-5 text-slate-300">{{ reel.caption }}</p>
                        <div class="mt-3 flex items-center justify-between gap-2 text-xs font-bold">
                            <span v-if="reel.item_type === 'product' && reel.item?.current_price">TZS {{ Number(reel.item.current_price).toLocaleString() }}</span>
                            <span v-else>{{ reel.item?.price_label || reel.cta_label }}</span>
                            <span class="inline-flex items-center gap-1 text-prinx-300">{{ reel.cta_label }}<Icon name="arrow-right" size="14" /></span>
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    </section>
</template>
