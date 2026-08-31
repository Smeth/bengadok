<script setup lang="ts">
import { AlertCircle, BellRing, CheckCircle2, Info, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        description?: string;
        variant?: 'success' | 'error' | 'info' | 'urgent';
        /** 0 = pas de fermeture auto */
        durationMs?: number;
    }>(),
    {
        variant: 'success',
        durationMs: 6500,
    },
);

const variantClasses = {
    success: {
        container: 'border-emerald-200 bg-emerald-50',
        title: 'text-emerald-900',
        description: 'text-emerald-800',
        icon: 'text-emerald-600',
        close: 'text-emerald-600 hover:text-emerald-800 focus:ring-emerald-400 focus:ring-offset-emerald-50',
    },
    error: {
        container: 'border-red-200 bg-red-50',
        title: 'text-red-900',
        description: 'text-red-700',
        icon: 'text-red-600',
        close: 'text-red-500 hover:text-red-700 focus:ring-red-400 focus:ring-offset-red-50',
    },
    info: {
        container: 'border-sky-200 bg-sky-50',
        title: 'text-sky-950',
        description: 'text-sky-800',
        icon: 'text-sky-600',
        close: 'text-sky-600 hover:text-sky-800 focus:ring-sky-400 focus:ring-offset-sky-50',
    },
    urgent: {
        container: 'border-amber-300 bg-amber-50 shadow-md ring-2 ring-amber-200/80',
        title: 'text-amber-950',
        description: 'text-amber-900',
        icon: 'text-amber-600',
        close: 'text-amber-700 hover:text-amber-900 focus:ring-amber-400 focus:ring-offset-amber-50',
    },
} as const;

const activeVariant = computed(() => variantClasses[props.variant]);

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
                class="pointer-events-auto fixed bottom-6 right-6 z-[250] w-[min(92vw,24rem)] overflow-hidden rounded-xl border shadow-sm"
                :class="activeVariant.container"
                role="status"
            >
                <div class="flex items-start gap-3 p-4">
                    <BellRing
                        v-if="variant === 'urgent'"
                        class="size-5 shrink-0 animate-pulse"
                        :class="activeVariant.icon"
                        aria-hidden="true"
                    />
                    <Info
                        v-else-if="variant === 'info'"
                        class="size-5 shrink-0"
                        :class="activeVariant.icon"
                        aria-hidden="true"
                    />
                    <CheckCircle2
                        v-else-if="variant === 'success'"
                        class="size-5 shrink-0"
                        :class="activeVariant.icon"
                        aria-hidden="true"
                    />
                    <AlertCircle
                        v-else
                        class="size-5 shrink-0"
                        :class="activeVariant.icon"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1 pt-0.5">
                        <p
                            class="text-sm font-semibold"
                            :class="activeVariant.title"
                        >
                            {{ title }}
                        </p>
                        <p
                            v-if="description"
                            class="mt-1 text-sm"
                            :class="activeVariant.description"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="activeVariant.close"
                        aria-label="Fermer la notification"
                        @click="close"
                    >
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
