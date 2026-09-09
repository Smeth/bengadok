<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus, RefreshCw, Trash2, X, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import PharmaciePhotosUpload from '@/components/dok-pharma/PharmaciePhotosUpload.vue';
import { useImageZoomPan } from '@/composables/useImageZoomPan';
import { moduleModalSurfaceClass, modulePrimaryTextClass } from '@/lib/bengadokUi';

export type PieceJointeImage = {
    id: number;
    label?: string | null;
    original_name?: string | null;
    file_url?: string | null;
    created_at?: string | null;
};

const props = withDefaults(
    defineProps<{
        commandeId: number;
        pieces: PieceJointeImage[];
        /** Affiche zone d’ajout (onglets actifs pharmacie). */
        editable?: boolean;
    }>(),
    {
        editable: false,
        pieces: () => [],
    },
);

const uploadingCount = ref(0);
const viewer = ref<{ open: boolean; url: string; title: string }>({
    open: false,
    url: '',
    title: '',
});

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

const isUploading = () => uploadingCount.value > 0;

function openViewer(pj: PieceJointeImage) {
    if (!pj.file_url) return;
    resetView();
    viewer.value = {
        open: true,
        url: pj.file_url,
        title: pj.label ?? pj.original_name ?? 'Photo',
    };
}

function closeViewer() {
    viewer.value.open = false;
    resetView();
}

function uploadFile(file: File) {
    if (uploadingCount.value >= 3) {
        window.setTimeout(() => uploadFile(file), 400);
        return;
    }

    const fd = new FormData();
    fd.append('fichier', file);

    uploadingCount.value += 1;
    router.post(`/dok-pharma/${props.commandeId}/pieces-jointes`, fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploadingCount.value = Math.max(0, uploadingCount.value - 1);
        },
    });
}

function remove(pjId: number) {
    if (!confirm('Supprimer cette photo ?')) return;

    router.delete(
        `/dok-pharma/${props.commandeId}/pieces-jointes/${pjId}`,
        { preserveScroll: true },
    );
}

watch(
    () => props.commandeId,
    () => {
        uploadingCount.value = 0;
    },
);

onBeforeUnmount(closeViewer);
</script>

<template>
    <div
        v-if="pieces.length || editable"
        class="space-y-3"
    >
        <p
            v-if="pieces.length || editable"
            class="flex items-center gap-2 text-[13px] font-bold text-gray-700"
        >
            <ImagePlus class="size-4" :class="modulePrimaryTextClass" />
            Photos jointes
            <span
                v-if="isUploading()"
                class="text-[11px] font-normal text-gray-500"
            >
                (envoi en cours…)
            </span>
        </p>

        <div
            v-if="pieces.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
        >
            <div
                v-for="pj in pieces"
                :key="pj.id"
                class="group relative overflow-hidden rounded-lg border border-gray-200/80 bg-gray-50/80"
            >
                <button
                    type="button"
                    class="block w-full"
                    @click="openViewer(pj)"
                >
                    <img
                        v-if="pj.file_url"
                        :src="pj.file_url"
                        :alt="pj.label ?? pj.original_name ?? 'Photo'"
                        class="aspect-square w-full object-cover transition-transform group-hover:scale-[1.02]"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="flex aspect-square items-center justify-center text-[10px] text-gray-400"
                    >
                        Photo
                    </div>
                </button>
                <button
                    v-if="editable"
                    type="button"
                    class="absolute right-1 top-1 flex size-6 items-center justify-center rounded-full bg-red-600/90 text-white opacity-0 shadow transition-opacity group-hover:opacity-100"
                    aria-label="Supprimer la photo"
                    @click.stop="remove(pj.id)"
                >
                    <Trash2 class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="absolute bottom-1 right-1 flex size-6 items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity group-hover:opacity-100"
                    aria-label="Agrandir"
                    @click.stop="openViewer(pj)"
                >
                    <ZoomIn class="size-3.5" />
                </button>
                <p
                    v-if="pj.label || pj.original_name"
                    class="truncate px-1.5 py-1 text-[10px] text-gray-600"
                    :title="pj.label ?? pj.original_name ?? undefined"
                >
                    {{ pj.label ?? pj.original_name }}
                </p>
            </div>
        </div>

        <p
            v-else-if="!editable"
            class="text-[12px] text-gray-400"
        >
            Aucune photo.
        </p>

        <PharmaciePhotosUpload
            v-if="editable"
            :disabled="isUploading()"
            @upload="uploadFile"
        />
    </div>

    <Teleport to="body">
        <div
            v-if="viewer.open"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            style="background: rgba(0, 0, 0, 0.55)"
            @click.self="closeViewer"
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
                    <p
                        class="min-w-0 flex-1 truncate text-[14px] font-extrabold text-gray-900 dark:text-foreground"
                    >
                        {{ viewer.title }}
                    </p>
                    <button
                        type="button"
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200 dark:bg-muted dark:text-muted-foreground dark:hover:bg-muted/80"
                        @click="closeViewer"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div
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
                            :src="viewer.url"
                            :alt="viewer.title"
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
            </div>
        </div>
    </Teleport>
</template>
