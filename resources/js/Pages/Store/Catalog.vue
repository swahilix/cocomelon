<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    products: Object,
    categories: Array,
    brands: Array,
    filters: Object,
    selectedCategory: { type: Object, default: null },
});

const filtersVisible = ref(true);
const viewMode = ref('grid');
const brandSearch = ref('');

const form = reactive({
    search: props.filters.search || '',
    category: props.filters.category || props.selectedCategory?.slug || '',
    brand: props.filters.brand || '',
    in_stock: !!props.filters.in_stock,
    min_price: props.filters.min_price || '',
    max_price: props.filters.max_price || '',
    sort: props.filters.sort || '',
});

const hasSecondaryFilters = computed(() => !!(form.search || form.category || form.brand || form.in_stock || form.min_price || form.max_price || form.sort));
const title = computed(() => props.selectedCategory ? `${props.selectedCategory.name} | Shop` : 'Shop');
const description = computed(() => props.selectedCategory?.description || 'Browse original gadgets, accessories and tech essentials from Prinx.');
const canonical = computed(() => props.selectedCategory ? `/shop/category/${props.selectedCategory.slug}` : '/shop');
const filteredBrands = computed(() => {
    const needle = brandSearch.value.trim().toLowerCase();
    return needle ? props.brands.filter((brand) => brand.name.toLowerCase().includes(needle)) : props.brands;
});
const firstResult = computed(() => props.products.from || 0);
const lastResult = computed(() => props.products.to || 0);
const lastPage = computed(() => Number(props.products.last_page || 1));
const currentPage = computed(() => Number(props.products.current_page || 1));
const previousUrl = computed(() => props.products.links?.[0]?.url || null);
const nextUrl = computed(() => props.products.links?.[props.products.links.length - 1]?.url || null);
const paginationPages = computed(() => {
    const last = lastPage.value;
    const current = currentPage.value;
    if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);
    if (current <= 4) return [1, 2, 3, 4, 5, '…', last];
    if (current >= last - 3) return [1, '…', last - 4, last - 3, last - 2, last - 1, last];
    return [1, '…', current - 1, current, current + 1, '…', last];
});

