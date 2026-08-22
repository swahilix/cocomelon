<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Flash from '../Components/Flash.vue';
import Icon from '../Components/Icon.vue';

const page = usePage();
const open = ref(false);
const search = ref('');
const user = computed(() => page.props.auth?.user);
const cartCount = computed(() => page.props.cartCount || 0);
const productCategories = computed(() => page.props.navigationCategories || []);
const submitSearch = () => router.get('/shop', { search: search.value }, { preserveState: false });
const logout = () => router.post('/logout');
const active = (url) => page.url === url || (url !== '/' && page.url.startsWith(url));
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <Flash />

        <div class="bg-slate-950 text-white">
            <div class="container-shell flex min-h-9 items-center justify-center gap-5 py-2 text-center text-[11px] font-bold sm:justify-between sm:text-xs">
                <span class="inline-flex items-center gap-2"><Icon name="shield-tick" :size="14" class="text-prinx-400" /> Trusted electronics, professional services and responsive support.</span>
                <div class="hidden items-center gap-5 text-slate-300 sm:flex">
                    <a :href="`tel:${page.props.store.phone}`" class="inline-flex items-center gap-1.5 hover:text-white"><Icon name="call" :size="14" />{{ page.props.store.phone }}</a>
                    <a :href="`mailto:${page.props.store.email}`" class="inline-flex items-center gap-1.5 hover:text-white"><Icon name="sms" :size="14" />{{ page.props.store.email }}</a>
                </div>
            </div>
        </div>

        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur-xl">
            <div class="container-shell flex h-[76px] items-center gap-5">
                <Link href="/" class="shrink-0">
                    <img src="/assets/prinx-logo.png" alt="Prinx Electronics" class="h-10 w-auto max-w-[210px] object-contain sm:h-11">
                </Link>

                <nav class="hidden items-center gap-1 lg:flex">
                    <Link href="/" :class="['rounded-[9px] px-3 py-2 text-sm font-extrabold transition', active('/') ? 'bg-prinx-50 text-prinx-600' : 'text-slate-700 hover:bg-slate-50 hover:text-prinx-600']">Home</Link>
                    <Link href="/shop" :class="['rounded-[9px] px-3 py-2 text-sm font-extrabold transition', active('/shop') || active('/products') ? 'bg-prinx-50 text-prinx-600' : 'text-slate-700 hover:bg-slate-50 hover:text-prinx-600']">Shop</Link>
                    <Link href="/services" :class="['rounded-[9px] px-3 py-2 text-sm font-extrabold transition', active('/services') ? 'bg-prinx-50 text-prinx-600' : 'text-slate-700 hover:bg-slate-50 hover:text-prinx-600']">Services</Link>
                    <Link href="/pages/about" class="rounded-[9px] px-3 py-2 text-sm font-extrabold text-slate-700 transition hover:bg-slate-50 hover:text-prinx-600">About</Link>
                    <Link href="/pages/contact" class="rounded-[9px] px-3 py-2 text-sm font-extrabold text-slate-700 transition hover:bg-slate-50 hover:text-prinx-600">Contact</Link>
                </nav>

                <form class="ml-auto hidden max-w-sm flex-1 xl:flex" @submit.prevent="submitSearch">
                    <div class="relative w-full">
                        <Icon name="search-normal" :size="20" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input v-model="search" class="input h-11 pl-10 pr-24" placeholder="Search products...">
                        <button class="absolute right-1.5 top-1/2 inline-flex -translate-y-1/2 items-center gap-1.5 rounded-[8px] bg-slate-950 px-3 py-2 text-xs font-black text-white hover:bg-prinx-500"><Icon name="search-normal" :size="14" />Search</button>
                    </div>
                </form>

                <div class="ml-auto flex items-center gap-2 xl:ml-0">
                    <Link v-if="user?.is_admin" href="/admin" class="btn-light hidden px-3 sm:inline-flex"><Icon name="category" :size="16" />Admin</Link>
                    <Link v-else-if="!user" href="/login" class="btn-light hidden px-3 sm:inline-flex"><Icon name="login" :size="16" />Sign in</Link>
                    <button v-else type="button" class="btn-light hidden px-3 sm:inline-flex" @click="logout"><Icon name="logout" :size="16" />Sign out</button>

                    <Link href="/cart" class="relative inline-flex h-11 w-11 items-center justify-center rounded-[10px] border border-slate-200 bg-white text-slate-800 shadow-sm transition hover:border-prinx-300 hover:text-prinx-600" aria-label="Shopping cart">
                        <Icon name="shopping-cart" :size="21" />
                        <span v-if="cartCount" class="absolute -right-1.5 -top-1.5 min-w-5 rounded-[10px] bg-prinx-500 px-1 text-center text-[10px] font-black leading-5 text-white">{{ cartCount }}</span>
                    </Link>

                    <button class="inline-flex h-11 w-11 items-center justify-center rounded-[10px] border border-slate-200 bg-white lg:hidden" @click="open=!open" aria-label="Menu">
                        <Icon :name="open ? 'close-circle' : 'menu'" :size="24" />
                    </button>
                </div>
            </div>

            <div v-if="open" class="border-t border-slate-200 bg-white lg:hidden">
                <div class="scrollbar-hidden container-shell max-h-[calc(100vh-110px)] overflow-y-auto py-4">
                    <form class="mb-4 flex" @submit.prevent="submitSearch">
                        <div class="relative flex-1"><Icon name="search-normal" :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="input rounded-r-none pl-9" placeholder="Search products"></div>
                        <button class="btn-primary rounded-l-none"><Icon name="search-normal" :size="15" />Go</button>
                    </form>
                    <div class="grid grid-cols-2 gap-2">
                        <Link href="/" class="rounded-[9px] bg-slate-50 px-3 py-3 font-extrabold text-slate-700">Home</Link>
                        <Link href="/shop" class="rounded-[9px] bg-slate-50 px-3 py-3 font-extrabold text-slate-700">Shop all</Link>
                        <Link href="/services" class="rounded-[9px] bg-slate-50 px-3 py-3 font-extrabold text-slate-700">Services</Link>
                        <Link href="/pages/contact" class="rounded-[9px] bg-slate-50 px-3 py-3 font-extrabold text-slate-700">Contact</Link>
                    </div>
                    <p class="mt-5 flex items-center gap-2 px-1 text-[10px] font-black uppercase tracking-[.18em] text-slate-400"><Icon name="category" :size="14" />Product categories</p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <Link v-for="category in productCategories" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="inline-flex items-center gap-2 rounded-[9px] border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700"><Icon name="box" :size="15" />{{ category.name }}</Link>
                    </div>
                    <div class="mt-4 border-t border-slate-200 pt-4">
                        <Link v-if="user?.is_admin" href="/admin" class="flex items-center gap-2 rounded-[9px] px-3 py-2.5 font-bold text-slate-700"><Icon name="category" :size="17" />Admin panel</Link>
                        <Link v-if="!user" href="/login" class="flex items-center gap-2 rounded-[9px] px-3 py-2.5 font-bold text-slate-700"><Icon name="login" :size="17" />Sign in</Link>
                        <button v-else class="flex w-full items-center gap-2 rounded-[9px] px-3 py-2.5 text-left font-bold text-slate-700" @click="logout"><Icon name="logout" :size="17" />Sign out</button>
                    </div>
                </div>
            </div>
        </header>

        <main><slot /></main>

        <footer class="mt-20 border-t border-slate-800 bg-slate-950 text-slate-300">
            <div class="container-shell grid gap-10 py-14 md:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2"><img src="/assets/prinx-logo.png" alt="Prinx Electronics" class="h-11 w-auto brightness-0 invert"><p class="mt-4 max-w-xl text-sm leading-7 text-slate-400">Electronics products, accessories, installation, maintenance and repair services for homes, businesses and institutions.</p><div class="mt-5 flex flex-wrap gap-2 text-xs font-bold"><span class="inline-flex items-center gap-1.5 rounded-[10px] border border-slate-700 px-3 py-1.5"><Icon name="shield-tick" :size="14" />Secure ordering</span><span class="inline-flex items-center gap-1.5 rounded-[10px] border border-slate-700 px-3 py-1.5"><Icon name="headphone" :size="14" />Expert support</span><span class="inline-flex items-center gap-1.5 rounded-[10px] border border-slate-700 px-3 py-1.5"><Icon name="location" :size="14" />Tanzania</span></div></div>
                <div><h3 class="font-black text-white">Shop</h3><div class="mt-4 space-y-2 text-sm"><Link href="/shop" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>All products</Link><Link v-for="category in productCategories.slice(0, 4)" :key="category.id" :href="`/shop?category=${encodeURIComponent(category.slug)}`" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>{{ category.name }}</Link></div></div>
                <div><h3 class="flex items-center gap-2 font-black text-white"><Icon name="headphone" :size="17" />Customer care</h3><div class="mt-4 space-y-2 text-sm"><Link href="/services" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Technical services</Link><Link href="/pages/shipping" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Shipping & delivery</Link><Link href="/pages/returns" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Returns & refunds</Link><Link href="/pages/contact" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Contact us</Link></div></div>
                <div><h3 class="flex items-center gap-2 font-black text-white"><Icon name="building" :size="17" />Company</h3><div class="mt-4 space-y-2 text-sm"><Link href="/pages/about" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>About Prinx</Link><Link href="/pages/privacy" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Privacy policy</Link><Link href="/pages/terms" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Terms & conditions</Link><a href="/sitemap.xml" class="flex items-center gap-2 hover:text-white"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>Sitemap</a></div></div>
            </div>
            <div class="border-t border-slate-800 py-5"><div class="container-shell flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:justify-between"><span>© {{ page.props.store.year }} Prinx Electronics. All rights reserved.</span><span class="inline-flex items-center gap-1.5"><Icon name="location" :size="13" />{{ page.props.store.address }} · {{ page.props.store.phone }}</span></div></div>
        </footer>
    </div>
</template>
