<script setup lang="ts">
import { FileText, RefreshCw, X, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { watch } from 'vue';
import { useImageZoomPan } from '@/composables/useImageZoomPan';
import { moduleModalSurfaceClass } from '@/lib/bengadokUi';

const props = defineProps<{
    open: boolean;
    url?: string;
    isPdf?: boolean;
    numero?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const {
    zoomPercent,
    dragging,
    canPan,
    imageTransform,
    resetView,
    zoomIn,
    zoomOut,
    handleWheel,
    onPointerDown,
    onPointerMove,
    onPointerUp,
} = useImageZoomPan({ max: 2 });

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetView();
        }
    },
);

function close() {
    resetView();
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0, 0, 0, 0.55)"
            @click.self="close"
        >
            <div
                :class="[
                    'relative flex max-h-[90vh] w-full max-w-[500px] flex-col',
                    moduleModalSurfaceClass,
                ]"
            >
                <div
                    class="flex items-center gap-3 border-b border-gray-100 px-5 py-4 dark:border-border"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF]"
                    >
                        <FileText class="size-5 text-[#459cd1]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-[14px] font-extrabold text-gray-900 dark:text-foreground"
                        >
                            Ordonnance — Commande {{ numero }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200 dark:bg-muted dark:text-muted-foreground dark:hover:bg-muted/80"
                        @click="close"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div
                    v-if="!isPdf"
                    class="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2 dark:border-border dark:bg-muted/40"
                >
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 dark:border-border dark:bg-input dark:text-foreground dark:hover:bg-muted"
                        @click="zoomOut"
                    >
                        <ZoomOut class="size-3.5" />
                    </button>
                    <span
                        class="min-w-[40px] text-center text-[12px] font-semibold text-gray-700 dark:text-foreground"
                        >{{ zoomPercent }}%</span
                    >
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 dark:border-border dark:bg-input dark:text-foreground dark:hover:bg-muted"
                        @click="zoomIn"
                    >
                        <ZoomIn class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 dark:border-border dark:bg-input dark:text-foreground dark:hover:bg-muted"
                        aria-label="Réinitialiser le zoom"
                        @click="resetView"
                    >
                        <RefreshCw class="size-3.5" />
                    </button>
                </div>
                <div
                    v-if="url && isPdf"
                    class="flex min-h-[250px] flex-1 items-center justify-center overflow-auto bg-gray-100 p-4 dark:bg-muted/30"
                >
                    <iframe
                        :src="`${url}#toolbar=1`"
                        class="h-[min(70vh,520px)] w-full rounded-lg border-0 bg-white shadow"
                        title="Ordonnance PDF"
                    />
                </div>
                <div
                    v-else-if="url"
                    class="relative h-[min(70vh,520px)] w-full overflow-hidden bg-gray-100 dark:bg-muted/30"
                    :class="
                        canPan
                            ? dragging
                                ? 'cursor-grabbing'
                                : 'cursor-grab'
                            : 'cursor-default'
                    "
                    @wheel.prevent="handleWheel"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @pointerleave="onPointerUp"
                >
                    <div
                        class="flex h-full w-full items-center justify-center p-4"
                    >
                        <img
                            :src="url"
                            alt="Ordonnance"
                            class="max-h-full max-w-full select-none rounded-lg object-contain shadow"
                            :style="{
                                transform: imageTransform,
                                transformOrigin: 'center center',
                            }"
                            draggable="false"
                            @click.stop
                        />
                    </div>
                    <p
                        v-if="canPan"
                        class="pointer-events-none absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-1 text-xs text-white"
                    >
                        Glissez pour déplacer l’image
                    </p>
                </div>
                <div
                    v-else
                    class="flex min-h-[250px] flex-1 items-center justify-center bg-gray-100 p-4 text-center text-gray-400 dark:bg-muted/30"
                >
                    <div>
                        <FileText class="mx-auto mb-2 size-10 opacity-40" />
                        <p class="text-[13px]">Aucune ordonnance disponible</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
