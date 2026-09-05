<script setup lang="ts">
defineProps<{
    onglet: string;
    stats: {
        nouvelles: number;
        en_attente: number;
        a_preparer: number;
        livrees: number;
    };
    canViewHistorique?: boolean;
}>();

const emit = defineEmits<{
    change: [onglet: string];
}>();
</script>

<template>
    <div
        class="pharmacy-card mb-4 flex flex-wrap items-stretch gap-2 p-2 sm:gap-2 sm:p-3"
    >
        <button
            type="button"
            class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:min-w-0 sm:flex-none sm:px-3"
            :class="
                onglet === 'nouvelles'
                    ? 'bg-[#459cd1] text-white ring-2 ring-white/40'
                    : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
            "
            @click="emit('change', 'nouvelles')"
        >
            <span
                class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                :class="
                    onglet === 'nouvelles'
                        ? 'bg-white/25'
                        : 'bg-[#459cd1]/15 text-[#459cd1]'
                "
            >
                {{ stats.nouvelles }}
            </span>
            <span class="hidden sm:inline">Nouvelles</span>
            <span class="sm:hidden">Nouv.</span>
        </button>

        <button
            type="button"
            class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
            :class="
                onglet === 'en_attente'
                    ? 'bg-indigo-600 text-white ring-2 ring-white/40'
                    : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
            "
            @click="emit('change', 'en_attente')"
        >
            <span
                class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                :class="
                    onglet === 'en_attente'
                        ? 'bg-white/25'
                        : 'bg-indigo-100 text-indigo-700'
                "
            >
                {{ stats.en_attente }}
            </span>
            <span class="hidden sm:inline">En attente</span>
            <span class="sm:hidden">Att.</span>
        </button>

        <button
            type="button"
            class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
            :class="
                onglet === 'a_preparer'
                    ? 'bg-amber-500 text-white ring-2 ring-white/40'
                    : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
            "
            @click="emit('change', 'a_preparer')"
        >
            <span
                class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                :class="
                    onglet === 'a_preparer'
                        ? 'bg-white/25'
                        : 'bg-amber-100 text-amber-800'
                "
            >
                {{ stats.a_preparer }}
            </span>
            <span class="hidden md:inline">À préparer</span>
            <span class="md:hidden">Prép.</span>
        </button>

        <button
            v-if="canViewHistorique !== false"
            type="button"
            class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
            :class="
                onglet === 'livrees'
                    ? 'bg-[#5bb66e] text-white ring-2 ring-white/40'
                    : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
            "
            @click="emit('change', 'livrees')"
        >
            <span
                class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                :class="
                    onglet === 'livrees'
                        ? 'bg-white/25'
                        : 'bg-emerald-100 text-emerald-800'
                "
            >
                {{ stats.livrees }}
            </span>
            Retirées
        </button>
    </div>
</template>
