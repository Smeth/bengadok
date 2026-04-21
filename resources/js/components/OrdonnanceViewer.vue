<script setup lang="ts">
import { X, ZoomIn, ZoomOut, ExternalLink, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';

const props = defineProps<{
    urlfile: string;
    /** Hauteur max de l'aperçu (images) ou de l'iframe (PDF) */
    maxHeight?: string;
}>();

const maxHeight = computed(() => props.maxHeight ?? '12rem');

function isPdf(url: string): boolean {
    return url?.toLowerCase().endsWith('.pdf') ?? false;
}

const fullUrl = computed(() => `/storage/${props.urlfile}`);
const isPdfFile = computed(() => isPdf(props.urlfile));

const lightboxOpen = ref(false);
const zoomLevel = ref(1);

function openLightbox() {
    lightboxOpen.value = true;
    zoomLevel.value = 1;
}

function closeLightbox() {
    lightboxOpen.value = false;
    zoomLevel.value = 1;
}

function zoomIn() {
    zoomLevel.value = Math.min(zoomLevel.value + 0.25, 3);
}

function zoomOut() {
    zoomLevel.value = Math.max(zoomLevel.value - 0.25, 0.5);
}

function openInNewTab() {
    window.open(fullUrl.value, '_blank', 'noopener');
}

function handleWheel(e: WheelEvent) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    zoomLevel.value = Math.max(0.5, Math.min(3, zoomLevel.value + delta));
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
                    :src="fullUrl"
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
                    :src="`${fullUrl}#toolbar=1`"
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
                        class="flex max-h-[80vh] items-center justify-center overflow-auto bg-black/5 p-4"
                        @wheel.prevent="handleWheel"
                    >
                        <img
                            :src="fullUrl"
                            alt="Ordonnance"
                            class="max-w-full object-contain transition-transform"
                            :style="{ transform: `scale(${zoomLevel})` }"
                            @click.stop
                        />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
