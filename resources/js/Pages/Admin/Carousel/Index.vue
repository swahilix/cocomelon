<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
    view: { type: String, default: 'active' },
    counts: { type: Object, default: () => ({}) },
});

const editingId = ref(null);
const currentImageUrl = ref('');
const localPreviewUrl = ref('');
const trash = computed(() => props.view === 'trash');
const previewImage = computed(() => localPreviewUrl.value || currentImageUrl.value || '');

const form = useForm({
    image: null,
    headline: '',
    image_alt: '',
    link_url: '',
    sort_order: 0,
    is_active: true,
});

function revokePreview() {
    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
        localPreviewUrl.value = '';
    }
}

function resetForm() {
    revokePreview();
    editingId.value = null;
    currentImageUrl.value = '';
    form.reset();
    form.image = null;
    form.sort_order = props.slides.length;
    form.is_active = true;
    form.clearErrors();
}

function onImageSelected(event) {
    revokePreview();
    const file = event.target.files?.[0] || null;
    form.image = file;
    if (file) localPreviewUrl.value = URL.createObjectURL(file);
}

function editSlide(slide) {
    revokePreview();
    editingId.value = slide.id;
    currentImageUrl.value = slide.image_url || '';
    form.image = null;
    form.headline = slide.headline || '';
    form.image_alt = slide.image_alt || '';
    form.link_url = slide.link_url || '';
    form.sort_order = slide.sort_order ?? 0;
    form.is_active = Boolean(slide.is_active);
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function save() {
    form.post(editingId.value ? `/admin/carousel/${editingId.value}` : '/admin/carousel', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: resetForm,
    });
}

function removeSlide(slide) {
    const label = slide.headline || slide.image_alt || 'this banner';
    if (confirm(`Remove “${label}” from the homepage carousel? It will be moved to Trash.`)) {
        router.delete(`/admin/carousel/${slide.id}`, { preserveScroll: true });
    }
}

function restoreSlide(slide) {
    router.post(`/admin/carousel/${slide.id}/restore`, {}, { preserveScroll: true });
}

