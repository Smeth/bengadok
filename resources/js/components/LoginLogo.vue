<script setup lang="ts">
/**
 * Logo BengaDok officiel — sidebar-logo-benga.png
 */
defineProps<{
    variant?: 'full' | 'icon-only';
}>();
</script>

<template>
    <img
        src="/images/figma-assets/sidebar-logo-benga.png"
        alt="BengaDok"
        class="h-[100px] w-auto max-w-[275px] object-contain md:h-[125px] md:max-w-[325px]"
    />
</template>
