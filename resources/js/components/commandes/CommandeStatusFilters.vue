<script setup lang="ts">
import { moduleFilterPanelClass, modulePrimaryBgClass, modulePrimaryBorderClass, modulePrimaryTextClass } from '@/lib/bengadokUi';
import { commandeStatutFilterStyle, type CommandeStatutConfig } from '@/types';

defineProps<{
    statuts: CommandeStatutConfig[];
    stats: Record<string, number>;
    activeStatus?: string;
}>();

const emit = defineEmits<{
    filter: [status: string];
}>();
</script>

<template>
    <div :class="moduleFilterPanelClass">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <button
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-all"
                :class="
                    !activeStatus
                        ? `${modulePrimaryBgClass} text-white ring-2 ring-[#459cd1]/30`
                        : `border ${modulePrimaryBorderClass} bg-white ${modulePrimaryTextClass} hover:bg-[#459cd1]/5 dark:bg-card dark:hover:bg-[#459cd1]/10`
                "
                @click="emit('filter', '')"
            >
                Toutes
            </button>
            <button
                v-for="s in statuts"
                :key="s.key"
                type="button"
                class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-all whitespace-nowrap hover:opacity-90"
                :style="commandeStatutFilterStyle(s, activeStatus === s.key)"
                @click="emit('filter', activeStatus === s.key ? '' : s.key)"
            >
                {{ s.label }} ({{ stats[s.statsKey] ?? 0 }})
            </button>
        </div>
    </div>
</template>
