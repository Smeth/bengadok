<script setup lang="ts">
import { X, ZoomIn, ZoomOut, ExternalLink, FileText } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';

const props = defineProps<{
    fileUrl: string;
    isPdf?: boolean;
    /** Hauteur max de l'aperçu (images) ou de l'iframe (PDF) */
    maxHeight?: string;
}>();

const maxHeight = computed(() => props.maxHeight ?? '12rem');
const isPdfFile = computed(() => props.isPdf ?? false);

const lightboxOpen = ref(false);
const zoomLevel = ref(1);
const panX = ref(0);
const panY = ref(0);
const dragging = ref(false);
const lastPointer = ref({ x: 0, y: 0 });

const canPan = computed(() => zoomLevel.value > 1);

const imageTransform = computed(
    () => `translate(${panX.value}px, ${panY.value}px) scale(${zoomLevel.value})`,
);

function resetView() {
    zoomLevel.value = 1;
    panX.value = 0;
    panY.value = 0;
    dragging.value = false;
}

function openLightbox() {
    lightboxOpen.value = true;
    resetView();
}

function closeLightbox() {
    lightboxOpen.value = false;
    resetView();
}

function zoomIn() {
    zoomLevel.value = Math.min(zoomLevel.value + 0.25, 3);
}

function zoomOut() {
    zoomLevel.value = Math.max(zoomLevel.value - 0.25, 0.5);
    if (zoomLevel.value <= 1) {
        panX.value = 0;
        panY.value = 0;
    }
}

function openInNewTab() {
    window.open(props.fileUrl, '_blank', 'noopener');
}

function handleWheel(e: WheelEvent) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    const next = Math.max(0.5, Math.min(3, zoomLevel.value + delta));
    zoomLevel.value = next;
    if (next <= 1) {
        panX.value = 0;
        panY.value = 0;
    }
}

function onPointerDown(e: PointerEvent) {
    if (!canPan.value) return;
    dragging.value = true;
    lastPointer.value = { x: e.clientX, y: e.clientY };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
}

function onPointerMove(e: PointerEvent) {
    if (!dragging.value) return;
    panX.value += e.clientX - lastPointer.value.x;
    panY.value += e.clientY - lastPointer.value.y;
    lastPointer.value = { x: e.clientX, y: e.clientY };
}

function onPointerUp(e: PointerEvent) {
    dragging.value = false;
    try {
        (e.currentTarget as HTMLElement).releasePointerCapture(e.pointerId);
    } catch {
        // ignore
    }
}

watch(zoomLevel, (value) => {
    if (value <= 1) {
        panX.value = 0;
        panY.value = 0;
    }
});
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
                                >{{ Math.round(zoomLevel * 100) }}%</span
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
                        class="relative max-h-[80vh] overflow-hidden bg-black/5 p-4"
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
                            class="flex min-h-[60vh] items-center justify-center"
                        >
                            <img
                                :src="fileUrl"
                                alt="Ordonnance"
                                class="max-w-none select-none object-contain transition-transform duration-75"
                                :style="{ transform: imageTransform }"
                                draggable="false"
                                @click.stop
                            />
                        </div>
                        <p
                            v-if="canPan"
                            class="pointer-events-none absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-1 text-xs text-white"
                        >
                            Glissez pour déplacer l’image zoomée
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