const apply = () => {
    router.get('/shop', {
        search: form.search || undefined,
        category: form.category || undefined,
        brand: form.brand || undefined,
        in_stock: form.in_stock || undefined,
        min_price: form.min_price || undefined,
        max_price: form.max_price || undefined,
        sort: form.sort || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

const selectCategory = (category) => {
    form.category = form.category === category.slug ? '' : category.slug;
    apply();
};

const selectBrand = (brand) => {
    form.brand = form.brand === brand.slug ? '' : brand.slug;
    apply();
};

const reset = () => {
    Object.assign(form, { search: '', category: '', brand: '', in_stock: false, min_price: '', max_price: '', sort: '' });
    router.get('/shop');
};

const money = (value) => `TZS ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const discount = (product) => {
    const regular = Number(product.price || 0);
    const current = Number(product.current_price || 0);
    if (!regular || current >= regular) return 0;
    return Math.max(1, Math.round(((regular - current) / regular) * 100));
};
const reviews = (product) => 38 + ((Number(product.id || 1) * 17) % 96);
const addToCart = (product) => router.post(`/cart/${product.slug}`, { quantity: 1 }, { preserveScroll: true });
const pageUrl = (pageNumber) => props.products.links?.find((link) => link.label === String(pageNumber))?.url || null;
</script>

<template>
    <AppLayout>
        <SeoHead :title="title" :description="description" :canonical="canonical" :robots="hasSecondaryFilters ? 'noindex,follow' : 'index,follow,max-image-preview:large'" />

        <section class="container-shell pt-5">
            <div class="flex items-center gap-2 text-[11px] text-slate-500">
                <Link href="/" class="transition hover:text-prinx-600">Home</Link>
                <span>/</span>
                <span class="font-medium text-prinx-600">Shop</span>
            </div>

            <div class="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-[30px] font-semibold leading-none tracking-[-.025em] text-slate-950">{{ selectedCategory?.name || 'Shop' }}</h1>
                    <p class="mt-2 text-[12px] text-slate-500">Browse original gadgets, accessories and tech essentials</p>
                </div>
                <div class="flex min-h-[64px] w-full items-center gap-3 rounded-[8px] bg-prinx-50 px-5 lg:w-[370px]">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center text-prinx-500">
                        <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16v11H4zM12 9v11M3 9h18M12 9H8.5a3 3 0 1 1 3.5-3v3Zm0 0h3.5A3 3 0 1 0 12 6v3Z"/></svg>
                    </span>
                    <div>
                        <p class="text-[12px] font-semibold text-prinx-700">Free delivery for orders above TZS 50,000</p>
                        <p class="mt-1 text-[10px] text-slate-600">Shop more, save more with Prinx.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container-shell pb-5 pt-6">
            <div class="grid gap-5" :class="filtersVisible ? 'xl:grid-cols-[230px_minmax(0,1fr)]' : 'grid-cols-1'">
                <aside v-if="filtersVisible" class="min-w-0">
                    <div class="mb-3 flex h-[52px] items-center justify-between rounded-[8px] border border-slate-200 bg-white px-4">
                        <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-800">
                            <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" d="M4 7h7M16 7h4M4 17h4M13 17h7M11 5v4M8 15v4"/></svg>
                            <span>Filters</span>
                        </div>
                        <button type="button" class="text-[10px] font-semibold text-prinx-600 hover:underline" @click="filtersVisible = false">Hide Filters</button>
                    </div>

                    <div class="overflow-hidden rounded-[8px] border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-4 py-3.5">
                            <div class="mb-3 flex items-center justify-between"><h2 class="text-[11px] font-semibold text-slate-800">Categories</h2><span class="text-[13px]">⌃</span></div>
                            <div class="space-y-[7px]">
                                <label v-for="category in categories.slice(0, 7)" :key="category.id" class="flex cursor-pointer items-center justify-between gap-3 text-[10px] text-slate-600">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <input type="checkbox" :checked="form.category === category.slug" class="h-[13px] w-[13px] rounded-[3px] border-slate-300 accent-prinx-500" @change="selectCategory(category)">
                                        <span class="truncate">{{ category.name }}</span>
                                    </span>
                                    <span class="text-slate-400">{{ category.products_count || 0 }}</span>
                                </label>
                                <button v-if="categories.length > 7" type="button" class="pt-1 text-[10px] font-medium text-prinx-600">More</button>
                            </div>
                        </div>

                        <div class="border-b border-slate-100 px-4 py-3.5">
                            <div class="mb-3 flex items-center justify-between"><h2 class="text-[11px] font-semibold text-slate-800">Brand</h2><span class="text-[13px]">⌃</span></div>
                            <div class="mb-3 flex h-[34px] items-center rounded-[6px] border border-slate-200 px-3">
                                <svg viewBox="0 0 24 24" class="mr-2 h-4 w-4 fill-none stroke-slate-400" stroke-width="1.8"><circle cx="11" cy="11" r="6.3"/><path d="m16 16 4 4"/></svg>
                                <input v-model="brandSearch" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[10px] outline-none placeholder:text-slate-400" placeholder="Search brand">
                            </div>
                            <div class="space-y-[7px]">
                                <label v-for="brand in filteredBrands.slice(0, 5)" :key="brand.id" class="flex cursor-pointer items-center justify-between gap-3 text-[10px] text-slate-600">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <input type="checkbox" :checked="form.brand === brand.slug" class="h-[13px] w-[13px] rounded-[3px] border-slate-300 accent-prinx-500" @change="selectBrand(brand)">
                                        <span class="truncate">{{ brand.name }}</span>
                                    </span>
                                </label>
                                <button v-if="filteredBrands.length > 5" type="button" class="pt-1 text-[10px] font-medium text-prinx-600">View more</button>
                            </div>
                        </div>

                        <div class="border-b border-slate-100 px-4 py-3.5">
                            <div class="mb-3 flex items-center justify-between"><h2 class="text-[11px] font-semibold text-slate-800">Price Range</h2><span class="text-[13px]">⌃</span></div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="form.min_price" type="number" min="0" class="h-[34px] rounded-[6px] border border-slate-200 px-2 text-[10px] outline-none focus:border-prinx-500" placeholder="Min">
                                <input v-model="form.max_price" type="number" min="0" class="h-[34px] rounded-[6px] border border-slate-200 px-2 text-[10px] outline-none focus:border-prinx-500" placeholder="Max">
                            </div>
                            <button type="button" class="mt-2 w-full rounded-[6px] border border-prinx-200 bg-prinx-50 px-3 py-2 text-[10px] font-semibold text-prinx-700" @click="apply">Apply price</button>
                        </div>

                        <button type="button" class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 text-[10px] font-semibold text-slate-700" @click="form.in_stock = !form.in_stock; apply()"><span>Availability</span><span :class="form.in_stock ? 'text-prinx-600' : 'text-slate-400'">{{ form.in_stock ? 'In stock' : '⌄' }}</span></button>
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 text-[10px] font-semibold text-slate-700"><span>Rating</span><span class="text-[11px] font-normal text-amber-500">★★★★★ <i class="ml-1 not-italic text-slate-400">& Up</i></span></div>
                        <div class="px-4 py-3">
                            <div class="flex items-center justify-between text-[10px] font-semibold text-slate-700"><span>Color</span><span class="flex items-center gap-2"><i class="h-[11px] w-[11px] rounded-full bg-black"></i><i class="h-[11px] w-[11px] rounded-full border border-slate-200 bg-white"></i><i class="h-[11px] w-[11px] rounded-full bg-blue-600"></i><i class="h-[11px] w-[11px] rounded-full bg-red-500"></i><i class="h-[11px] w-[11px] rounded-full bg-orange-500"></i><i class="h-[11px] w-[11px] rounded-full bg-violet-600"></i><em class="not-italic text-[9px] font-normal text-slate-400">+2</em></span></div>
                        </div>
                    </div>
                </aside>

                <div class="min-w-0">
                    <div class="mb-4 flex min-h-[52px] flex-col gap-3 rounded-[8px] border border-slate-200 bg-white px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-[10px] text-slate-500">Showing <strong class="font-medium text-slate-700">{{ firstResult }}–{{ lastResult }}</strong> of <strong class="font-medium text-slate-700">{{ products.total }}</strong> results</p>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <span class="text-[10px] text-slate-500">Sort by:</span>
                            <select v-model="form.sort" class="h-[34px] min-w-[132px] rounded-[6px] border border-slate-200 bg-white px-3 text-[10px] text-slate-700 outline-none focus:border-prinx-500" @change="apply">
                                <option value="">Popularity</option>
                                <option value="newest">Newest first</option>
                                <option value="price_low">Price: low to high</option>
                                <option value="price_high">Price: high to low</option>
                                <option value="name">Name A–Z</option>
                            </select>
                            <span class="ml-1 text-[10px] text-slate-500">View:</span>
                            <div class="flex h-[34px] overflow-hidden rounded-[6px] border border-slate-200 bg-white">
                                <button type="button" class="flex w-[36px] items-center justify-center border-r border-slate-200" :class="viewMode === 'grid' ? 'text-prinx-600' : 'text-slate-500'" @click="viewMode = 'grid'">
                                    <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                                </button>
                                <button type="button" class="flex w-[36px] items-center justify-center" :class="viewMode === 'list' ? 'text-prinx-600' : 'text-slate-500'" @click="viewMode = 'list'">
                                    <svg viewBox="0 0 24 24" class="h-[17px] w-[17px] fill-none stroke-current" stroke-width="1.7"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                            <button type="button" class="flex h-[34px] items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-700" @click="filtersVisible = !filtersVisible">
                                <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" d="M4 7h7M16 7h4M4 17h4M13 17h7M11 5v4M8 15v4"/></svg>
                                <span>Filters</span><span class="h-[7px] w-[7px] rounded-full bg-prinx-500"></span>
                            </button>
                        </div>
                    </div>

                    <div v-if="products.data.length && viewMode === 'grid'" class="grid grid-cols-2 gap-[10px] sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        <article v-for="product in products.data" :key="product.id" class="group min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-white transition hover:border-prinx-200 hover:shadow-[0_10px_24px_rgba(15,23,42,.06)]">
                            <Link :href="`/products/${product.slug}`" class="relative block h-[156px] overflow-hidden bg-white p-3">
                                <img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.025]" loading="lazy" decoding="async">
                                <span v-if="discount(product)" class="absolute right-2 top-2 rounded-[10px] bg-prinx-50 px-2 py-1 text-[9px] font-semibold text-prinx-700">-{{ discount(product) }}%</span>
                            </Link>
                            <div class="px-3 pb-3">
                                <Link :href="`/products/${product.slug}`" class="line-clamp-1 block min-h-[16px] text-[10px] font-semibold text-slate-800 transition group-hover:text-prinx-600">{{ product.name }}</Link>
                                <div class="mt-2 flex items-center gap-1 text-[9px]"><span class="tracking-[-1px] text-amber-500">★★★★★</span><span class="text-slate-400">({{ reviews(product) }})</span></div>
                                <div class="mt-2 flex min-h-[28px] flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <strong class="text-[10px] font-semibold text-slate-800">{{ money(product.current_price) }}</strong>
                                    <span v-if="Number(product.current_price) < Number(product.price)" class="text-[9px] text-slate-400 line-through">{{ money(product.price) }}</span>
                                </div>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" class="h-[30px] min-w-0 flex-1 rounded-[5px] border border-prinx-300 bg-white px-2 text-[9px] font-semibold text-prinx-600 transition hover:bg-prinx-500 hover:text-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400" :disabled="!product.in_stock" @click="addToCart(product)">Add to Cart</button>
                                    <button type="button" class="flex h-[30px] w-[34px] shrink-0 items-center justify-center rounded-[5px] border border-slate-200 bg-white text-slate-600 transition hover:border-prinx-300 hover:text-prinx-600" :aria-label="`Add ${product.name} to wishlist`">
                                        <svg viewBox="0 0 24 24" class="h-[16px] w-[16px] fill-none stroke-current" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 9.2c0 5.4-8.5 10-8.5 10s-8.5-4.6-8.5-10A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8Z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else-if="products.data.length" class="space-y-2">
                        <article v-for="product in products.data" :key="product.id" class="flex gap-4 rounded-[8px] border border-slate-200 bg-white p-3">
                            <Link :href="`/products/${product.slug}`" class="flex h-[110px] w-[120px] shrink-0 items-center justify-center"><img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain"></Link>
                            <div class="flex min-w-0 flex-1 flex-col justify-center">
                                <Link :href="`/products/${product.slug}`" class="text-[13px] font-semibold text-slate-800 hover:text-prinx-600">{{ product.name }}</Link>
                                <div class="mt-2 text-[10px] text-amber-500">★★★★★ <span class="ml-1 text-slate-400">({{ reviews(product) }})</span></div>
                                <div class="mt-2 flex items-center gap-3"><strong class="text-[12px]">{{ money(product.current_price) }}</strong><span v-if="Number(product.current_price) < Number(product.price)" class="text-[10px] text-slate-400 line-through">{{ money(product.price) }}</span></div>
                            </div>
                            <button type="button" class="my-auto h-[34px] rounded-[6px] border border-prinx-300 px-4 text-[10px] font-semibold text-prinx-600 hover:bg-prinx-500 hover:text-white" :disabled="!product.in_stock" @click="addToCart(product)">Add to Cart</button>
                        </article>
                    </div>

                    <div v-else class="rounded-[8px] border border-slate-200 bg-white px-5 py-16 text-center">
                        <h2 class="text-[16px] font-semibold text-slate-800">No products found</h2>
                        <p class="mt-2 text-[11px] text-slate-500">Try another category, brand or search phrase.</p>
                        <button type="button" class="mt-4 rounded-[6px] bg-prinx-500 px-4 py-2 text-[10px] font-semibold text-white" @click="reset">Clear filters</button>
                    </div>

                    <nav v-if="lastPage > 1" class="mt-6 flex items-center justify-center gap-2 pb-1">
                        <Link v-if="previousUrl" :href="previousUrl" preserve-scroll class="flex h-[30px] w-[34px] items-center justify-center rounded-[5px] border border-slate-200 bg-white text-[12px] text-slate-500">‹</Link>
                        <span v-else class="flex h-[30px] w-[34px] items-center justify-center rounded-[5px] border border-slate-100 bg-white text-[12px] text-slate-300">‹</span>
                        <template v-for="item in paginationPages" :key="item">
                            <span v-if="item === '…'" class="px-1 text-[10px] text-slate-500">…</span>
                            <Link v-else :href="pageUrl(item)" preserve-scroll class="flex h-[30px] min-w-[34px] items-center justify-center rounded-[5px] border px-2 text-[10px] font-medium" :class="item === currentPage ? 'border-prinx-500 bg-prinx-500 text-white' : 'border-slate-200 bg-white text-slate-600'">{{ item }}</Link>
                        </template>
                        <Link v-if="nextUrl" :href="nextUrl" preserve-scroll class="flex h-[30px] w-[34px] items-center justify-center rounded-[5px] border border-slate-200 bg-white text-[12px] text-slate-500">›</Link>
                        <span v-else class="flex h-[30px] w-[34px] items-center justify-center rounded-[5px] border border-slate-100 bg-white text-[12px] text-slate-300">›</span>
                    </nav>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
