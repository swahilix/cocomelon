<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const page = usePage();
const store = computed(() => page.props.store || {});

const solutions = [
    { title: 'POS & Inventory Management System', text: 'Manage sales, stock, purchases and reports in real time.', icon: 'pos' },
    { title: 'Microfinance Management System', text: 'Manage loans, savings, clients and repayments efficiently.', icon: 'finance' },
    { title: 'School Management System', text: 'Admissions, student records, fees, exams & communication.', icon: 'school' },
    { title: 'Hospital / Clinic Management System', text: 'Patient records, appointments, billing & pharmacy management.', icon: 'hospital' },
    { title: 'HR & Payroll Management System', text: 'Manage employees, payroll, leaves and performance.', icon: 'hr' },
    { title: 'SACCO / Loan Management System', text: 'Member management, savings, shares & loan processing.', icon: 'sacco' },
    { title: 'Hotel / Booking Management System', text: 'Reservations, rooms, guests, billing & housekeeping.', icon: 'hotel' },
    { title: 'Custom Business Website / ERP Solutions', text: 'Tailored web & ERP solutions for your unique business.', icon: 'erp' },
];

const capabilities = [
    ['Custom Development', 'Built around your needs', 'code'],
    ['Implementation Support', 'We set up and guide you', 'support'],
    ['Training & Handover', 'Empower your team', 'people'],
    ['Cloud Hosting', 'Secure & reliable hosting', 'cloud'],
    ['Deployment', 'On-premise or cloud', 'deploy'],
    ['Maintenance & Updates', 'Keep your system running', 'shield'],
    ['After-Sales Support', "We're here for you", 'headset'],
];

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
        <SeoHead
            title="Custom Software Solutions"
            description="Custom software systems, POS, microfinance, school, hospital, HR, SACCO, hotel and ERP solutions from Prinx."
            canonical="/software"
        />

        <main class="bg-[#fbfbfb]">
            <div class="container-shell pb-7 pt-4 sm:pt-5">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
                    <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                    <span>/</span>
                    <span class="text-prinx-600">Software</span>
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
                                    <div class="flex h-6 items-center gap-2 bg-slate-800 px-3"><span class="h-2 w-2 rounded-full bg-prinx-400"></span><span class="h-1.5 w-16 rounded bg-slate-500"></span></div>
                                    <div class="grid h-[calc(100%-24px)] grid-cols-[72px_1fr]">
                                        <div class="bg-[#15334a] p-2"><div class="h-3 w-10 rounded bg-prinx-400"></div><div v-for="n in 6" :key="n" class="mt-3 h-1.5 rounded bg-slate-500/50"></div></div>
                                        <div class="p-3"><div class="grid grid-cols-3 gap-2"><div v-for="n in 3" :key="n" class="h-10 rounded border border-slate-200 bg-white p-2"><div class="h-1.5 w-8 rounded bg-slate-200"></div><div class="mt-2 h-2 w-12 rounded bg-prinx-100"></div></div></div><div class="mt-3 grid grid-cols-2 gap-2"><div class="h-20 rounded border border-slate-200 bg-white p-2"><div class="flex h-full items-end gap-1"><span v-for="n in 11" :key="n" class="w-2 rounded-t bg-prinx-200" :style="{height: `${18 + ((n * 13) % 48)}%`}"></span></div></div><div class="h-20 rounded border border-slate-200 bg-white p-2"><div v-for="n in 4" :key="n" class="mb-2 flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-slate-200"></span><span class="h-1.5 flex-1 rounded bg-slate-100"></span></div></div></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="absolute bottom-0 left-[14%] z-10 h-[190px] w-[425px] rounded-t-[10px] border-[5px] border-slate-800 bg-slate-900 p-1 shadow-[0_20px_40px_rgba(15,23,42,.16)]">
                                <div class="h-full overflow-hidden rounded-[4px] bg-[#f8fafc]">
                                    <div class="grid h-full grid-cols-[68px_1fr]">
                                        <div class="bg-[#17364b] px-2 py-3"><div class="h-3 w-11 rounded bg-prinx-400"></div><div v-for="n in 7" :key="n" class="mt-3 h-1.5 rounded bg-slate-500/55"></div></div>
                                        <div class="p-3"><div class="flex items-center justify-between"><div><div class="h-2 w-24 rounded bg-slate-300"></div><div class="mt-1 h-1.5 w-14 rounded bg-slate-150"></div></div><div class="h-6 w-16 rounded bg-prinx-100"></div></div><div class="mt-3 grid grid-cols-3 gap-2"><div v-for="n in 3" :key="n" class="h-11 rounded border border-slate-200 bg-white p-2"><div class="h-1.5 w-10 rounded bg-slate-200"></div><div class="mt-2 h-2 w-12 rounded bg-prinx-200"></div></div></div><div class="mt-3 grid grid-cols-[1.2fr_.8fr] gap-2"><div class="h-72 max-h-[72px] rounded border border-slate-200 bg-white p-2"><svg viewBox="0 0 180 55" class="h-full w-full"><polyline points="0,44 22,37 43,40 66,24 88,31 110,18 135,23 160,8 180,13" fill="none" stroke="currentColor" class="text-prinx-500" stroke-width="2"/></svg></div><div class="flex h-[72px] items-center justify-center rounded border border-slate-200 bg-white"><div class="flex h-12 w-12 items-center justify-center rounded-full border-[8px] border-prinx-300 text-[9px] font-bold text-slate-700">72%</div></div></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="absolute bottom-[-10px] left-[9%] z-20 h-[10px] w-[470px] rounded-b-[50%] bg-slate-400 shadow-md"></div>

                            <div class="absolute bottom-3 right-[4%] z-20 h-[150px] w-[78px] rounded-[15px] border-[5px] border-slate-900 bg-white p-1 shadow-[0_16px_32px_rgba(15,23,42,.16)]">
                                <div class="h-full rounded-[8px] bg-slate-50 p-2"><div class="mx-auto h-1 w-7 rounded bg-slate-300"></div><div class="mt-4 h-1.5 w-10 rounded bg-slate-300"></div><div class="mt-2 h-2 w-12 rounded bg-prinx-200"></div><svg viewBox="0 0 60 50" class="mt-3 h-12 w-full"><polyline points="0,40 9,36 18,39 28,23 38,30 48,12 60,16" fill="none" stroke="currentColor" class="text-prinx-500" stroke-width="2"/></svg><div class="mx-auto mt-3 flex h-10 w-10 items-center justify-center rounded-full border-[5px] border-prinx-200 text-[8px] font-bold">75%</div></div>
                            </div>

                            <div class="absolute left-[2%] top-[40px] flex h-12 w-12 items-center justify-center rounded-[8px] border border-prinx-100 bg-white/75 text-prinx-500 shadow-sm"><svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m9 7-4 5 4 5M15 7l4 5-4 5M13 4l-2 16"/></svg></div>
                            <div class="absolute left-[10%] top-[120px] flex h-11 w-11 items-center justify-center rounded-[8px] border border-prinx-100 bg-white/75 text-prinx-500 shadow-sm"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.7"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg></div>
                            <div class="absolute right-[1%] top-[48px] flex h-12 w-12 items-center justify-center rounded-[8px] border border-prinx-100 bg-white/75 text-prinx-500 shadow-sm"><svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" d="M5 19V9M10 19V5M15 19v-7M20 19V3"/></svg></div>
                        </div>
                    </div>
                </section>

                <section id="software-solutions" class="mt-4">
                    <h2 class="text-base font-semibold text-slate-950">Our Custom Software Solutions</h2>
                    <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-8">
                        <article v-for="(solution, index) in solutions" :key="solution.title" class="group flex min-h-[250px] flex-col rounded-[8px] border border-slate-200 bg-white p-3 transition hover:border-prinx-200 hover:shadow-[0_8px_24px_rgba(15,23,42,.06)]">
                            <div class="h-[92px] overflow-hidden rounded-[5px] border border-slate-200 bg-[#f7fafc] p-2">
                                <div class="grid h-full grid-cols-[22px_1fr] overflow-hidden rounded-[3px] border border-slate-100 bg-white">
                                    <div class="bg-[#18394e] p-1"><span class="block h-2 w-3 rounded-sm bg-prinx-400"></span><span v-for="n in 5" :key="n" class="mt-2 block h-1 rounded bg-slate-500/50"></span></div>
                                    <div class="p-1.5"><div class="grid grid-cols-3 gap-1"><span v-for="n in 3" :key="n" class="h-4 rounded-sm bg-prinx-50"></span></div><div class="mt-1.5 grid grid-cols-2 gap-1"><span class="h-10 rounded-sm bg-slate-50"><svg viewBox="0 0 50 30" class="h-full w-full"><polyline :points="index % 2 ? '0,23 10,18 20,20 30,9 40,14 50,5' : '0,24 10,21 20,12 30,18 40,8 50,11'" fill="none" stroke="currentColor" class="text-prinx-400" stroke-width="1.4"/></svg></span><span class="grid h-10 grid-cols-4 items-end gap-[2px] rounded-sm bg-slate-50 p-1"><i v-for="n in 4" :key="n" class="block rounded-t bg-prinx-200" :style="{height: `${24 + ((n + index) * 14) % 65}%`}"></i></span></div></div>
                                </div>
                            </div>
                            <h3 class="mt-3 text-[13px] font-semibold leading-[1.35] text-slate-900">{{ solution.title }}</h3>
                            <p class="mt-2 flex-1 text-xs leading-5 text-slate-500">{{ solution.text }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <Link href="/pages/contact" class="inline-flex h-8 items-center justify-center rounded-[4px] border border-prinx-300 bg-white px-2 text-xs font-semibold text-prinx-600 transition hover:bg-prinx-50">View Details</Link>
                                <Link href="/pages/contact" class="inline-flex h-8 items-center justify-center rounded-[4px] bg-prinx-500 px-2 text-xs font-semibold text-white transition hover:bg-prinx-600">{{ index === 7 ? 'Get Quote' : 'Request Demo' }}</Link>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="mt-3 grid overflow-hidden rounded-[7px] border border-slate-200 bg-white sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <div v-for="(item, index) in capabilities" :key="item[0]" class="flex min-h-[72px] items-center gap-3 border-b border-slate-100 px-4 py-3 sm:border-r lg:border-b-0" :class="index === capabilities.length - 1 ? 'border-r-0' : ''">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center text-prinx-500">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.7"><path v-if="item[2] === 'code'" stroke-linecap="round" stroke-linejoin="round" d="m9 7-4 5 4 5M15 7l4 5-4 5M13 4l-2 16"/><path v-else-if="item[2] === 'cloud'" stroke-linecap="round" d="M6 18a4 4 0 0 1-.6-8A6 6 0 0 1 17 8.5 4.5 4.5 0 0 1 1 8.9M12 12v8M9 17l3 3 3-3"/><path v-else-if="item[2] === 'shield'" stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path v-else-if="item[2] === 'headset'" stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/><path v-else stroke-linecap="round" stroke-linejoin="round" d="M5 19h14M7 16V8h10v8M9 8V5h6v3M10 12h4"/></svg>
                        </span>
                        <div><h3 class="text-xs font-semibold text-slate-900">{{ item[0] }}</h3><p class="mt-0.5 text-xs text-slate-500">{{ item[1] }}</p></div>
                    </div>
                </section>

                <section class="mt-4 grid gap-3 xl:grid-cols-[1.45fr_.95fr]">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Why Choose Prinx Software?</h2>
                        <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-5">
                            <article v-for="reason in reasons" :key="reason[0]" class="min-h-[112px] rounded-[8px] border border-slate-200 bg-white p-4">
                                <svg viewBox="0 0 24 24" class="h-8 w-8 fill-none stroke-prinx-500" stroke-width="1.7"><path v-if="reason[2] === 'chart'" stroke-linecap="round" d="M5 19V9M10 19V5M15 19v-7M20 19V3"/><path v-else-if="reason[2] === 'shield'" stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path v-else-if="reason[2] === 'headset'" stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/><path v-else-if="reason[2] === 'people'" stroke-linecap="round" d="M8.5 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20c.5-4 2.3-6 5.5-6s5 2 5.5 6M17 5.5a3 3 0 0 1 0 5.5M16.5 14c2.6.3 4 2 4.2 5"/><path v-else stroke-linecap="round" stroke-linejoin="round" d="M8 3v4H4v4h4v4h4v4h4v-4h4v-4h-4V7h-4V3H8Z"/></svg>
                                <h3 class="mt-2 text-xs font-semibold text-slate-900">{{ reason[0] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ reason[1] }}</p>
                            </article>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-[8px] border border-slate-200 bg-gradient-to-r from-white to-prinx-50 p-5">
                        <div class="relative z-10 max-w-[65%]">
                            <h2 class="text-base font-semibold text-slate-950">Let's Build the Right Solution for You</h2>
                            <p class="mt-2 text-xs leading-5 text-slate-600">Tell us about your business needs and our team will recommend the best solution for you.</p>
                            <div class="mt-4 flex flex-wrap gap-2"><Link href="/pages/contact" class="inline-flex h-9 items-center justify-center rounded-[5px] bg-prinx-500 px-4 text-xs font-semibold text-white">Request a Demo</Link><Link href="/pages/contact" class="inline-flex h-9 items-center justify-center rounded-[5px] border border-prinx-300 bg-white px-4 text-xs font-semibold text-slate-800">Schedule a Consultation</Link></div>
                        </div>
                        <div class="absolute -bottom-5 -right-5 flex h-40 w-40 items-center justify-center rounded-full bg-prinx-100/70"><svg viewBox="0 0 120 120" class="h-28 w-28 fill-none stroke-prinx-400" stroke-width="3"><circle cx="60" cy="37" r="18"/><path d="M24 102c4-27 16-41 36-41s32 14 36 41M87 70l22 14M88 84l20 5"/></svg></div>
                    </div>
                </section>

                <section class="mt-4">
                    <h2 class="text-base font-semibold text-slate-950">Our Implementation & Support Services</h2>
                    <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <article v-for="item in implementation" :key="item[0]" class="flex min-h-[102px] gap-3 rounded-[8px] border border-slate-200 bg-white p-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center text-prinx-500"><svg viewBox="0 0 24 24" class="h-8 w-8 fill-none stroke-current" stroke-width="1.7"><path v-if="item[2] === 'screen'" stroke-linecap="round" stroke-linejoin="round" d="M3 5h18v12H3zM8 21h8M12 17v4"/><path v-else-if="item[2] === 'database'" d="M5 6c0-1.7 3.1-3 7-3s7 1.3 7 3-3.1 3-7 3-7-1.3-7-3Zm0 0v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/><path v-else-if="item[2] === 'headset'" stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/><path v-else stroke-linecap="round" d="M4 7h16M7 12h10M9 17h6"/></svg></span>
                            <div><h3 class="text-xs font-semibold text-slate-900">{{ item[0] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ item[1] }}</p><Link href="/pages/contact" class="mt-2 inline-flex text-xs font-semibold text-prinx-600">Learn More →</Link></div>
                        </article>
                        <article class="flex min-h-[102px] flex-col justify-center rounded-[8px] border border-slate-200 bg-white p-4"><h3 class="text-xs font-semibold text-slate-900">Need help choosing the right solution?</h3><p class="mt-1 text-xs leading-5 text-slate-500">Our experts are ready to help you.</p><Link href="/pages/contact" class="mt-3 inline-flex h-8 w-fit items-center justify-center rounded-[5px] border border-slate-300 px-4 text-xs font-semibold text-slate-800">Contact Us</Link></article>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
