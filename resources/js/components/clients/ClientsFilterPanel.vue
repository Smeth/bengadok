<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

withDefaults(
    defineProps<{
        search?: string;
        placeholder?: string;
        showSubmit?: boolean;
        submitLabel?: string;
        counter?: number | null;
        counterIcon?: Component;
        counterClass?: string;
    }>(),
    {
        search: '',
        placeholder: 'Rechercher…',
        showSubmit: false,
        submitLabel: 'Rechercher',
        counter: null,
        counterClass: 'bg-[#459cd1]',
    },
);

const emit = defineEmits<{
    'update:search': [value: string];
    submit: [];
}>();
</script>

<template>
    <div
        class="rounded-xl border border-white/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.96] sm:p-5"
    >
        <form
            class="flex flex-wrap items-center gap-3 sm:gap-4"
            @submit.prevent="emit('submit')"
        >
            <div class="relative min-w-[220px] flex-1">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500"
                />
                <Input
                    :model-value="search"
                    type="search"
                    :placeholder="placeholder"
                    autocomplete="off"
                    class="h-10 w-full rounded-full border-0 bg-white pl-10 pr-4 text-sm text-slate-900 shadow-sm ring-1 ring-black/10 placeholder:text-slate-500 focus-visible:ring-2 focus-visible:ring-[#459cd1]/40"
                    @update:model-value="emit('update:search', String($event ?? ''))"
                />
            </div>

            <slot />

            <Button
                v-if="showSubmit"
                type="submit"
                class="rounded-lg bg-[#459cd1] px-5 text-white hover:bg-[#3a87b8]"
            >
                {{ submitLabel }}
            </Button>

            <div
                v-if="counter != null"
                class="ml-auto flex items-center gap-2 rounded-lg px-3 py-1.5 text-white"
                :class="counterClass"
            >
                <component :is="counterIcon" v-if="counterIcon" class="size-4" />
                <span class="font-semibold tabular-nums">{{ counter }}</span>
            </div>
        </form>
    </div>
</template>
