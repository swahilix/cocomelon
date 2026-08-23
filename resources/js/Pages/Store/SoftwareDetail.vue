<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({ software: Object });
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="software.meta_title || software.name"
            :description="software.meta_description || software.short_description"
            :canonical="`/software/${software.slug}`"
        />

        <main class="bg-[#fbfbfb]">
            <div class="container-shell pb-10 pt-4 sm:pt-5">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="hover:text-prinx-600">Home</Link><span>/</span><Link href="/software" class="hover:text-prinx-600">Software</Link><span>/</span><span class="text-prinx-600">{{ software.name }}</span>
                </nav>

                <section class="mt-4 grid gap-7 rounded-[9px] border border-slate-200 bg-white p-5 lg:grid-cols-[1fr_.95fr] lg:p-8">
                    <div>
                        <p v-if="software.featured" class="mb-3 inline-flex rounded-full bg-prinx-50 px-3 py-1 text-xs font-semibold text-prinx-600">Featured software</p>
                        <h1 class="max-w-2xl text-[30px] font-semibold leading-tight tracking-[-.03em] text-slate-950 sm:text-[38px]">{{ software.name }}</h1>
                        <p class="mt-4 max-w-2xl text-[14px] leading-7 text-slate-600">{{ software.short_description }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a v-if="software.demo_url" :href="software.demo_url" target="_blank" rel="noopener" class="inline-flex h-11 items-center justify-center rounded-[6px] bg-prinx-500 px-6 text-xs font-semibold text-white hover:bg-prinx-600">Request Demo</a>
                            <Link v-else href="/pages/contact" class="inline-flex h-11 items-center justify-center rounded-[6px] bg-prinx-500 px-6 text-xs font-semibold text-white hover:bg-prinx-600">Request Demo</Link>
                            <Link href="/pages/contact" class="inline-flex h-11 items-center justify-center rounded-[6px] border border-prinx-300 bg-white px-6 text-xs font-semibold text-slate-800 hover:bg-prinx-50 hover:text-prinx-600">Talk to an Expert</Link>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50">
                        <img v-if="software.image_url" :src="software.image_url" :alt="software.name" class="aspect-video h-full w-full object-cover">
                        <div v-else class="grid aspect-video grid-cols-[76px_1fr] bg-white">
                            <div class="bg-[#18394e] p-3"><div class="h-3 w-10 rounded bg-prinx-400"></div><div v-for="n in 7" :key="n" class="mt-4 h-1.5 rounded bg-slate-500/50"></div></div>
                            <div class="p-4"><div class="grid grid-cols-3 gap-3"><div v-for="n in 3" :key="n" class="h-14 rounded border border-slate-200 bg-white p-3"><div class="h-2 w-12 rounded bg-slate-200"></div><div class="mt-3 h-3 w-16 rounded bg-prinx-100"></div></div></div><div class="mt-4 grid grid-cols-[1.3fr_.7fr] gap-3"><div class="h-32 rounded border border-slate-200 bg-white p-4"><svg viewBox="0 0 180 70" class="h-full w-full"><polyline points="0,55 22,48 43,50 66,30 88,38 110,22 135,28 160,12 180,16" fill="none" stroke="currentColor" class="text-prinx-500" stroke-width="3"/></svg></div><div class="flex h-32 items-center justify-center rounded border border-slate-200 bg-white"><div class="flex h-20 w-20 items-center justify-center rounded-full border-[12px] border-prinx-200 text-sm font-bold text-slate-700">72%</div></div></div></div>
                        </div>
                    </div>
                </section>

                <section class="mt-5 grid gap-5 lg:grid-cols-[1fr_320px]">
                    <article class="rounded-[8px] border border-slate-200 bg-white p-5 lg:p-7">
                        <h2 class="text-lg font-semibold text-slate-950">About this solution</h2>
                        <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600">{{ software.description || software.short_description }}</div>
                    </article>
                    <aside class="rounded-[8px] border border-prinx-100 bg-prinx-50 p-5">
                        <h2 class="text-base font-semibold text-slate-950">Need this system?</h2>
                        <p class="mt-2 text-xs leading-5 text-slate-600">Prinx can customize, deploy, migrate data, train your team and provide ongoing support.</p>
                        <Link href="/pages/contact" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-[6px] bg-prinx-500 px-5 text-xs font-semibold text-white hover:bg-prinx-600">Discuss Your Requirements</Link>
                    </aside>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
