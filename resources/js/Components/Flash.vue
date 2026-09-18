<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const page = usePage();
const visible = ref(false);
const copied = ref(false);
let dismissTimer = null;
let copiedTimer = null;
let removeSuccessListener = null;

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

const showCurrentFlash = () => {
    if (!message.value) {
        dismiss();
        return;
    }

    visible.value = true;
    copied.value = false;
    startDismissTimer();
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

watch(message, showCurrentFlash, { immediate: true });

onMounted(() => {
    // Inertia can return the exact same flash text twice in a row (for example,
    // repeated cart updates). Listen to every successful visit so the alert is
    // shown again even when the message string itself did not change.
    removeSuccessListener = router.on('success', () => {
        window.setTimeout(showCurrentFlash, 0);
    });
});

onBeforeUnmount(() => {
    if (dismissTimer) clearTimeout(dismissTimer);
    if (copiedTimer) clearTimeout(copiedTimer);
    if (removeSuccessListener) removeSuccessListener();
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
            class="fixed right-2.5 top-2.5 z-[100] w-[calc(100%-1.25rem)] max-w-[320px] sm:right-4 sm:top-4 sm:w-full"
            role="status"
            aria-live="polite"
        >
            <div
                :class="[
                    'flex min-h-[52px] items-center gap-2.5 rounded-[8px] border px-3 py-2 shadow-[0_8px_22px_rgba(15,23,42,0.08)]',
                    type === 'success'
                        ? 'border-[#ffd9c5] bg-[#fff4ed]'
                        : 'border-red-200 bg-[#fff1f2]',
                ]"
            >
                <div
                    :class="[
                        'flex h-7 w-7 shrink-0 items-center justify-center',
                        type === 'success' ? 'text-[#ee3500]' : 'text-red-600',
                    ]"
                >
                    <Icon :name="type === 'success' ? 'tick-circle' : 'danger'" :size="18" />
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        :class="[
                            'break-words text-[11px] font-semibold leading-4 sm:text-xs',
                            type === 'success' ? 'text-[#d92f00]' : 'text-red-700',
                        ]"
                    >
                        {{ type === 'success' ? 'Success' : 'Something went wrong' }}
                    </p>
                    <p class="mt-0.5 select-text break-words text-[11px] font-normal leading-4 text-slate-600 sm:text-xs">
                        {{ message }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-0.5 self-start">
                    <button
                        type="button"
                        :class="[
                            'flex h-6 w-6 items-center justify-center rounded-[5px] transition',
                            type === 'success'
                                ? 'text-[#e44a1b] hover:bg-white/70 hover:text-[#bd2b05]'
                                : 'text-red-400 hover:bg-white/70 hover:text-red-700',
                        ]"
                        :title="copied ? 'Copied' : 'Copy message'"
                        :aria-label="copied ? 'Message copied' : 'Copy message'"
                        @click="copyMessage"
                    >
                        <Icon :name="copied ? 'tick-circle' : 'copy'" :size="13" />
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex h-6 w-6 items-center justify-center rounded-[5px] transition',
                            type === 'success'
                                ? 'text-[#e44a1b] hover:bg-white/70 hover:text-[#bd2b05]'
                                : 'text-red-400 hover:bg-white/70 hover:text-red-700',
                        ]"
                        title="Dismiss"
                        aria-label="Dismiss notification"
                        @click="dismiss"
                    >
                        <Icon name="close-circle" :size="13" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
