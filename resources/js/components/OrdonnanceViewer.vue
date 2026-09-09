<script setup lang="ts">
import { X, ZoomIn, ZoomOut, ExternalLink, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useImageZoomPan } from '@/composables/useImageZoomPan';

const props = defineProps<{
    fileUrl: string;
    isPdf?: boolean;
    /** Hauteur max de l'aperçu (images) ou de l'iframe (PDF) */
    maxHeight?: string;
}>();

const maxHeight = computed(() => props.maxHeight ?? '12rem');
const isPdfFile = computed(() => props.isPdf ?? false);

const lightboxOpen = ref(false);

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
} = useImageZoomPan();

function openLightbox() {
    lightboxOpen.value = true;
    resetView();
}

function closeLightbox() {
    lightboxOpen.value = false;
    resetView();
}

function openInNewTab() {
    window.open(props.fileUrl, '_blank', 'noopener');
}
</script>

<template>
    <div class="space-y-2">
        <!-- Image : aperçu cliquable + zoom -->
        <template v-if="!isPdfFile">
            <div
                class="flex cursor-zoom-in justify-center overflow-hidden rounded border bg-muted/30"
                :style="{ maxHeight }"
                @click="openLightbox"
            >
                <img
                    :src="fileUrl"
                    alt="Ordonnance"
                    class="max-h-full w-auto object-contain transition-opacity hover:opacity-90"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="openLightbox">
                    <ZoomIn class="mr-1 size-3.5" />
                    Agrandir
                </Button>
                <Button variant="outline" size="sm" @click="openInNewTab">
                    <ExternalLink class="mr-1 size-3.5" />
                    Ouvrir dans un nouvel onglet
                </Button>
            </div>
        </template>

        <!-- PDF : iframe + lien -->
        <template v-else>
            <div
                class="overflow-hidden rounded border bg-muted/30"
                :style="{ maxHeight }"
            >
                <iframe
                    :src="`${fileUrl}#toolbar=1`"
                    class="h-full w-full border-0"
                    title="Ordonnance PDF"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="openInNewTab">
                    <FileText class="mr-1 size-3.5" />
                    Ouvrir le PDF
                </Button>
            </div>
        </template>

        <!-- Lightbox pour images -->
        <Dialog
            :open="lightboxOpen"
            @update:open="(v: boolean) => !v && closeLightbox()"
        >
            <DialogContent
                :show-close-button="false"
                class="max-h-[90vh] max-w-[95vw] overflow-hidden p-0"
                @pointer-down-outside="closeLightbox"
            >
                <div class="flex flex-col">
                    <div
                        class="flex items-center justify-between border-b bg-muted/30 px-4 py-2"
                    >
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                @click="zoomOut"
                            >
                                <ZoomOut class="size-4" />
                            </Button>
                            <span class="text-sm text-muted-foreground"
                                >{{ zoomPercent }}%</span
                            >
                            <Button
                                variant="outline"
                                size="icon"
                                @click="zoomIn"
                            >
                                <ZoomIn class="size-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openInNewTab"
                            >
                                <ExternalLink class="mr-1 size-3.5" />
                                Nouvel onglet
                            </Button>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="closeLightbox"
                        >
                            <X class="size-5" />
                        </Button>
                    </div>
                    <div
                        class="relative h-[min(80vh,720px)] w-full overflow-hidden bg-black/5"
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
                                :src="fileUrl"
                                alt="Ordonnance"
                                class="max-h-full max-w-full select-none object-contain transition-transform duration-75"
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
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
