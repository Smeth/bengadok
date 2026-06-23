<script setup lang="ts">
import { AlertCircle, CheckCircle2, X } from 'lucide-vue-next';
import { watch } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        description?: string;
        variant?: 'success' | 'error';
        /** 0 = pas de fermeture auto */
        durationMs?: number;
    }>(),
    {
        variant: 'success',
        durationMs: 6500,
    },
);

const emit = defineEmits<{
    'update:show': [value: boolean];
}>();

let hideTimer: ReturnType<typeof setTimeout> | undefined;

function close(): void {
    emit('update:show', false);
}

watch(
    () => props.show,
    (visible) => {
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = undefined;
        }
        if (visible && props.durationMs > 0) {
            hideTimer = setTimeout(close, props.durationMs);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 sm:translate-x-2 sm:translate-y-0"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="pointer-events-auto fixed bottom-6 right-6 z-[200] w-[min(92vw,24rem)] overflow-hidden rounded-lg shadow-lg ring-1 ring-black/5"
                :class="
                    variant === 'error'
                        ? 'bg-red-50 ring-red-200'
                        : 'bg-gray-900'
                "
                role="status"
            >
                <div class="flex p-4">
                    <div class="shrink-0">
                        <CheckCircle2
                            v-if="variant === 'success'"
                            class="size-5 text-emerald-400"
                            aria-hidden="true"
                        />
                        <AlertCircle
                            v-else
                            class="size-5 text-red-500"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p
                            class="text-sm font-medium"
                            :class="
                                variant === 'error'
                                    ? 'text-red-900'
                                    : 'text-white'
                            "
                        >
                            {{ title }}
                        </p>
                        <p
                            v-if="description"
                            class="mt-1 text-sm"
                            :class="
                                variant === 'error'
                                    ? 'text-red-700'
                                    : 'text-gray-300'
                            "
                        >
                            {{ description }}
                        </p>
                    </div>
                    <div class="ml-4 flex shrink-0">
                        <button
                            type="button"
                            class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="
                                variant === 'error'
                                    ? 'text-red-500 hover:text-red-700 focus:ring-red-400 focus:ring-offset-red-50'
                                    : 'text-gray-400 hover:text-gray-200 focus:ring-emerald-400 focus:ring-offset-gray-900'
                            "
                            aria-label="Fermer la notification"
                            @click="close"
                        >
                            <X class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
