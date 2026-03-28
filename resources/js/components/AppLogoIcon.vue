<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Logo BengaDok officiel : sidebar-logo-benga.svg (seul et unique logo) -->
    <img
        src="/images/figma-assets/sidebar-logo-benga.svg"
        alt="BengaDok"
        :class="className"
        v-bind="$attrs"
        class="h-auto w-auto object-contain"
    />
</template>
