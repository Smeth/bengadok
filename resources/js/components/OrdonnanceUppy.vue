<script setup lang="ts">
import Uppy from '@uppy/core';
import fr_FR from '@uppy/locales/lib/fr_FR.js';
import type { UppyFile } from '@uppy/utils';
import Dashboard from '@uppy/vue/dashboard';
import { ClipboardList, FileText, Paperclip, Pill, X } from 'lucide-vue-next';
import {
    computed,
    markRaw,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    toRaw,
    watch,
} from 'vue';
import '@uppy/core/css/style.css';
import '@uppy/dashboard/css/style.css';
import { ordonnanceFilesFromValue } from '@/lib/commandeCreationFields';
import { moduleTabFocusClass } from '@/lib/bengadokUi';
import { showGlobalErrorToast } from '@/lib/globalToast';
import { getUploadLimits, uploadMaxSizeNote, validateFileSize } from '@/lib/uploadLimits';

const uploadLimits = getUploadLimits();

const ALLOWED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
];
const ACCEPT_ATTR = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,.pdf';
const MAX_SIZE = uploadLimits.max_bytes;
const MAX_FILES = 10;

type InlinePreview = {
    file: File;
    url: string;
    isPdf: boolean;
};

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
    /** Évite la fermeture de la modale quand le sélecteur de fichiers natif s’ouvre */
    'file-picker-active': [active: boolean];
}>();

