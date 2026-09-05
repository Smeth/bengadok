<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus, Trash2, X, ZoomIn } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import PharmaciePhotosUpload from '@/components/dok-pharma/PharmaciePhotosUpload.vue';
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

const isUploading = () => uploadingCount.value > 0;

function openViewer(pj: PieceJointeImage) {
    if (!pj.file_url) return;
    viewer.value = {
        open: true,
        url: pj.file_url,
        title: pj.label ?? pj.original_name ?? 'Photo',
    };
}

function closeViewer() {
    viewer.value.open = false;
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
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4"
            @click.self="closeViewer"
        >
            <div
                :class="['relative max-h-[90vh] max-w-lg', moduleModalSurfaceClass]"
            >
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <p class="truncate text-[14px] font-bold text-gray-900">
                        {{ viewer.title }}
                    </p>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-gray-500 hover:bg-gray-100"
                        @click="closeViewer"
                    >
                        <X class="size-5" />
                    </button>
                </div>
                <div class="max-h-[75vh] overflow-auto p-3">
                    <img
                        :src="viewer.url"
                        :alt="viewer.title"
                        class="mx-auto max-h-[70vh] w-auto max-w-full object-contain"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>
