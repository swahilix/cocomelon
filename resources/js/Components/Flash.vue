<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const page = usePage();
const visible = ref(false);
const copied = ref(false);
let dismissTimer = null;
let copiedTimer = null;

const success = computed(() => page.props.flash?.success || '');
const error = computed(() => page.props.flash?.error || '');
const message = computed(() => success.value || error.value || '');
const type = computed(() => success.value ? 'success' : 'error');

const dismiss = () => {
    visible.value = false;
    if (dismissTimer) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }
};

const startDismissTimer = () => {
    if (dismissTimer) clearTimeout(dismissTimer);
    dismissTimer = setTimeout(() => {
        visible.value = false;
        dismissTimer = null;
    }, 30000);
};

const copyMessage = async () => {
    if (!message.value) return;

    try {
        await navigator.clipboard.writeText(message.value);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = message.value;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    }

    copied.value = true;
    if (copiedTimer) clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
        copied.value = false;
        copiedTimer = null;
    }, 1800);
};

watch(message, (value) => {
    if (!value) {
        dismiss();
        return;
    }

    visible.value = true;
    copied.value = false;
    startDismissTimer();
}, { immediate: true });

onBeforeUnmount(() => {
    if (dismissTimer) clearTimeout(dismissTimer);
    if (copiedTimer) clearTimeout(copiedTimer);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0 sm:translate-x-3 sm:translate-y-0"
        enter-to-class="translate-x-0 translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0 sm:translate-x-3 sm:translate-y-0"
    >
        <div
            v-if="visible && message"
            class="fixed right-3 top-3 z-[100] w-[calc(100%-1.5rem)] max-w-md sm:right-5 sm:top-5 sm:w-full"
            role="alert"
            aria-live="polite"
        >
            <div
                :class="[
                    'flex items-start gap-3 rounded-[10px] border bg-white p-3.5 shadow-[0_16px_40px_rgba(15,23,42,0.16)]',
                    type === 'success' ? 'border-emerald-200' : 'border-red-200',
                ]"
            >
                <div
                    :class="[
                        'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px]',
                        type === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600',
                    ]"
                >
                    <Icon :name="type === 'success' ? 'tick-circle' : 'danger'" :size="18" />
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        :class="[
                            'text-xs font-semibold uppercase tracking-[0.08em]',
                            type === 'success' ? 'text-emerald-700' : 'text-red-700',
                        ]"
                    >
                        {{ type === 'success' ? 'Success' : 'Error' }}
                    </p>
                    <p class="mt-1 select-text break-words text-sm font-normal leading-5 text-slate-700">
                        {{ message }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 min-w-8 items-center justify-center gap-1 rounded-[7px] px-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        :title="copied ? 'Copied' : 'Copy message'"
                        @click="copyMessage"
                    >
                        <Icon :name="copied ? 'tick-circle' : 'copy'" :size="16" />
                        <span v-if="copied" class="text-[11px] font-medium text-emerald-600">Copied</span>
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-[7px] text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        title="Dismiss"
                        aria-label="Dismiss notification"
                        @click="dismiss"
                    >
                        <Icon name="close-circle" :size="17" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
