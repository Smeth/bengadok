<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        file: File | null;
        maxHeight?: string;
    }>(),
    { maxHeight: '11rem' },
);

const objectUrl = ref<string | null>(null);

watch(
    () => props.file,
    (f) => {
        if (objectUrl.value) {
            URL.revokeObjectURL(objectUrl.value);
            objectUrl.value = null;
        }
        if (f) objectUrl.value = URL.createObjectURL(f);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
});

const isPdf = computed(() => {
    const f = props.file;
    if (!f) return false;
    if (f.type === 'application/pdf') return true;
    return f.name.toLowerCase().endsWith('.pdf');
});
</script>

<template>
    <div v-if="file && objectUrl" class="w-full overflow-hidden rounded-lg border bg-muted/20">
        <iframe
            v-if="isPdf"
            :src="`${objectUrl}#toolbar=0`"
            class="w-full border-0"
            :style="{ height: maxHeight }"
            title="Aperçu PDF"
        />
        <div v-else class="flex justify-center" :style="{ maxHeight }">
            <img
                :src="objectUrl"
                alt="Aperçu ordonnance"
                class="max-h-full w-auto max-w-full object-contain"
            />
        </div>
    </div>
</template>
