<script setup lang="ts">
import type { Component } from 'vue';
import { moduleCardClass } from '@/lib/bengadokUi';

defineProps<{
    label: string;
    value: string | number;
    icon?: Component;
    valueClass?: string;
}>();
</script>

<template>
    <div :class="[moduleCardClass, 'p-4']">
        <div class="flex items-center gap-2 text-muted-foreground">
            <component :is="icon" v-if="icon" class="size-4 shrink-0" />
            <span class="text-xs font-medium uppercase tracking-wide">{{
                label
            }}</span>
        </div>
        <p
            class="mt-2 text-2xl font-bold tabular-nums"
            :class="valueClass"
        >
            {{ value }}
        </p>
    </div>
</template>
