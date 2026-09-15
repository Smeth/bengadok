<script setup lang="ts">
import Uppy from '@uppy/core';
import fr_FR from '@uppy/locales/lib/fr_FR.js';
import type { UppyFile } from '@uppy/utils';
import Dashboard from '@uppy/vue/dashboard';
import { ClipboardList, Paperclip, Pill, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import '@uppy/core/css/style.css';
import '@uppy/dashboard/css/style.css';
import { ordonnanceFilesFromValue } from '@/lib/commandeCreationFields';
import { moduleTabFocusClass } from '@/lib/bengadokUi';
import { getUploadLimits, uploadMaxSizeNote } from '@/lib/uploadLimits';

const uploadLimits = getUploadLimits();

const ALLOWED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
];
const MAX_SIZE = uploadLimits.max_bytes;
const MAX_FILES = 10;

const props = withDefaults(
    defineProps<{
        modelValue: File | File[] | null;
        /** Texte optionnel au-dessus de la zone */
        label?: string;
        /** Affiche l’info analyse OCR / règles (flux back-office) */
        showAnalysisNotice?: boolean;
        /** Remplace le texte par défaut de la notice (si showAnalysisNotice) */
        analysisNotice?: string;
        /**
         * `card` : zone en pointillés (ex. modale commande).
         * `inline` : une ligne, plusieurs fichiers possibles.
         */
        variant?: 'default' | 'card' | 'inline';
        multiple?: boolean;
    }>(),
    {
        label: '',
        showAnalysisNotice: false,
        analysisNotice: '',
        variant: 'default',
        multiple: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: File | File[] | null];
}>();

const uppy = shallowRef<InstanceType<typeof Uppy> | null>(null);
const ready = ref(false);
const cardWrapRef = ref<HTMLElement | null>(null);
const selectedFiles = ref<File[]>([]);

const isInline = computed(
    () => props.variant === 'inline' || (props.variant === 'card' && props.multiple),
);

const dashboardProps = computed(() => ({
    proudlyDisplayPoweredByUppy: false,
    hideUploadButton: true,
    disableStatusBar: true,
    height: isInline.value ? 1 : props.variant === 'card' ? 132 : 220,
    note:
        props.variant === 'card' || isInline.value
            ? ''
            : uploadMaxSizeNote('JPG, PNG, GIF, WebP ou PDF'),
}));

function fileFromUppy(file: UppyFile): File {
    const data = file.data;
    if (data instanceof File) {
        return data;
    }
    return new File([data], file.name || 'ordonnance', {
        type: file.type || (data as Blob).type || 'application/octet-stream',
    });
}

function emitCurrentFiles() {
    const u = uppy.value;
    const files = u ? u.getFiles().map(fileFromUppy) : [];
    selectedFiles.value = files;
    if (props.multiple) {
        emit('update:modelValue', files);
        return;
    }
    emit('update:modelValue', files[0] ?? null);
}

function clearFiles() {
    const u = uppy.value;
    if (!u) return;
    u.getFiles().forEach((f) => {
        u.removeFile(f.id);
    });
}

function openFilePickerFromCard(): void {
    const input = cardWrapRef.value?.querySelector<HTMLInputElement>(
        'input.uppy-Dashboard-input',
    );
    input?.click();
}

function removeSelectedFile(index: number): void {
    const u = uppy.value;
    if (!u) return;
    const file = u.getFiles()[index];
    if (file) {
        u.removeFile(file.id);
    }
}

