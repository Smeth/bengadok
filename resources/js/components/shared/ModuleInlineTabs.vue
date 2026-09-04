<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import {
    moduleTabActiveClass,
    moduleTabFocusClass,
    moduleTabInactiveClass,
    moduleTabInactiveOnLightClass,
} from '@/lib/bengadokUi';

export type ModuleInlineTab = {
    id: string;
    label: string;
    icon?: Component;
};

const props = withDefaults(
    defineProps<{
        tabs: ModuleInlineTab[];
        modelValue: string;
        variant?: 'gradient' | 'light';
    }>(),
    {
        variant: 'gradient',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const inactiveTabClass = computed(() =>
    props.variant === 'light'
        ? moduleTabInactiveOnLightClass
        : moduleTabInactiveClass,
);

function select(id: string) {
    emit('update:modelValue', id);
}
</script>

<template>
    <div
        class="flex flex-wrap gap-2"
        :class="variant === 'light' ? 'rounded-xl bg-muted/50 p-1' : ''"
    >
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="[
                modelValue === tab.id
                    ? moduleTabActiveClass
                    : inactiveTabClass,
                moduleTabFocusClass,
            ]"
            @click="select(tab.id)"
        >
            <component
                :is="tab.icon"
                v-if="tab.icon"
                class="size-4 shrink-0"
            />
            {{ tab.label }}
        </button>
    </div>
</template>
