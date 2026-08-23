<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Flash from '../Components/Flash.vue';

const page = usePage();
const mobileOpen = ref(false);
const categoryOpen = ref(false);
const search = ref('');

const user = computed(() => page.props.auth?.user);
const cartCount = computed(() => page.props.cartCount || 0);
const categories = computed(() => page.props.navigationCategories || []);
const store = computed(() => page.props.store || {});
const phone = computed(() => store.value.phone || '+255 710 123 456');
const email = computed(() => store.value.email || 'info@prinx.co.tz');
const address = computed(() => store.value.address || 'Dodoma, Tanzania');

const submitSearch = () => {
    const query = search.value.trim();
    router.get('/shop', query ? { search: query } : {});
};

const logout = () => router.post('/logout');
const active = (url) => page.url === url || (url !== '/' && page.url.startsWith(url));
</script>

<template>
    <div class="min-h-screen bg-white text-[#111827]">
        <Flash />

        <header class="relative z-50 border-b border-[#e7eaee] bg-white">
            <div class="hidden border-b border-[#eceff2] lg:block">
                <div class="mx-auto grid h-[86px] w-full max-w-[1450px] grid-cols-[170px_180px_minmax(360px,1fr)_190px_205px] items-center gap-5 px-5">
                    <Link href="/" class="flex items-center">
                        <img src="/assets/prinx-logo.png" :alt="store.name || 'Prinx'" class="h-[48px] w-auto max-w-[160px] object-contain">
                    </Link>

                    <div class="relative">
                        <button type="button" class="flex h-[48px] w-full items-center gap-3 rounded-[8px] bg-[#009b35] px-4 text-[13px] font-semibold text-white shadow-[0_3px_8px_rgba(0,155,53,.18)] transition hover:bg-[#008a30]" @click="categoryOpen = !categoryOpen">
                            <svg viewBox="0 0 24 24" class="h-[19px] w-[19px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                            <span class="min-w-0 flex-1 text-left">All Categories</span>
                            <svg viewBox="0 0 20 20" class="h-4 w-4 fill-current transition" :class="categoryOpen ? 'rotate-180' : ''" aria-hidden="true"><path d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4Z"/></svg>
                        </button>
                        <div v-if="categoryOpen" class="absolute left-0 top-[55px] z-50 w-[285px] overflow-hidden rounded-[8px] border border-[#e4e7eb] bg-white py-1 shadow-[0_16px_40px_rgba(15,23,42,.14)]">
                            <Link href="/shop" class="block px-4 py-3 text-[12px] font-semibold text-[#232a32] hover:bg-[#f2fbf5] hover:text-[#008f31]" @click="categoryOpen = false">All products</Link>
                            <Link v-for="category in categories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="block border-t border-[#f0f2f4] px-4 py-3 text-[12px] text-[#4e5965] hover:bg-[#f2fbf5] hover:text-[#008f31]" @click="categoryOpen = false">{{ category.name }}</Link>
                        </div>
                    </div>

                    <form class="flex h-[48px] min-w-0 items-center overflow-hidden rounded-[8px] border border-[#dfe3e8] bg-[#fafbfc]" @submit.prevent="submitSearch">
                        <input v-model="search" class="min-w-0 flex-1 border-0 bg-transparent px-4 text-[12px] text-[#303842] outline-none placeholder:text-[#858e99]" placeholder="Search for products, brands and more...">
                        <div class="flex h-full w-[82px] shrink-0 items-center justify-center gap-2 border-l border-[#e0e4e8] bg-white text-[12px] text-[#2b3138]">
                            <span>All</span>
                            <svg viewBox="0 0 20 20" class="h-3.5 w-3.5 fill-current text-[#6a737d]" aria-hidden="true"><path d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4Z"/></svg>
                        </div>
                        <button type="submit" class="flex h-full w-[52px] shrink-0 items-center justify-center bg-[#009b35] text-white transition hover:bg-[#008a30]" aria-label="Search">
                            <svg viewBox="0 0 24 24" class="h-[21px] w-[21px] fill-none stroke-current" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6.6"/><path stroke-linecap="round" d="m16 16 4.2 4.2"/></svg>
                        </button>
                    </form>

                    <a :href="`tel:${phone}`" class="flex items-center gap-3 text-[#111827]">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15v-3a7 7 0 0 1 14 0v3M5 14H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2v-6Zm13 0h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v-6Z"/></svg>
                        </span>
                        <span class="min-w-0 leading-tight"><span class="block text-[10px] text-[#222831]">Support</span><strong class="mt-1 block whitespace-nowrap text-[11px] font-medium">{{ phone }}</strong></span>
                    </a>

                    <div class="grid grid-cols-3 divide-x divide-[#eceff2]">
                        <Link v-if="!user" href="/login" class="flex flex-col items-center justify-center gap-1 text-[#111827] hover:text-[#008f31]">
                            <svg viewBox="0 0 24 24" class="h-[24px] w-[24px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M5 20c.7-4.2 3-6.3 7-6.3s6.3 2.1 7 6.3"/></svg>
                            <span class="text-[9px]">Account</span>
                        </Link>
                        <button v-else type="button" class="flex flex-col items-center justify-center gap-1 text-[#111827] hover:text-[#008f31]" @click="logout">
                            <svg viewBox="0 0 24 24" class="h-[24px] w-[24px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M5 20c.7-4.2 3-6.3 7-6.3s6.3 2.1 7 6.3"/></svg>
                            <span class="text-[9px]">Account</span>
                        </button>
                        <span class="relative flex flex-col items-center justify-center gap-1 text-[#111827]">
                            <span class="relative">
                                <svg viewBox="0 0 24 24" class="h-[24px] w-[24px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                                <b class="absolute -right-2 -top-2 flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-[#009b35] px-1 text-[8px] font-semibold text-white">0</b>
                            </span>
                            <span class="text-[9px]">Wishlist</span>
                        </span>
                        <Link href="/cart" class="relative flex flex-col items-center justify-center gap-1 text-[#111827] hover:text-[#008f31]">
                            <span class="relative">
                                <svg viewBox="0 0 24 24" class="h-[25px] w-[25px] fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2 10h10l2-7H7M9 19.2a1.2 1.2 0 1 0 0 .1Zm7 0a1.2 1.2 0 1 0 0 .1Z"/></svg>
                                <b class="absolute -right-2 -top-2 flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-[#009b35] px-1 text-[8px] font-semibold text-white">{{ cartCount }}</b>
                            </span>
                            <span class="text-[9px]">Cart</span>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block">
                <div class="mx-auto flex h-[56px] w-full max-w-[1450px] items-center justify-between px-5">
                    <nav class="flex h-full items-center gap-6 text-[12px] font-medium text-[#252b31]">
                        <Link href="/" class="flex h-full items-center gap-2 border-b-2 px-0.5" :class="active('/') ? 'border-[#009b35] text-[#009b35]' : 'border-transparent'">
                            <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m4 10 8-7 8 7v10h-6v-6h-4v6H4V10Z"/></svg>Home
                        </Link>
                        <Link href="/shop" class="flex h-full items-center gap-2 border-b-2 px-0.5" :class="active('/shop') || active('/products') ? 'border-[#009b35] text-[#009b35]' : 'border-transparent'">
                            <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14l-1 12H6L5 8Zm3 0a4 4 0 0 1 8 0"/></svg>Shop
                        </Link>
                        <Link href="/shop" class="flex h-full items-center gap-2 border-b-2 border-transparent px-0.5 hover:text-[#009b35]">
                            <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 7v5c0 4.3 2.5 7.3 7 9 4.5-1.7 7-4.7 7-9V7l-7-4Z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg>Brands
                        </Link>
                        <Link href="/shop" class="flex h-full items-center gap-2 border-b-2 border-transparent px-0.5 hover:text-[#009b35]">
                            <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m4 12 8-8 8 8-8 8-8-8Z"/><path stroke-linecap="round" d="M9 12h6"/></svg>Offers
                        </Link>
                        <Link href="/track-order" class="flex h-full items-center gap-2 border-b-2 border-transparent px-0.5 hover:text-[#009b35]">
                            <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4h10v16H7zM9 8h6M9 12h6"/></svg>Track Order
                        </Link>
                        <Link href="/pages/contact" class="flex h-full items-center gap-2 border-b-2 border-transparent px-0.5 hover:text-[#009b35]">
                            <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 7l8 6 8-6"/></svg>Contact Us
                        </Link>
                    </nav>
                    <div class="flex h-[36px] items-center gap-2 rounded-[7px] border border-[#e5e8eb] bg-[#fbfcfc] px-3 text-[11px] text-[#252b31]">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        <span>Fast Delivery in <strong class="font-semibold text-[#009b35]">20 – 40 mins</strong></span>
                    </div>
                </div>
            </div>

            <div class="flex h-[68px] items-center justify-between px-4 lg:hidden">
                <Link href="/"><img src="/assets/prinx-logo.png" :alt="store.name || 'Prinx'" class="h-9 w-auto"></Link>
                <div class="flex items-center gap-2">
                    <Link href="/cart" class="relative flex h-10 w-10 items-center justify-center rounded-[8px] border border-[#e4e7eb]">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2 10h10l2-7H7M9 19.2a1.2 1.2 0 1 0 0 .1Zm7 0a1.2 1.2 0 1 0 0 .1Z"/></svg>
                        <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#009b35] px-1 text-[8px] text-white">{{ cartCount }}</span>
                    </Link>
                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-[8px] bg-[#009b35] text-white" @click="mobileOpen = !mobileOpen">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                </div>
            </div>
            <div v-if="mobileOpen" class="border-t border-[#eceff2] bg-white px-4 py-4 lg:hidden">
                <form class="mb-3 flex h-11 overflow-hidden rounded-[8px] border border-[#dfe3e8]" @submit.prevent="submitSearch">
                    <input v-model="search" class="min-w-0 flex-1 border-0 px-3 text-[13px] outline-none" placeholder="Search products...">
                    <button class="w-11 bg-[#009b35] text-white" type="submit"><svg viewBox="0 0 24 24" class="mx-auto h-5 w-5 fill-none stroke-current" stroke-width="2"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg></button>
                </form>
                <nav class="grid grid-cols-2 gap-2 text-[13px] font-medium">
                    <Link href="/" class="rounded-[7px] border border-[#e8ebee] px-3 py-2.5">Home</Link>
                    <Link href="/shop" class="rounded-[7px] border border-[#bfe7cc] bg-[#f2fbf5] px-3 py-2.5 text-[#008f31]">Shop</Link>
                    <Link href="/track-order" class="rounded-[7px] border border-[#e8ebee] px-3 py-2.5">Track Order</Link>
                    <Link href="/pages/contact" class="rounded-[7px] border border-[#e8ebee] px-3 py-2.5">Contact Us</Link>
                </nav>
            </div>
        </header>

        <main><slot /></main>

        <footer class="mt-8 border-t border-[#e5e8eb] bg-[#fafbfc]">
            <div class="mx-auto w-full max-w-[1450px] px-5 py-7">
                <div class="grid gap-8 border-b border-[#e5e8eb] pb-7 md:grid-cols-2 lg:grid-cols-[1.35fr_.8fr_.95fr_.9fr_1.1fr]">
                    <div>
                        <img src="/assets/prinx-logo.png" :alt="store.name || 'Prinx'" class="h-[42px] w-auto">
                        <p class="mt-3 max-w-[250px] text-[10px] leading-5 text-[#6d7680]">Your one-stop destination for original tech products and accessories.</p>
                        <div class="mt-4 flex gap-3 text-[10px] font-semibold text-[#242a31]"><span>f</span><span>◎</span><span>♪</span><span>𝕏</span><span>▶</span></div>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-semibold text-[#1d2329]">Shop</h3>
                        <div class="mt-3 space-y-2 text-[10px] text-[#656e78]"><Link href="/shop" class="block hover:text-[#009b35]">All Products</Link><Link href="/shop?sort=newest" class="block hover:text-[#009b35]">New Arrivals</Link><Link href="/shop" class="block hover:text-[#009b35]">Best Sellers</Link><Link href="/shop" class="block hover:text-[#009b35]">Deals Of The Day</Link><Link href="/track-order" class="block hover:text-[#009b35]">Track Order</Link></div>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-semibold text-[#1d2329]">Customer Service</h3>
                        <div class="mt-3 space-y-2 text-[10px] text-[#656e78]"><Link href="/pages/contact" class="block hover:text-[#009b35]">Help Center</Link><Link href="/pages/returns" class="block hover:text-[#009b35]">Returns & Refunds</Link><Link href="/pages/shipping" class="block hover:text-[#009b35]">Shipping & Delivery</Link><Link href="/pages/warranty" class="block hover:text-[#009b35]">Warranty</Link><Link href="/pages/contact" class="block hover:text-[#009b35]">Contact Us</Link></div>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-semibold text-[#1d2329]">About Prinx</h3>
                        <div class="mt-3 space-y-2 text-[10px] text-[#656e78]"><Link href="/pages/about" class="block hover:text-[#009b35]">About Us</Link><Link href="/pages/careers" class="block hover:text-[#009b35]">Careers</Link><Link href="/pages/terms" class="block hover:text-[#009b35]">Terms & Conditions</Link><Link href="/pages/privacy" class="block hover:text-[#009b35]">Privacy Policy</Link></div>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-semibold text-[#1d2329]">Contact Us</h3>
                        <div class="mt-3 space-y-3 text-[10px] text-[#343b43]">
                            <a :href="`tel:${phone}`" class="flex items-center gap-2 hover:text-[#009b35]"><span>⌕</span><span>{{ phone }}</span></a>
                            <a :href="`mailto:${email}`" class="flex items-center gap-2 hover:text-[#009b35]"><span>✉</span><span>{{ email }}</span></a>
                            <p class="flex items-center gap-2"><span>⌖</span><span>{{ address }}</span></p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-4 pt-5 text-[9px] text-[#737c86] sm:flex-row sm:items-center sm:justify-between">
                    <p>© 2025 Prinx. All rights reserved.</p>
                    <div class="flex items-center gap-2"><span class="mr-2">We accept:</span><span class="rounded border border-[#e2e5e8] bg-white px-2 py-1 font-bold text-[#234c9f]">VISA</span><span class="rounded border border-[#e2e5e8] bg-white px-2 py-1 font-bold text-[#ef5b2a]">●●</span><span class="rounded border border-[#e2e5e8] bg-white px-2 py-1 font-bold text-[#2870c7]">AMEX</span><span class="rounded border border-[#e2e5e8] bg-white px-2 py-1 font-bold text-[#274c83]">tigopesa</span><span class="rounded border border-[#e2e5e8] bg-white px-2 py-1 font-bold text-[#159447]">m-pesa</span></div>
                </div>
            </div>
        </footer>

        <button type="button" class="fixed bottom-7 right-7 z-40 hidden h-10 w-10 items-center justify-center rounded-full bg-[#009b35] text-white shadow-[0_6px_18px_rgba(0,155,53,.3)] lg:flex" aria-label="Back to top" @click="window.scrollTo({ top: 0, behavior: 'smooth' })">
            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 14 6-6 6 6M12 8v11"/></svg>
        </button>
    </div>
</template>
