<script setup lang="ts">
/**
 * Logo BengaDok officiel — sidebar-logo-benga.svg (seul et unique logo).
 */
defineProps<{
    variant?: 'full' | 'icon-only';
}>();
</script>

<template>
    <img
        src="/images/figma-assets/sidebar-logo-benga.svg"
        alt="BengaDok"
        class="h-[80px] w-auto max-w-[220px] object-contain md:h-[100px] md:max-w-[260px]"
    />
</template>
