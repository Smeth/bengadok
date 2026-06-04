<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        points: { label: string; valeur: number }[];
        color?: string;
        trackColor?: string;
        barHeight?: number;
        scaleMax?: number;
        emptyMessage?: string;
        showYAxis?: boolean;
    }>(),
    {
        color: '#5bb66e',
        trackColor: 'rgba(92,89,89,0.17)',
        barHeight: 200,
        emptyMessage: 'Aucune donnée sur cette période',
        showYAxis: false,
    },
);

const maxVal = computed(() => {
    if (props.scaleMax && props.scaleMax > 0) {
        return props.scaleMax;
    }
    return Math.max(1, ...props.points.map((p) => p.valeur));
});

const yTicks = computed(() => {
    if (!props.showYAxis) {
        return [];
    }
    const niceMax = Math.max(
        5,
        props.scaleMax ?? Math.ceil(maxVal.value / 5) * 5,
    );
    const step = niceMax <= 25 ? 5 : Math.ceil(niceMax / 5 / 5) * 5;
    const ticks: number[] = [];
    for (let v = 0; v <= niceMax; v += step) {
        ticks.push(v);
    }
    if (ticks[ticks.length - 1]! < niceMax) {
        ticks.push(niceMax);
    }
    return ticks.slice(-6).reverse();
});

function formatTick(n: number): string {
    if (n >= 1000) {
        return `${Math.round(n / 1000)}K`;
    }
    return String(n);
}
</script>

<template>
    <div class="flex gap-2 sm:gap-3">
        <div
            v-if="showYAxis && yTicks.length"
            class="flex w-8 shrink-0 flex-col justify-between py-1 text-right text-xs font-medium text-black sm:w-9 sm:text-sm"
            :style="{ height: `${barHeight}px` }"
        >
            <span v-for="tick in yTicks" :key="tick">{{
                formatTick(tick)
            }}</span>
        </div>
        <div
            class="flex min-w-0 flex-1 items-end justify-between gap-1 overflow-x-auto pb-1 sm:gap-2"
            :style="{ minHeight: `${barHeight}px` }"
        >
            <template v-for="(point, i) in points" :key="i">
                <div
                    class="flex min-w-[32px] flex-1 flex-col items-center gap-2 sm:min-w-[40px]"
                >
                    <div
                        class="flex w-full max-w-[72px] flex-col justify-end rounded-[50px] p-1 sm:max-w-[88px]"
                        :style="{
                            height: `${barHeight - 20}px`,
                            backgroundColor: trackColor,
                        }"
                    >
                        <div
                            class="w-full rounded-[50px] transition-all"
                            :style="{
                                height: `${Math.max(2, (point.valeur / maxVal) * 100)}%`,
                                minHeight: point.valeur > 0 ? '8px' : '0',
                                backgroundColor: color,
                            }"
                        />
                    </div>
                    <span
                        class="text-[11px] font-medium text-black sm:text-xs"
                        >{{ point.label }}</span
                    >
                </div>
            </template>
            <p
                v-if="!points.length"
                class="flex w-full items-center justify-center py-10 text-center text-sm text-black/60"
            >
                {{ emptyMessage }}
            </p>
        </div>
    </div>
</template>
