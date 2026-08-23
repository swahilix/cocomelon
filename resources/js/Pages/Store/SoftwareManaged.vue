<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({ softwareSolutions: { type: Array, default: () => [] } });
const page = usePage();
const store = computed(() => page.props.store || {});
const featuredImages = computed(() => props.softwareSolutions.filter((item) => item.image_url).slice(0, 3));

const reasons = [
    ['Custom-Built', 'Solutions tailored to your business processes.', 'puzzle'],
    ['Scalable', 'Systems that grow with your business and needs.', 'chart'],
    ['Secure & Reliable', 'Built with security and performance in mind.', 'shield'],
    ['Local Support', 'Fast local support when you need us most.', 'headset'],
    ['Training Included', 'We train your team for smooth daily operations.', 'people'],
];

const implementation = [
    ['System Setup', 'We set up your system based on your business needs.', 'screen'],
    ['Customization', 'We customize features to match your workflow.', 'sliders'],
    ['Data Migration', 'We migrate your data safely and accurately.', 'database'],
    ['Staff Training', 'We train your team for better productivity.', 'training'],
    ['Ongoing Support', 'We provide continuous support & maintenance.', 'headset'],
];
</script>

<template>
    <AppLayout>
        <SeoHead title="Custom Software Solutions" description="Custom software systems, POS, microfinance, school, hospital, HR, SACCO, hotel and ERP solutions from Prinx." canonical="/software" />

        <main class="bg-[#fbfbfb]">
            <div class="container-shell pb-7 pt-4 sm:pt-5">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="transition hover:text-prinx-600">Home</Link><span>/</span><span class="text-prinx-600">Software</span>
                </nav>

                <section class="relative mt-3 overflow-hidden border-b border-slate-100 pb-5 lg:min-h-[292px]">
                    <div class="relative z-10 grid items-center gap-8 lg:grid-cols-[.72fr_1.28fr]">
                        <div class="py-4 lg:py-7">
                            <h1 class="max-w-[520px] text-[30px] font-semibold leading-[1.15] tracking-[-.035em] text-slate-950 sm:text-[39px]">Custom Software Solutions</h1>
                            <p class="mt-4 max-w-[500px] text-[14px] leading-7 text-slate-600">Power your business with custom-built software systems developed by Prinx. Built for companies, schools, institutions and organizations of all sizes.</p>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <a href="#software-solutions" class="inline-flex h-11 items-center justify-center gap-2 rounded-[6px] bg-prinx-500 px-6 text-xs font-semibold text-white transition hover:bg-prinx-600">
                                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                                    Explore Our Solutions
                                </a>
                                <Link href="/pages/contact" class="inline-flex h-11 items-center justify-center gap-2 rounded-[6px] border border-prinx-300 bg-white px-6 text-xs font-semibold text-slate-800 transition hover:bg-prinx-50 hover:text-prinx-600">
                                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                                    Talk to an Expert
                                </Link>
                            </div>
                        </div>

                        <div class="relative hidden h-[265px] lg:block">
                            <div class="absolute inset-x-0 bottom-3 h-[160px] rounded-[50%] bg-prinx-50/70 blur-xl"></div>

                            <div class="absolute right-[10%] top-0 h-[210px] w-[390px] rounded-[10px] border-[5px] border-slate-950 bg-slate-950 p-1 shadow-[0_20px_45px_rgba(15,23,42,.13)]">
                                <div class="h-full overflow-hidden rounded-[5px] bg-[#f8fafc]">
                                    <img v-if="featuredImages[1]" :src="featuredImages[1].image_url" :alt="featuredImages[1].name" class="h-full w-full object-cover">
                                    <div v-else class="grid h-full grid-cols-[72px_1fr]"><div class="bg-[#15334a] p-2"><div class="h-3 w-10 rounded bg-prinx-400"></div><div v-for="n in 7" :key="n" class="mt-3 h-1.5 rounded bg-slate-500/50"></div></div><div class="p-3"><div class="grid grid-cols-3 gap-2"><div v-for="n in 3" :key="n" class="h-11 rounded border border-slate-200 bg-white p-2"><div class="h-1.5 w-8 rounded bg-slate-200"></div><div class="mt-2 h-2 w-12 rounded bg-prinx-100"></div></div></div><div class="mt-3 grid grid-cols-2 gap-2"><div class="h-24 rounded border border-slate-200 bg-white p-2"><div class="flex h-full items-end gap-1"><span v-for="n in 11" :key="n" class="w-2 rounded-t bg-prinx-200" :style="{height: `${18 + ((n * 13) % 48)}%`}"></span></div></div><div class="h-24 rounded border border-slate-200 bg-white p-3"><div v-for="n in 5" :key="n" class="mb-2 flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-slate-200"></span><span class="h-1.5 flex-1 rounded bg-slate-100"></span></div></div></div></div></div>
                                </div>
                            </div>

                            <div class="absolute bottom-0 left-[14%] z-10 h-[190px] w-[425px] rounded-t-[10px] border-[5px] border-slate-800 bg-slate-900 p-1 shadow-[0_20px_40px_rgba(15,23,42,.16)]">
                                <div class="h-full overflow-hidden rounded-[4px] bg-[#f8fafc]">
                                    <img v-if="featuredImages[0]" :src="featuredImages[0].image_url" :alt="featuredImages[0].name" class="h-full w-full object-cover">
                                    <div v-else class="grid h-full grid-cols-[68px_1fr]"><div class="bg-[#17364b] px-2 py-3"><div class="h-3 w-11 rounded bg-prinx-400"></div><div v-for="n in 7" :key="n" class="mt-3 h-1.5 rounded bg-slate-500/55"></div></div><div class="p-3"><div class="grid grid-cols-3 gap-2"><div v-for="n in 3" :key="n" class="h-11 rounded border border-slate-200 bg-white p-2"><div class="h-1.5 w-10 rounded bg-slate-200"></div><div class="mt-2 h-2 w-12 rounded bg-prinx-200"></div></div></div><div class="mt-3 grid grid-cols-[1.2fr_.8fr] gap-2"><div class="h-[78px] rounded border border-slate-200 bg-white p-2"><svg viewBox="0 0 180 55" class="h-full w-full"><polyline points="0,44 22,37 43,40 66,24 88,31 110,18 135,23 160,8 180,13" fill="none" stroke="currentColor" class="text-prinx-500" stroke-width="2"/></svg></div><div class="flex h-[78px] items-center justify-center rounded border border-slate-200 bg-white"><div class="flex h-12 w-12 items-center justify-center rounded-full border-[8px] border-prinx-300 text-[9px] font-bold text-slate-700">72%</div></div></div></div></div>
                                </div>
                            </div>
                            <div class="absolute bottom-[-10px] left-[9%] z-20 h-[10px] w-[470px] rounded-b-[50%] bg-slate-400 shadow-md"></div>

                            <div class="absolute bottom-3 right-[4%] z-20 h-[150px] w-[78px] rounded-[15px] border-[5px] border-slate-900 bg-white p-1 shadow-[0_16px_32px_rgba(15,23,42,.16)]">
                                <div class="h-full overflow-hidden rounded-[8px] bg-slate-50">
                                    <img v-if="featuredImages[2]" :src="featuredImages[2].image_url" :alt="featuredImages[2].name" class="h-full w-full object-cover">
                                    <div v-else class="p-2"><div class="mx-auto h-1 w-7 rounded bg-slate-300"></div><div class="mt-4 h-1.5 w-10 rounded bg-slate-300"></div><div class="mt-2 h-2 w-12 rounded bg-prinx-200"></div><svg viewBox="0 0 60 50" class="mt-3 h-12 w-full"><polyline points="0,40 9,36 18,39 28,23 38,30 48,12 60,16" fill="none" stroke="currentColor" class="text-prinx-500" stroke-width="2"/></svg><div class="mx-auto mt-3 flex h-10 w-10 items-center justify-center rounded-full border-[5px] border-prinx-200 text-[8px] font-bold">75%</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="software-solutions" class="mt-4">
                    <h2 class="text-base font-semibold text-slate-950">Our Custom Software Solutions</h2>
                    <div v-if="softwareSolutions.length" class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-8">
                        <article v-for="(solution, index) in softwareSolutions" :key="solution.id" class="group flex min-h-[250px] flex-col rounded-[8px] border border-slate-200 bg-white p-3 transition hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.06)]">
                            <div class="h-[92px] overflow-hidden rounded-[5px] border border-slate-200 bg-[#f7fafc]">
                                <img v-if="solution.image_url" :src="solution.image_url" :alt="solution.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                                <div v-else class="grid h-full grid-cols-[22px_1fr] bg-white"><div class="bg-[#18394e] p-1"><span class="block h-2 w-3 rounded-sm bg-prinx-400"></span><span v-for="n in 5" :key="n" class="mt-2 block h-1 rounded bg-slate-500/50"></span></div><div class="p-1.5"><div class="grid grid-cols-3 gap-1"><span v-for="n in 3" :key="n" class="h-4 rounded-sm bg-prinx-50"></span></div><div class="mt-1.5 grid grid-cols-2 gap-1"><span class="h-10 rounded-sm bg-slate-50"><svg viewBox="0 0 50 30" class="h-full w-full"><polyline :points="index % 2 ? '0,23 10,18 20,20 30,9 40,14 50,5' : '0,24 10,21 20,12 30,18 40,8 50,11'" fill="none" stroke="currentColor" class="text-prinx-500" stroke-width="1.5"/></svg></span><span class="grid h-10 grid-cols-3 items-end gap-1 rounded-sm bg-slate-50 p-1"><i class="h-3 bg-prinx-100"></i><i class="h-6 bg-prinx-200"></i><i class="h-4 bg-prinx-300"></i></span></div></div></div>
                            </div>
                            <h3 class="mt-3 text-[13px] font-semibold leading-5 text-slate-950">{{ solution.name }}</h3>
                            <p class="mt-2 flex-1 text-xs leading-5 text-slate-500">{{ solution.short_description || 'Custom software solution built for your business requirements.' }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-1.5">
                                <Link :href="`/software/${solution.slug}`" class="inline-flex h-8 items-center justify-center rounded-[5px] border border-prinx-300 bg-white px-2 text-xs font-semibold text-prinx-600 hover:bg-prinx-50">View Details</Link>
                                <a v-if="solution.demo_url" :href="solution.demo_url" target="_blank" rel="noopener" class="inline-flex h-8 items-center justify-center rounded-[5px] bg-prinx-500 px-2 text-xs font-semibold text-white hover:bg-prinx-600">Request Demo</a>
                                <Link v-else href="/pages/contact" class="inline-flex h-8 items-center justify-center rounded-[5px] bg-prinx-500 px-2 text-xs font-semibold text-white hover:bg-prinx-600">Request Demo</Link>
                            </div>
                        </article>
                    </div>
                    <div v-else class="mt-3 rounded-[8px] border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500">Software solutions are being prepared. Contact Prinx for a custom system consultation.</div>
                </section>

                <section class="mt-4 grid gap-3 xl:grid-cols-[1.35fr_.85fr]">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Why Choose Prinx Software?</h2>
                        <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-5">
                            <article v-for="reason in reasons" :key="reason[0]" class="rounded-[8px] border border-slate-200 bg-white p-4"><div class="flex h-10 w-10 items-center justify-center text-prinx-600"><svg viewBox="0 0 24 24" class="h-8 w-8 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg></div><h3 class="mt-2 text-xs font-semibold text-slate-900">{{ reason[0] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ reason[1] }}</p></article>
                        </div>
                    </div>
                    <div class="relative overflow-hidden rounded-[8px] border border-slate-200 bg-gradient-to-r from-white to-prinx-50 p-5">
                        <div class="relative z-10 max-w-[420px]"><h2 class="text-base font-semibold text-slate-950">Let's Build the Right Solution for You</h2><p class="mt-2 text-xs leading-5 text-slate-600">Tell us about your business needs and our team will recommend the best solution for you.</p><div class="mt-4 flex flex-wrap gap-2"><Link href="/pages/contact" class="inline-flex h-9 items-center justify-center rounded-[5px] bg-prinx-500 px-5 text-xs font-semibold text-white hover:bg-prinx-600">Request a Demo</Link><Link href="/pages/contact" class="inline-flex h-9 items-center justify-center rounded-[5px] border border-prinx-300 bg-white px-5 text-xs font-semibold text-slate-700 hover:bg-prinx-50">Schedule a Consultation</Link></div></div>
                        <div class="pointer-events-none absolute bottom-[-20px] right-[-20px] h-32 w-32 rounded-full bg-prinx-100"></div>
                    </div>
                </section>

                <section class="mt-4">
                    <h2 class="text-base font-semibold text-slate-950">Our Implementation & Support Services</h2>
                    <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <article v-for="item in implementation" :key="item[0]" class="rounded-[8px] border border-slate-200 bg-white p-4"><div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center text-prinx-600"><svg viewBox="0 0 24 24" class="h-8 w-8 fill-none stroke-current" stroke-width="1.7"><rect x="3" y="4" width="18" height="13" rx="2"/><path stroke-linecap="round" d="M8 21h8M12 17v4"/></svg></span><div><h3 class="text-xs font-semibold text-slate-900">{{ item[0] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ item[1] }}</p><Link href="/pages/contact" class="mt-2 inline-flex text-xs font-semibold text-prinx-600">Learn More →</Link></div></div></article>
                        <article class="rounded-[8px] border border-slate-200 bg-white p-4"><h3 class="text-xs font-semibold text-slate-900">Need help choosing the right solution?</h3><p class="mt-2 text-xs leading-5 text-slate-500">Our experts are ready to help you.</p><Link href="/pages/contact" class="mt-3 inline-flex h-9 items-center justify-center rounded-[5px] border border-slate-300 px-4 text-xs font-semibold text-slate-700 hover:border-prinx-300 hover:text-prinx-600">Contact Us</Link></article>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>