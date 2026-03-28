<script setup lang="ts">
withDefaults(
    defineProps<{
        layout?: 'horizontal' | 'vertical';
        iconOnly?: boolean;
    }>(),
    {
        layout: 'vertical',
        iconOnly: false,
    },
);
</script>

<template>
    <!-- Logo BengaDok Figma : image officielle sidebar-logo-benga.svg -->
    <div
        class="group-data-[collapsible=icon]:gap-0 flex items-center justify-center"
        :class="layout === 'vertical' ? 'flex-col gap-2' : 'flex-row gap-2'"
    >
        <img
            src="/images/figma-assets/sidebar-logo-benga.svg"
            alt="BengaDok"
            class="h-[90px] w-auto max-w-[210px] shrink-0 object-contain object-center group-data-[collapsible=icon]:hidden"
        />
        <!-- Icône réduite quand sidebar collapsed -->
        <div class="hidden shrink-0 items-center justify-center rounded-full bg-[#3995D2] size-8 group-data-[collapsible=icon]:flex">
            <svg class="size-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 4v16M4 12h16" />
            </svg>
        </div>
    </div>
</template>