function switchView(view) {
    router.get('/admin/carousel', { view: view || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <AdminLayout>
        <Head title="Homepage Carousel" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Homepage presentation</p>
                <h1 class="section-title">Image banner carousel</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Upload finished promotional banner images for the top of the homepage. Products are no longer used as carousel slides.</p>
            </div>
            <button class="btn-light self-start" type="button" @click="switchView(trash ? '' : 'trash')">{{ trash ? 'Back to carousel' : `Trash (${counts.trash || 0})` }}</button>
        </div>

        <div v-if="!trash" class="mt-7 grid gap-6 xl:grid-cols-[410px_1fr]">
            <section class="card h-fit overflow-hidden xl:sticky xl:top-24">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-prinx-600">{{ editingId ? 'Editing banner' : 'New banner' }}</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">{{ editingId ? 'Update banner' : 'Add homepage banner' }}</h2>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="save">
                    <div>
                        <label class="label">Banner image {{ editingId ? '' : '*' }}</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="input py-2" @change="onImageSelected">
                        <p class="error">{{ form.errors.image }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-slate-400">Recommended size: 1600 × 500 px or a similar wide ratio. Minimum: 1000 × 280 px.</p>
                    </div>

                    <div v-if="previewImage" class="overflow-hidden rounded-[9px] border border-slate-200 bg-slate-100">
                        <div class="aspect-[16/5] overflow-hidden">
                            <img :src="previewImage" :alt="form.image_alt || form.headline || 'Banner preview'" class="h-full w-full object-cover">
                        </div>
                    </div>

                    <div>
                        <label class="label">Banner name</label>
                        <input v-model="form.headline" class="input" maxlength="191" placeholder="Example: Back to School Offer">
                        <p class="mt-1 text-[11px] text-slate-400">Used in admin only; it is not printed over the banner.</p>
                    </div>

                    <div>
                        <label class="label">Image alt text</label>
                        <input v-model="form.image_alt" class="input" maxlength="191" placeholder="Describe what the banner promotes">
                    </div>

                    <div>
                        <label class="label">Destination link</label>
                        <input v-model="form.link_url" class="input" placeholder="/shop or https://example.com/page">
                        <p class="error">{{ form.errors.link_url }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">Optional. Clicking the banner opens this page.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Display order</label>
                            <input v-model="form.sort_order" type="number" min="0" max="9999" class="input">
                            <p class="error">{{ form.errors.sort_order }}</p>
                        </div>
                        <label class="mt-7 flex items-center gap-2 text-sm font-bold text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-prinx-500"> Published
                        </label>
                    </div>

                    <button class="btn-primary w-full py-3" :disabled="form.processing">{{ form.processing ? 'Saving...' : editingId ? 'Save banner' : 'Add banner' }}</button>
                    <button v-if="editingId" type="button" class="w-full text-sm font-bold text-slate-500 hover:text-prinx-600" @click="resetForm">Cancel editing</button>
                </form>
            </section>

            <section>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Homepage banners</h2>
                        <p class="mt-1 text-xs text-slate-500">Lower display-order numbers appear first.</p>
                    </div>
                    <span class="status bg-prinx-50 text-prinx-700">{{ slides.length }} banners</span>
                </div>

                <div class="space-y-4">
                    <article v-for="(slide, index) in slides" :key="slide.id" class="card overflow-hidden">
                        <div class="grid lg:grid-cols-[360px_1fr]">
                            <div class="relative aspect-[16/5] overflow-hidden border-b border-slate-200 bg-slate-100 lg:aspect-auto lg:min-h-[160px] lg:border-b-0 lg:border-r">
                                <img :src="slide.image_url" :alt="slide.image_alt || slide.headline || 'Carousel banner'" class="h-full w-full object-cover">
                                <span class="absolute left-3 top-3 rounded-[7px] bg-slate-950/85 px-2.5 py-1 text-[9px] font-black uppercase tracking-[.14em] text-white">{{ String(index + 1).padStart(2, '0') }}</span>
                            </div>
                            <div class="p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-black text-slate-950">{{ slide.headline || 'Homepage banner' }}</h3>
                                        <p class="mt-1 text-xs font-bold text-slate-400">Order {{ slide.sort_order }}</p>
                                    </div>
                                    <span :class="['status', slide.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500']">{{ slide.is_active ? 'Published' : 'Hidden' }}</span>
                                </div>
                                <p v-if="slide.image_alt" class="mt-3 text-sm text-slate-600">{{ slide.image_alt }}</p>
                                <p v-if="slide.link_url" class="mt-2 truncate text-xs font-semibold text-prinx-600">{{ slide.link_url }}</p>
                                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                                    <button class="btn-light px-3 py-2" @click="editSlide(slide)">Edit</button>
                                    <button class="btn-danger" @click="removeSlide(slide)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </article>

                    <div v-if="!slides.length" class="card py-20 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[10px] bg-prinx-50 text-prinx-600"><Icon name="gallery" :size="25" /></div>
                        <p class="mt-4 font-black text-slate-800">No image banners yet</p>
                        <p class="mt-1 text-sm text-slate-500">Upload the first homepage banner using the form.</p>
                    </div>
                </div>
            </section>
        </div>

        <section v-else class="mt-7">
            <div class="rounded-[10px] border border-red-200 bg-red-50 p-5">
                <h2 class="font-black text-red-900">Carousel Trash</h2>
                <p class="mt-1 text-sm text-red-700">Deleted banner images remain recoverable.</p>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="slide in slides" :key="slide.id" class="card p-4">
                    <img :src="slide.image_url" :alt="slide.image_alt || slide.headline || 'Deleted banner'" class="aspect-[16/5] w-full rounded-[8px] border border-slate-200 bg-slate-100 object-cover">
                    <p class="mt-3 truncate font-black text-slate-900">{{ slide.headline || 'Homepage banner' }}</p>
                    <p class="mt-1 text-xs text-slate-500">Deleted {{ new Date(slide.deleted_at).toLocaleString() }}</p>
                    <button class="btn-primary mt-4 w-full" @click="restoreSlide(slide)">Restore banner</button>
                </article>
                <div v-if="!slides.length" class="card col-span-full py-16 text-center text-sm font-semibold text-slate-500">Carousel Trash is empty.</div>
            </div>
        </section>
    </AdminLayout>
</template>
