<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string;
    hint?: string;
    icon: Component;
    accent: 'green' | 'blue' | 'amber';
}>();

const accentClasses = {
    green: 'from-emerald-500/15 to-emerald-600/5 text-emerald-600 ring-emerald-500/20',
    blue: 'from-sky-500/15 to-sky-600/5 text-sky-600 ring-sky-500/20',
    amber: 'from-amber-500/15 to-amber-600/5 text-amber-600 ring-amber-500/20',
};
</script>

<template>
    <article
        class="pharmacy-dash-card group relative overflow-hidden p-5 transition-shadow hover:shadow-lg sm:p-6"
    >
        <div
            class="pointer-events-none absolute -right-6 -top-6 size-28 rounded-full bg-gradient-to-br opacity-60 blur-2xl transition-opacity group-hover:opacity-80"
            :class="{
                'from-emerald-400/30 to-transparent': accent === 'green',
                'from-sky-400/30 to-transparent': accent === 'blue',
                'from-amber-400/30 to-transparent': accent === 'amber',
            }"
        />
        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                    {{ label }}
                </p>
                <p
                    class="mt-2 text-[clamp(1.5rem,3.5vw,2.25rem)] font-bold tabular-nums leading-none tracking-tight text-slate-900"
                >
                    {{ value }}
                </p>
                <p
                    v-if="hint"
                    class="mt-2 text-xs leading-relaxed text-slate-500"
                >
                    {{ hint }}
                </p>
            </div>
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br ring-1 ring-inset"
                :class="accentClasses[accent]"
            >
                <component :is="icon" class="size-5" stroke-width="2" />
            </div>
        </div>
    </article>
</template>
