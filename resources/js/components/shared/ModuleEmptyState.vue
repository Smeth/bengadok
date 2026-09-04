<script setup lang="ts">
import { moduleCardClass } from '@/lib/bengadokUi';

defineProps<{
    message: string;
    hint?: string;
}>();
</script>

<template>
    <div
        :class="[
            moduleCardClass,
            'px-6 py-12 text-center',
        ]"
    >
        <p class="text-sm font-medium text-foreground">{{ message }}</p>
        <p v-if="hint" class="mt-1 text-sm text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
