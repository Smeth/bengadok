<script setup lang="ts">
import Uppy from '@uppy/core';
import fr_FR from '@uppy/locales/lib/fr_FR.js';
import type { UppyFile } from '@uppy/utils';
import Dashboard from '@uppy/vue/dashboard';
import { ClipboardList, Pill } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
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
        /** Affiche l’info analyse OCR / règles (flux back-office) */
        showAnalysisNotice?: boolean;
        /** Remplace le texte par défaut de la notice (si showAnalysisNotice) */
        analysisNotice?: string;
        /**
         * `card` : zone en pointillés, libellé court « Ajouter une ordonnance » (ex. modale commande).
         */
        variant?: 'default' | 'card';
    }>(),
    {
        label: '',
        showAnalysisNotice: false,
        analysisNotice: '',
        variant: 'default',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: File | null];
}>();

const uppy = shallowRef<InstanceType<typeof Uppy> | null>(null);
const ready = ref(false);
const cardWrapRef = ref<HTMLElement | null>(null);

const dashboardProps = computed(() => ({
    proudlyDisplayPoweredByUppy: false,
    hideUploadButton: true,
    disableStatusBar: true,
    height: props.variant === 'card' ? 132 : 220,
    note: props.variant === 'card' ? '' : 'JPG, PNG, GIF, WebP ou PDF — max. 10 Mo.',
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
    <div
        class="ordonnance-uppy"
        :class="{ 'ordonnance-uppy--card': variant === 'card' }"
    >
        <p
            v-if="label"
            class="mb-2 text-sm font-medium text-muted-foreground"
        >
            {{ label }}
        </p>
        <p
            v-if="showAnalysisNotice && variant !== 'card'"
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
                    class="pointer-events-auto flex cursor-pointer flex-col items-center gap-2 rounded-lg border-0 bg-transparent p-3 text-center outline-none focus-visible:ring-2 focus-visible:ring-[#3B82F6] focus-visible:ring-offset-2"
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
.ordonnance-uppy:not(.ordonnance-uppy--card) :deep(.uppy-Dashboard-inner) {
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
