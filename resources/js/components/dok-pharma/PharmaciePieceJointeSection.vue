<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus, Trash2, X, ZoomIn } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import OrdonnanceFilePreview from '@/components/OrdonnanceFilePreview.vue';

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

const label = ref('');
const pendingFile = ref<File | null>(null);
const uploading = ref(false);
const viewer = ref<{ open: boolean; url: string; title: string }>({
    open: false,
    url: '',
    title: '',
});

function onFilePick(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    pendingFile.value = file;
    input.value = '';
}

function clearPending() {
    pendingFile.value = null;
}

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

function upload() {
    if (!pendingFile.value || uploading.value) return;

    const fd = new FormData();
    fd.append('fichier', pendingFile.value);
    const trimmed = label.value.trim();
    if (trimmed) {
        fd.append('label', trimmed);
    }

    uploading.value = true;
    router.post(`/dok-pharma/${props.commandeId}/pieces-jointes`, fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            pendingFile.value = null;
            label.value = '';
        },
        onFinish: () => {
            uploading.value = false;
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
        pendingFile.value = null;
        label.value = '';
    },
);

onBeforeUnmount(closeViewer);
</script>

<template>
    <div
        v-if="pieces.length || editable"
        class="space-y-3 rounded-xl border border-dashed border-gray-200 bg-white px-4 py-3"
    >
        <p class="flex items-center gap-2 text-[13px] font-bold text-gray-700">
            <ImagePlus class="size-4 text-[#0d6efd]" />
            Photos jointes
        </p>

        <div
            v-if="pieces.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
        >
            <div
                v-for="pj in pieces"
                :key="pj.id"
                class="group relative overflow-hidden rounded-lg border border-gray-100 bg-gray-50"
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

        <div v-if="editable" class="space-y-2 border-t border-gray-100 pt-3">
            <p class="text-[11px] text-gray-500">
                JPG, PNG, GIF ou WebP — max. 10 Mo
            </p>
            <input
                v-model="label"
                type="text"
                placeholder="Libellé (facultatif) — ex. Colis, étiquette…"
                class="h-9 w-full rounded-lg border border-gray-200 px-3 text-[12px]"
            />
            <label
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[#93c5fd]/60 bg-[#eff6ff]/40 px-4 py-4 text-center transition-colors hover:border-[#0d6efd]/50 hover:bg-[#eff6ff]/70"
            >
                <ImagePlus class="size-8 text-[#0d6efd]/70" />
                <span class="text-[12px] font-semibold text-[#0d6efd]"
                    >Choisir une image</span
                >
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="sr-only"
                    @change="onFilePick"
                />
            </label>

            <div v-if="pendingFile" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate text-[12px] text-gray-600">{{
                        pendingFile.name
                    }}</span>
                    <button
                        type="button"
                        class="shrink-0 text-gray-400 hover:text-gray-700"
                        aria-label="Retirer"
                        @click="clearPending"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <OrdonnanceFilePreview
                    :file="pendingFile"
                    max-height="8rem"
                />
                <button
                    type="button"
                    class="w-full rounded-lg bg-[#0d6efd] px-4 py-2 text-[12px] font-semibold text-white disabled:opacity-50"
                    :disabled="uploading"
                    @click="upload"
                >
                    {{ uploading ? 'Envoi…' : 'Joindre la photo' }}
                </button>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="viewer.open"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4"
            @click.self="closeViewer"
        >
            <div
                class="relative max-h-[90vh] max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
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
