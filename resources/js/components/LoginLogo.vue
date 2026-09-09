<script setup lang="ts">
/**
 * Logo BengaDok officiel — sidebar-logo-benga.png
 */
withDefaults(
    defineProps<{
        size?: 'default' | 'compact';
    }>(),
    {
        size: 'default',
    },
);
</script>

<template>
    <img
        src="/images/figma-assets/sidebar-logo-benga.png"
        alt="BengaDok"
        :class="
            size === 'compact'
                ? 'h-10 w-auto max-w-[140px] object-contain sm:h-11 sm:max-w-[155px]'
                : 'h-[100px] w-auto max-w-[275px] object-contain md:h-[125px] md:max-w-[325px]'
        "
    />
</template>
