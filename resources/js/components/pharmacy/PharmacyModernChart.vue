<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        points: { label: string; valeur: number }[];
        variant?: 'green' | 'blue' | 'amber';
        height?: number;
        unit?: string;
        emptyMessage?: string;
    }>(),
    {
        variant: 'green',
        height: 200,
        unit: '',
        emptyMessage: 'Aucune donnée sur cette période',
    },
);

const maxVal = computed(() => Math.max(1, ...props.points.map((p) => p.valeur)));

const gradient = computed(() => {
    if (props.variant === 'blue') {
        return 'linear-gradient(180deg, #60b4f0 0%, #3995d2 100%)';
    }
    if (props.variant === 'amber') {
        return 'linear-gradient(180deg, #fcd34d 0%, #f59e0b 100%)';
    }
    return 'linear-gradient(180deg, #7dd99a 0%, #5bb66e 100%)';
});

const barShadow = computed(() => {
    if (props.variant === 'blue') {
        return '0 4px 14px rgba(57, 149, 210, 0.28)';
    }
    if (props.variant === 'amber') {
        return '0 4px 14px rgba(245, 158, 11, 0.28)';
    }
    return '0 4px 14px rgba(91, 182, 110, 0.35)';
});

const trackBg = 'rgba(15, 23, 42, 0.06)';

function barTitle(point: { label: string; valeur: number }): string {
    const u = props.unit ? ` ${props.unit}` : '';
    return `${point.label} : ${point.valeur.toLocaleString('fr-FR')}${u}`;
}
</script>

<template>
    <div
        v-if="points.length"
        class="relative"
        :style="{ minHeight: `${height + 28}px` }"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 flex flex-col justify-between opacity-40"
            :style="{ height: `${height}px` }"
        >
            <div
                v-for="n in 4"
                :key="n"
                class="border-t border-dashed border-slate-200"
            />
        </div>
        <div
            class="relative flex items-end justify-between gap-1 overflow-x-auto pb-1 sm:gap-2"
            :style="{ height: `${height}px` }"
        >
            <div
                v-for="(point, i) in points"
                :key="i"
                class="group flex min-w-[28px] flex-1 flex-col items-center gap-2 sm:min-w-[36px]"
            >
                <div
                    class="flex w-full max-w-[56px] flex-col justify-end rounded-t-xl transition-transform duration-300 group-hover:scale-[1.02] sm:max-w-[64px]"
                    :style="{
                        height: `${height - 8}px`,
                        backgroundColor: trackBg,
                    }"
                    :title="barTitle(point)"
                >
                    <div
                        class="w-full rounded-t-xl shadow-sm transition-all duration-500 ease-out"
                        :style="{
                            height: `${Math.max(point.valeur > 0 ? 6 : 0, (point.valeur / maxVal) * 100)}%`,
                            background: gradient,
                            boxShadow:
                                point.valeur > 0 ? barShadow : 'none',
                        }"
                    />
                </div>
                <span
                    class="text-[10px] font-medium text-slate-500 sm:text-xs"
                    >{{ point.label }}</span
                >
            </div>
        </div>
    </div>
    <div
        v-else
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200/80 bg-slate-50/50 py-14"
    >
        <div
            class="mb-3 size-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400"
        >
            <svg
                class="size-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"
                />
            </svg>
        </div>
        <p class="text-sm font-medium text-slate-500">{{ emptyMessage }}</p>
    </div>
</template>