onMounted(() => {
    const u = new Uppy({
        id: `ordonnance-${globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`}`,
        locale: fr_FR,
        restrictions: {
            maxNumberOfFiles: props.multiple ? MAX_FILES : 1,
            maxFileSize: MAX_SIZE,
            allowedFileTypes: ALLOWED_TYPES,
        },
        autoProceed: false,
    });

    u.on('file-added', (file) => {
        if (!props.multiple) {
            const files = u.getFiles();
            if (files.length > 1) {
                const previous = files.find((f) => f.id !== file.id);
                if (previous) {
                    u.removeFile(previous.id);
                }
            }
        }
        emitCurrentFiles();
    });

    u.on('file-removed', () => {
        emitCurrentFiles();
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
    (value) => {
        if (ordonnanceFilesFromValue(value).length > 0) return;
        clearFiles();
        selectedFiles.value = [];
    },
);
</script>

<template>
    <div
        class="ordonnance-uppy"
        :class="{
            'ordonnance-uppy--card': variant === 'card' && !isInline,
            'ordonnance-uppy--inline': isInline,
        }"
    >
        <p
            v-if="label"
            class="mb-2 text-sm font-medium text-muted-foreground"
        >
            {{ label }}
        </p>
        <p
            v-if="showAnalysisNotice && variant === 'default'"
            class="mb-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs leading-relaxed text-sky-950"
        >
            <template v-if="analysisNotice">{{ analysisNotice }}</template>
            <template v-else>
                Après enregistrement de la commande, le fichier sera analysé
                automatiquement (OCR et règles métier). Le résultat s’affiche sur
                la fiche détail de la commande ; en mode file d’attente, comptez
                quelques secondes avant actualisation.
            </template>
        </p>
        <div
            ref="cardWrapRef"
            class="relative"
            :class="
                isInline
                    ? 'min-h-[48px] overflow-hidden rounded-[10px] border-2 border-dashed border-[#d1d5db] bg-white'
                    : variant === 'card'
                      ? 'min-h-[132px] overflow-hidden rounded-[10px] border-2 border-dashed border-[#d1d5db] bg-white'
                      : ''
            "
        >
            <div
                v-if="isInline"
                class="relative z-[2] flex min-h-[48px] flex-wrap items-center gap-2 px-3 py-2"
            >
                <button
                    type="button"
                    :class="[
                        'inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-[#cbd5e1] bg-[#f8fafc] px-3 py-1.5 text-sm font-semibold text-[#475569] hover:bg-[#f1f5f9]',
                        moduleTabFocusClass,
                    ]"
                    @click="openFilePickerFromCard"
                >
                    <Paperclip class="size-4" />
                    {{
                        selectedFiles.length
                            ? 'Ajouter'
                            : multiple
                              ? 'Ajouter des fichiers'
                              : 'Ajouter un fichier'
                    }}
                </button>
                <span
                    v-if="!selectedFiles.length"
                    class="text-xs text-[#94a3b8]"
                >
                    JPG, PNG, GIF, WebP ou PDF — plusieurs fichiers
                </span>
                <span
                    v-for="(file, index) in selectedFiles"
                    :key="`${file.name}-${index}`"
                    class="inline-flex max-w-[14rem] items-center gap-1 rounded-full bg-[#e2e8f0] px-2.5 py-1 text-xs font-medium text-[#334155]"
                >
                    <span class="truncate">{{ file.name }}</span>
                    <button
                        type="button"
                        class="shrink-0 rounded-full p-0.5 text-[#64748b] hover:bg-white hover:text-[#dc3545]"
                        :aria-label="`Retirer ${file.name}`"
                        @click="removeSelectedFile(index)"
                    >
                        <X class="size-3.5" />
                    </button>
                </span>
            </div>
            <div
                v-else-if="variant === 'card' && !modelValue"
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
                    <span class="relative inline-flex text-[#94a3b8]">
                        <ClipboardList class="size-10 stroke-[1.25]" />
                        <Pill
                            class="absolute -bottom-0.5 -right-1 size-5 text-[#94a3b8]"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="text-base font-bold tracking-tight text-[#64748b]"
                    >
                        Ajouter une ordonnance
                    </span>
                </button>
            </div>
            <Dashboard
                v-if="ready && uppy"
                :uppy="uppy"
                :props="dashboardProps"
            />
        </div>
    </div>
</template>

<style scoped>
.ordonnance-uppy:not(.ordonnance-uppy--card):not(.ordonnance-uppy--inline)
    :deep(.uppy-Dashboard-inner) {
    border-radius: 0.625rem;
}

/* Variant carte : fond neutre, pas de double bordure Uppy */
.ordonnance-uppy--card :deep(.uppy-Dashboard-inner) {
    border: none !important;
    border-radius: 0;
    background: transparent !important;
    box-shadow: none !important;
}

.ordonnance-uppy--card :deep(.uppy-Dashboard-AddFiles) {
    border: none;
    background: transparent;
}

/* Masquer le libellé Uppy (glisser / parcourir) : remplacé par le bouton maquette */
.ordonnance-uppy--card :deep(.uppy-Dashboard-AddFiles-title),
.ordonnance-uppy--inline :deep(.uppy-Dashboard-AddFiles-title) {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.ordonnance-uppy--card :deep(.uppy-Dashboard-note),
.ordonnance-uppy--inline :deep(.uppy-Dashboard-note) {
    display: none;
}

.ordonnance-uppy--card :deep(.uppy-Dashboard-AddFiles-list) {
    display: none;
}

.ordonnance-uppy--inline :deep(.uppy-Dashboard-inner) {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    opacity: 0;
    pointer-events: none;
    border: none !important;
    box-shadow: none !important;
}

.ordonnance-uppy--inline :deep(.uppy-Dashboard-AddFiles-list) {
    display: none;
}
</style>
