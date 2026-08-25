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
    }, 5000);
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
            class="fixed right-3 top-3 z-[100] w-[calc(100%-1.5rem)] max-w-[520px] sm:right-5 sm:top-5 sm:w-full"
            role="alert"
            aria-live="polite"
        >
            <div
                :class="[
                    'flex min-h-[86px] items-center gap-4 rounded-[10px] border px-4 py-3.5 shadow-[0_10px_30px_rgba(15,23,42,0.08)] sm:px-5',
                    type === 'success'
                        ? 'border-[#ffd9c5] bg-[#fff4ed]'
                        : 'border-red-200 bg-[#fff1f2]',
                ]"
            >
                <div
                    :class="[
                        'flex h-11 w-11 shrink-0 items-center justify-center',
                        type === 'success' ? 'text-[#ee3500]' : 'text-red-600',
                    ]"
                >
                    <Icon :name="type === 'success' ? 'tick-circle' : 'danger'" :size="28" />
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        :class="[
                            'break-words text-[14px] font-semibold leading-5 sm:text-[15px]',
                            type === 'success' ? 'text-[#d92f00]' : 'text-red-700',
                        ]"
                    >
                        {{ type === 'success' ? 'Success' : 'Something went wrong' }}
                    </p>
                    <p class="mt-1 select-text break-words text-[13px] font-normal leading-5 text-slate-600 sm:text-sm">
                        {{ message }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-0.5 self-start pt-0.5">
                    <button
                        type="button"
                        :class="[
                            'flex h-8 min-w-8 items-center justify-center gap-1 rounded-[6px] px-1.5 transition',
                            type === 'success'
                                ? 'text-[#e44a1b] hover:bg-white/70 hover:text-[#bd2b05]'
                                : 'text-red-400 hover:bg-white/70 hover:text-red-700',
                        ]"
                        :title="copied ? 'Copied' : 'Copy message'"
                        @click="copyMessage"
                    >
                        <Icon :name="copied ? 'tick-circle' : 'copy'" :size="15" />
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex h-8 w-8 items-center justify-center rounded-[6px] transition',
                            type === 'success'
                                ? 'text-[#e44a1b] hover:bg-white/70 hover:text-[#bd2b05]'
                                : 'text-red-400 hover:bg-white/70 hover:text-red-700',
                        ]"
                        title="Dismiss"
                        aria-label="Dismiss notification"
                        @click="dismiss"
                    >
                        <Icon name="close-circle" :size="16" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
