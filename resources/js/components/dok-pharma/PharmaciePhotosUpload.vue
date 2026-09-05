<script setup lang="ts">
import Uppy from '@uppy/core';
import fr_FR from '@uppy/locales/lib/fr_FR.js';
import type { UppyFile } from '@uppy/utils';
import Dashboard from '@uppy/vue/dashboard';
import { ImagePlus } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import '@uppy/core/css/style.css';
import '@uppy/dashboard/css/style.css';
import { moduleTabFocusClass } from '@/lib/bengadokUi';

const ALLOWED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];
const MAX_SIZE = 10 * 1024 * 1024;
const MAX_FILES = 10;

const props = withDefaults(
    defineProps<{
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    upload: [file: File];
}>();

const uppy = shallowRef<InstanceType<typeof Uppy> | null>(null);
const ready = ref(false);
const cardWrapRef = ref<HTMLElement | null>(null);
const pendingCount = ref(0);

function fileFromUppy(file: UppyFile): File {
    const data = file.data;
    if (data instanceof File) {
        return data;
    }
    return new File([data], file.name || 'photo.jpg', {
        type: file.type || (data as Blob).type || 'image/jpeg',
    });
}

function openFilePickerFromCard(): void {
    if (props.disabled) return;
    const input = cardWrapRef.value?.querySelector<HTMLInputElement>(
        'input.uppy-Dashboard-input',
    );
    input?.click();
}

onMounted(() => {
    const u = new Uppy({
        id: `pharmacie-photos-${globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`}`,
        locale: fr_FR,
        restrictions: {
            maxNumberOfFiles: MAX_FILES,
            maxFileSize: MAX_SIZE,
            allowedFileTypes: ALLOWED_TYPES,
        },
        autoProceed: false,
    });

    u.on('file-added', (file) => {
        pendingCount.value = u.getFiles().length;
        emit('upload', fileFromUppy(file));
        u.removeFile(file.id);
        pendingCount.value = u.getFiles().length;
    });

    uppy.value = u;
    ready.value = true;
});

onBeforeUnmount(() => {
    uppy.value?.destroy();
    uppy.value = null;
    ready.value = false;
});
</script>

<template>
    <div class="pharmacie-photos-uppy">
        <div
            ref="cardWrapRef"
            class="relative min-h-[132px] overflow-hidden rounded-[10px] border-2 border-dashed border-[#d1d5db] bg-white/90"
            :class="{ 'pointer-events-none opacity-60': disabled }"
        >
            <div
                v-if="!disabled"
                class="pointer-events-none absolute inset-0 z-[1] flex items-center justify-center"
            >
                <button
                    type="button"
                    :class="[
                        'pointer-events-auto flex cursor-pointer flex-col items-center gap-2 rounded-lg border-0 bg-transparent p-3 text-center outline-none',
                        moduleTabFocusClass,
                    ]"
                    @click="openFilePickerFromCard"
                >
                    <ImagePlus class="size-10 stroke-[1.25] text-[#94a3b8]" />
                    <span
                        class="text-base font-bold tracking-tight text-[#64748b]"
                    >
                        Ajouter une ou plusieurs photos
                    </span>
                    <span class="text-[11px] font-medium text-[#94a3b8]">
                        JPG, PNG, GIF ou WebP — max. 10 Mo
                    </span>
                </button>
            </div>
            <Dashboard
                v-if="ready && uppy"
                :uppy="uppy"
                :props="{
                    proudlyDisplayPoweredByUppy: false,
                    hideUploadButton: true,
                    disableStatusBar: true,
                    height: 132,
                    note: '',
                }"
            />
        </div>
        <p v-if="pendingCount > 0" class="mt-1 text-[11px] text-gray-500">
            {{ pendingCount }} fichier(s) en attente…
        </p>
    </div>
</template>

<style scoped>
.pharmacie-photos-uppy :deep(.uppy-Dashboard-inner) {
    border: none !important;
    border-radius: 0;
    background: transparent !important;
    box-shadow: none !important;
}

.pharmacie-photos-uppy :deep(.uppy-Dashboard-AddFiles) {
    border: none;
    background: transparent;
}

.pharmacie-photos-uppy :deep(.uppy-Dashboard-AddFiles-title) {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.pharmacie-photos-uppy :deep(.uppy-Dashboard-note) {
    display: none;
}

.pharmacie-photos-uppy :deep(.uppy-Dashboard-AddFiles-list) {
    display: none;
}
</style>
