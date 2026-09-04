<script setup lang="ts">
import { FileText, RefreshCw, X, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    url?: string;
    isPdf?: boolean;
    numero?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const zoom = ref(100);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            zoom.value = 100;
        }
    },
);

function zoomIn() {
    zoom.value = Math.min(zoom.value + 25, 200);
}

function zoomOut() {
    zoom.value = Math.max(zoom.value - 25, 50);
}

function resetZoom() {
    zoom.value = 100;
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0, 0, 0, 0.55)"
            @click.self="emit('close')"
        >
            <div
                class="relative flex max-h-[90vh] w-full max-w-[500px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-center gap-3 border-b border-gray-100 px-5 py-4"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF]"
                    >
                        <FileText class="size-5 text-[#459cd1]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[14px] font-extrabold text-gray-900">
                            Ordonnance — Commande {{ numero }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div
                    class="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2"
                >
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100"
                        @click="zoomOut"
                    >
                        <ZoomOut class="size-3.5" />
                    </button>
                    <span
                        class="min-w-[40px] text-center text-[12px] font-semibold text-gray-700"
                        >{{ zoom }}%</span
                    >
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100"
                        @click="zoomIn"
                    >
                        <ZoomIn class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100"
                        @click="resetZoom"
                    >
                        <RefreshCw class="size-3.5" />
                    </button>
                </div>
                <div
                    class="flex min-h-[250px] flex-1 items-start justify-center overflow-auto bg-gray-100 p-4"
                >
                    <iframe
                        v-if="url && isPdf"
                        :src="`${url}#toolbar=1`"
                        class="h-[min(70vh,520px)] w-full rounded-lg border-0 bg-white shadow"
                        title="Ordonnance PDF"
                    />
                    <img
                        v-else-if="url"
                        :src="url"
                        alt="Ordonnance"
                        class="rounded-lg shadow"
                        :style="{
                            transform: `scale(${zoom / 100})`,
                            transformOrigin: 'top center',
                            maxWidth: '100%',
                        }"
                    />
                    <div v-else class="m-auto text-center text-gray-400">
                        <FileText class="mx-auto mb-2 size-10 opacity-40" />
                        <p class="text-[13px]">Aucune ordonnance disponible</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
