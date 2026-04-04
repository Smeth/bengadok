<script setup lang="ts">
withDefaults(
    defineProps<{
        /** Afficher la barre et le libellé */
        visible: boolean;
        /** Texte sous la barre */
        label?: string;
    }>(),
    {
        label: 'Analyse de l’ordonnance en cours…',
    },
);
</script>

<template>
    <div v-if="visible" class="space-y-1.5">
        <p class="text-xs font-medium text-sky-800">{{ label }}</p>
        <div
            class="relative h-1.5 w-full overflow-hidden rounded-full bg-sky-100"
            role="progressbar"
            aria-busy="true"
            aria-label="Analyse en cours"
        >
            <div class="ordonnance-analysis-progress-indeterminate" />
        </div>
    </div>
</template>

<style scoped>
.ordonnance-analysis-progress-indeterminate {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 35%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #38bdf8, #0284c7, #38bdf8);
    animation: ordonnance-analysis-slide 1.15s ease-in-out infinite;
}

@keyframes ordonnance-analysis-slide {
    0% {
        left: -35%;
    }
    100% {
        left: 100%;
    }
}
</style>
