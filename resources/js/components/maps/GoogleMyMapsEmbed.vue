<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        embedUrl: string;
        viewerUrl?: string | null;
        title?: string;
        height?: string;
    }>(),
    {
        title: 'Carte BengaDok',
        height: '550px',
    },
);

const iframeStyle = computed(() => ({
    height: props.height,
}));
</script>

<template>
    <div
        class="relative overflow-hidden rounded-[20px] border-[6px] border-white/40 shadow-md"
        :style="{ minHeight: height }"
    >
        <iframe
            :src="embedUrl"
            :title="title"
            class="w-full border-0"
            :style="iframeStyle"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
        />
        <a
            v-if="viewerUrl"
            :href="viewerUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="absolute right-4 top-4 z-10 rounded-lg border border-gray-200 bg-white/95 px-3 py-2 text-xs font-semibold text-[#3995D2] shadow-md backdrop-blur-sm transition hover:bg-white"
        >
            Ouvrir dans Google Maps
        </a>
    </div>
</template>
