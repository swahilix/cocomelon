<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';

defineProps({ guides: Object });
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Buying Guides | Prinx Tech"
            description="Practical electronics buying guides from Prinx Tech. Compare features, understand products and choose the right tech for your needs."
            canonical="/guides"
        />

        <main class="bg-[#fbfbfb]">
            <section class="container-shell py-8 sm:py-10">
                <div class="max-w-3xl">
                    <p class="section-kicker">Prinx Tech knowledge</p>
                    <h1 class="section-title">Electronics Buying Guides</h1>
                    <p class="mt-3 text-[13px] leading-6 text-slate-600">Simple, practical guidance to help you compare products and make a better buying decision before you order.</p>
                </div>

                <div v-if="guides.data?.length" class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="guide in guides.data" :key="guide.id" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                        <Link :href="`/guides/${guide.slug}`" class="block">
                            <img v-if="guide.cover_image_url" :src="guide.cover_image_url" :alt="guide.title" class="aspect-[16/9] w-full object-cover" loading="lazy" decoding="async">
                            <div v-else class="flex aspect-[16/9] items-center justify-center bg-slate-100 text-4xl text-slate-300">⌁</div>
                        </Link>
                        <div class="p-4">
                            <div class="flex flex-wrap items-center gap-2 text-[9px] text-slate-400">
                                <Link v-if="guide.category" :href="`/shop/category/${guide.category.slug}`" class="font-semibold text-prinx-600 hover:text-prinx-700">{{ guide.category.name }}</Link>
                                <span v-if="guide.published_at">{{ new Date(guide.published_at).toLocaleDateString() }}</span>
                            </div>
                            <Link :href="`/guides/${guide.slug}`" class="mt-2 line-clamp-2 block text-[15px] font-semibold leading-5 text-slate-900 hover:text-prinx-600">{{ guide.title }}</Link>
                            <p class="mt-2 line-clamp-3 text-[11px] leading-5 text-slate-600">{{ guide.excerpt || guide.body }}</p>
                            <Link :href="`/guides/${guide.slug}`" class="mt-4 inline-flex text-[11px] font-semibold text-prinx-600 hover:text-prinx-700">Read guide →</Link>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-7 rounded-[8px] border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Buying guides are being prepared. Check back soon.</div>

                <div v-if="guides.links?.length > 3" class="mt-7 flex flex-wrap gap-2">
                    <Link v-for="link in guides.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded-[6px] border px-3 py-2 text-[11px]', link.active ? 'border-prinx-500 bg-prinx-500 text-white' : 'border-slate-200 bg-white text-slate-600', !link.url ? 'pointer-events-none opacity-40' : '']" />
                </div>
            </section>
        </main>
    </AppLayout>
</template>
