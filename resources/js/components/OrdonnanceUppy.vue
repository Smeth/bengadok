<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import Uppy from '@uppy/core';
import Dashboard from '@uppy/vue/dashboard';
import fr_FR from '@uppy/locales/lib/fr_FR.js';
import type { UppyFile } from '@uppy/utils';
import '@uppy/core/css/style.css';
import '@uppy/dashboard/css/style.css';

const ALLOWED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
];
const MAX_SIZE = 10 * 1024 * 1024;

const props = withDefaults(
    defineProps<{
        modelValue: File | null;
        /** Texte optionnel au-dessus de la zone */
        label?: string;
    }>(),
    { label: '' },
);

const emit = defineEmits<{
    'update:modelValue': [value: File | null];
}>();

const uppy = shallowRef<InstanceType<typeof Uppy> | null>(null);
const ready = ref(false);

const dashboardProps = {
    proudlyDisplayPoweredByUppy: false,
    hideUploadButton: true,
    disableStatusBar: true,
    height: 220,
    note: 'JPG, PNG, GIF, WebP ou PDF — max. 10 Mo.',
} as const;

function fileFromUppy(file: UppyFile): File {
    const data = file.data;
    if (data instanceof File) {
        return data;
    }
    return new File([data], file.name || 'ordonnance', {
        type: file.type || (data as Blob).type || 'application/octet-stream',
    });
}

function clearFiles() {
    const u = uppy.value;
    if (!u) return;
    u.getFiles().forEach((f) => {
        u.removeFile(f.id);
    });
}

onMounted(() => {
    const u = new Uppy({
        id: `ordonnance-${globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`}`,
        locale: fr_FR,
        restrictions: {
            maxNumberOfFiles: 1,
            maxFileSize: MAX_SIZE,
            allowedFileTypes: ALLOWED_TYPES,
        },
        autoProceed: false,
    });

    u.on('file-added', (file) => {
        const files = u.getFiles();
        if (files.length > 1) {
            const previous = files.find((f) => f.id !== file.id);
            if (previous) {
                u.removeFile(previous.id);
            }
        }
        emit('update:modelValue', fileFromUppy(file));
    });

    u.on('file-removed', () => {
        if (u.getFiles().length === 0) {
            emit('update:modelValue', null);
        }
    });

    uppy.value = u;
    ready.value = true;
});

onBeforeUnmount(() => {
    uppy.value?.destroy();
    uppy.value = null;
    ready.value = false;
});

watch(
    () => props.modelValue,
    (file) => {
        if (file) return;
        clearFiles();
    },
);
</script>

<template>
    <div class="ordonnance-uppy">
        <p v-if="label" class="mb-2 text-sm font-medium text-muted-foreground">
            {{ label }}
        </p>
        <Dashboard v-if="ready && uppy" :uppy="uppy" :props="dashboardProps" />
    </div>
</template>

<style scoped>
.ordonnance-uppy :deep(.uppy-Dashboard-inner) {
    border-radius: 0.625rem;
}
</style>