const uppy = shallowRef<InstanceType<typeof Uppy> | null>(null);
const ready = ref(false);
const cardWrapRef = ref<HTMLElement | null>(null);
const nativeInputRef = ref<HTMLInputElement | null>(null);
const inlinePreviews = ref<InlinePreview[]>([]);
const nativeInputId = `ordonnance-files-${globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`}`;

const isInline = computed(
    () => props.variant === 'inline' || (props.variant === 'card' && props.multiple),
);

const dashboardProps = computed(() => ({
    proudlyDisplayPoweredByUppy: false,
    hideUploadButton: true,
    disableStatusBar: true,
    height: props.variant === 'card' ? 132 : 220,
    note:
        props.variant === 'card'
            ? ''
            : uploadMaxSizeNote('JPG, PNG, GIF, WebP ou PDF'),
}));

function isPdfFile(file: File): boolean {
    return (
        file.type === 'application/pdf' ||
        file.name.toLowerCase().endsWith('.pdf')
    );
}

function isAllowedType(file: File): boolean {
    if (ALLOWED_TYPES.includes(file.type)) {
        return true;
    }
    return isPdfFile(file);
}

function fileKey(file: File): string {
    const raw = toRaw(file);
    return `${raw.name}:${raw.size}:${raw.lastModified}`;
}

function previewKeysFromFiles(files: File[]): string {
    return files
        .map((file) => fileKey(file))
        .sort()
        .join('\0');
}

function filesMatchInlinePreviews(files: File[]): boolean {
    return (
        previewKeysFromFiles(files) ===
        previewKeysFromFiles(inlinePreviews.value.map((item) => item.file))
    );
}

/** Dernière sélection émise — évite d’effacer l’aperçu si le parent n’a pas encore synchronisé le v-model */
const lastEmittedPreviewKeys = ref<string | null>(null);

function setFilePickerActive(active: boolean) {
    emit('file-picker-active', active);
}

function emitFiles(files: File[]) {
    const rawFiles = files.map((file) => markRaw(toRaw(file)));
    lastEmittedPreviewKeys.value = previewKeysFromFiles(rawFiles);
    if (props.multiple) {
        emit('update:modelValue', rawFiles);
        return;
    }
    emit('update:modelValue', rawFiles[0] ?? null);
}

function revokeInlinePreviews() {
    inlinePreviews.value.forEach((item) => URL.revokeObjectURL(item.url));
}

function rebuildInlinePreviews(files: File[]) {
    revokeInlinePreviews();
    inlinePreviews.value = files.map((file) => {
        const raw = markRaw(toRaw(file));
        return {
            file: raw,
            url: URL.createObjectURL(raw),
            isPdf: isPdfFile(raw),
        };
    });
}

function setInlineFiles(files: File[]) {
    rebuildInlinePreviews(files);
    emitFiles(files);
}

function syncInlineFromModelValue(
    value: File | File[] | null,
    previousValue: File | File[] | null | undefined,
) {
    const files = ordonnanceFilesFromValue(value);
    const previousFiles = ordonnanceFilesFromValue(previousValue);

    if (files.length === 0) {
        if (inlinePreviews.value.length === 0) {
            lastEmittedPreviewKeys.value = null;
            return;
        }
        const localKeys = previewKeysFromFiles(
            inlinePreviews.value.map((item) => item.file),
        );
        if (
            previousFiles.length === 0 &&
            lastEmittedPreviewKeys.value === localKeys
        ) {
            return;
        }
        revokeInlinePreviews();
        inlinePreviews.value = [];
        lastEmittedPreviewKeys.value = null;
        return;
    }

    lastEmittedPreviewKeys.value = null;
    if (!filesMatchInlinePreviews(files)) {
        rebuildInlinePreviews(files);
    }
}

function addNativeFiles(list: FileList | File[]) {
    const incoming = Array.from(list);
    const current = inlinePreviews.value.map((item) => item.file);
    const existing = new Set(current.map(fileKey));
    const next = [...current];

    for (const file of incoming) {
        if (next.length >= (props.multiple ? MAX_FILES : 1)) {
            showGlobalErrorToast(
                `Vous pouvez joindre au plus ${props.multiple ? MAX_FILES : 1} fichier(s).`,
            );
            break;
        }
        if (!isAllowedType(file)) {
            showGlobalErrorToast(
                `Format non accepté : ${file.name}. Utilisez JPG, PNG, GIF, WebP ou PDF.`,
            );
            continue;
        }
        const sizeError = validateFileSize(file);
        if (sizeError) {
            showGlobalErrorToast(sizeError);
            continue;
        }
        if (existing.has(fileKey(file))) {
            continue;
        }
        existing.add(fileKey(file));
        next.push(file);
    }

    if (!props.multiple && next.length > 1) {
        setInlineFiles(next.slice(-1));
        return;
    }

    setInlineFiles(next);
}

function onNativeInputChange(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        addNativeFiles(input.files);
    }
    input.value = '';
    setFilePickerActive(false);
}

function openNativePicker() {
    setFilePickerActive(true);
    nativeInputRef.value?.click();
    const onWindowFocus = () => {
        window.removeEventListener('focus', onWindowFocus);
        window.setTimeout(() => setFilePickerActive(false), 300);
    };
    window.addEventListener('focus', onWindowFocus);
}

function removeInlineFile(index: number) {
    const next = inlinePreviews.value
        .filter((_, i) => i !== index)
        .map((item) => item.file);
    setInlineFiles(next);
}

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

onMounted(() => {
    if (isInline.value) {
        syncInlineFromModelValue(props.modelValue, undefined);
        return;
    }

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
    revokeInlinePreviews();
    uppy.value?.destroy();
    uppy.value = null;
    ready.value = false;
});

watch(
    () => props.modelValue,
    (value, previousValue) => {
        if (isInline.value) {
            syncInlineFromModelValue(value, previousValue);
            return;
        }
        if (ordonnanceFilesFromValue(value).length > 0) return;
        clearFiles();
    },
    { flush: 'post' },
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
            v-if="isInline"
            class="rounded-[10px] border-2 border-dashed border-[#d1d5db] bg-white px-3 py-2"
        >
            <input
                :id="nativeInputId"
                ref="nativeInputRef"
                type="file"
                class="sr-only"
                :accept="ACCEPT_ATTR"
                :multiple="multiple"
                @change="onNativeInputChange"
            />
            <div class="flex flex-wrap items-start gap-3">
                <button
                    type="button"
                    :class="[
                        'inline-flex h-24 shrink-0 cursor-pointer items-center gap-1.5 self-center rounded-lg border border-[#cbd5e1] bg-[#f8fafc] px-3 text-sm font-semibold text-[#475569] hover:bg-[#f1f5f9]',
                        moduleTabFocusClass,
                    ]"
                    @click.stop.prevent="openNativePicker"
                >
                    <Paperclip class="size-4" />
                    Ajouter
                </button>
                <p
                    v-if="inlinePreviews.length === 0"
                    class="self-center text-xs text-[#94a3b8]"
                >
                    JPG, PNG, GIF, WebP ou PDF — plusieurs fichiers
                </p>
                <p
                    v-else
                    class="w-full text-xs font-medium text-[#64748b]"
                >
                    {{ inlinePreviews.length }} fichier{{
                        inlinePreviews.length > 1 ? 's' : ''
                    }}
                    — aperçu ci-dessous
                </p>
                <div
                    v-for="(item, index) in inlinePreviews"
                    :key="`${fileKey(item.file)}-${index}`"
                    class="relative h-24 w-24 shrink-0 overflow-hidden rounded-lg border border-[#e2e8f0] bg-[#f8fafc] shadow-sm"
                >
                    <div
                        v-if="item.isPdf"
                        class="flex h-full w-full flex-col items-center justify-center gap-1 bg-[#eef2ff] text-[#475569]"
                    >
                        <FileText class="size-7" />
                        <span class="px-1 text-[9px] font-bold">PDF</span>
                    </div>
                    <img
                        v-else
                        :src="item.url"
                        :alt="item.file.name"
                        class="h-full w-full object-cover"
                    />
                    <div
                        class="pointer-events-none absolute inset-x-0 bottom-0 truncate bg-black/55 px-1 py-0.5 text-[10px] font-medium text-white"
                    >
                        {{ item.file.name }}
                    </div>
                    <button
                        type="button"
                        class="absolute right-0.5 top-0.5 inline-flex size-5 items-center justify-center rounded-full bg-black/70 text-white hover:bg-[#dc3545]"
                        :aria-label="`Retirer ${item.file.name}`"
                        @click="removeInlineFile(index)"
                    >
                        <X class="size-3" />
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else
            ref="cardWrapRef"
            class="relative"
            :class="
                variant === 'card'
                    ? 'min-h-[132px] overflow-hidden rounded-[10px] border-2 border-dashed border-[#d1d5db] bg-white'
                    : ''
            "
        >
            <div
                v-if="variant === 'card' && !modelValue"
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

.ordonnance-uppy--card :deep(.uppy-Dashboard-AddFiles-title) {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.ordonnance-uppy--card :deep(.uppy-Dashboard-note) {
    display: none;
}

.ordonnance-uppy--card :deep(.uppy-Dashboard-AddFiles-list) {
    display: none;
}
</style>
