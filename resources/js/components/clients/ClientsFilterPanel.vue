<script setup lang="ts">
import ModuleFilterPanel from '@/components/shared/ModuleFilterPanel.vue';
</script>

<template>
    <ModuleFilterPanel v-bind="$attrs">
        <slot />
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </ModuleFilterPanel>
</template>
