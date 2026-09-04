<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    moduleCounterDefaultClass,
    moduleFilterPanelClass,
    modulePrimaryButtonClass,
    moduleSearchInputClass,
} from '@/lib/bengadokUi';

withDefaults(
    defineProps<{
        search?: string;
        placeholder?: string;
        showSubmit?: boolean;
        submitLabel?: string;
        counter?: number | null;
        counterIcon?: Component;
        counterClass?: string;
        showSearch?: boolean;
    }>(),
    {
        search: '',
        placeholder: 'Rechercher…',
        showSubmit: false,
        submitLabel: 'Rechercher',
        counter: null,
        counterClass: moduleCounterDefaultClass,
        showSearch: true,
    },
);

const emit = defineEmits<{
    'update:search': [value: string];
    submit: [];
}>();
</script>

<template>
    <div :class="moduleFilterPanelClass">
        <form
            class="flex flex-wrap items-center gap-3 sm:gap-4"
            @submit.prevent="emit('submit')"
        >
            <div
                v-if="showSearch"
                class="relative min-w-[220px] flex-1"
            >
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500"
                />
                <Input
                    :model-value="search"
                    type="search"
                    :placeholder="placeholder"
                    autocomplete="off"
                    :class="moduleSearchInputClass"
                    @update:model-value="
                        emit('update:search', String($event ?? ''))
                    "
                />
            </div>

            <slot />

            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>

            <Button
                v-if="showSubmit"
                type="submit"
                :class="modulePrimaryButtonClass"
            >
                {{ submitLabel }}
            </Button>

            <div
                v-if="counter != null"
                class="ml-auto flex items-center gap-2 rounded-lg px-3 py-1.5 text-white"
                :class="counterClass"
            >
                <component
                    :is="counterIcon"
                    v-if="counterIcon"
                    class="size-4"
                />
                <span class="font-semibold tabular-nums">{{ counter }}</span>
            </div>
        </form>
    </div>
</template>
