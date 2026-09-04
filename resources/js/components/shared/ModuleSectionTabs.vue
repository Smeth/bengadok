<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import {
    moduleTabActiveClass,
    moduleTabFocusClass,
    moduleTabInactiveClass,
} from '@/lib/bengadokUi';

export type ModuleSectionTab = {
    id: string;
    label: string;
    href: string;
    icon?: Component;
};

defineProps<{
    tabs: ModuleSectionTab[];
    active?: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <slot name="badge" />
        <div class="flex flex-wrap gap-2">
            <template v-for="tab in tabs" :key="tab.id">
                <span v-if="active === tab.id" :class="moduleTabActiveClass">
                    <component
                        :is="tab.icon"
                        v-if="tab.icon"
                        class="size-4 shrink-0"
                    />
                    {{ tab.label }}
                </span>
                <Link
                    v-else
                    :href="tab.href"
                    :class="[moduleTabInactiveClass, moduleTabFocusClass]"
                >
                    <component
                        :is="tab.icon"
                        v-if="tab.icon"
                        class="size-4 shrink-0"
                    />
                    {{ tab.label }}
                </Link>
            </template>
        </div>
    </div>
</template>
