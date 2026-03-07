<script setup lang="ts">
/**
 * Logo BengaDok officiel (image 1).
 * Icône : cercle, croix médicale teal #34B0C7, combiné téléphone bleu foncé #2C4685.
 * Texte : Benga #2C4685, Dok #5BB034, contour pilule #34B0C7.
 * Slogan : gris #AAAAAA.
 */
defineProps<{
    variant?: 'full' | 'icon-only';
}>();
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <!-- Icône : cercle + croix médicale teal + combiné bleu foncé -->
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 80 80"
            class="size-16 shrink-0 md:size-20"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="bengadok-circle-bg" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#3d9bad" />
                    <stop offset="100%" stop-color="#34B0C7" />
                </linearGradient>
            </defs>
            <!-- Cercle de fond (teal légèrement plus foncé que la croix) -->
            <circle cx="40" cy="40" r="38" fill="url(#bengadok-circle-bg)" />
            <!-- Combiné téléphone (bleu foncé #2C4685), courbes derrière la croix -->
            <g transform="rotate(-22 40 40)" fill="none" stroke="#2C4685" stroke-width="5" stroke-linecap="round">
                <path d="M30 30 Q30 50 42 58" />
                <path d="M50 30 Q50 50 38 58" />
            </g>
            <!-- Croix médicale (teal #34B0C7), rectangles arrondis -->
            <rect x="35" y="22" width="10" height="36" rx="2" fill="#34B0C7" />
            <rect x="22" y="35" width="36" height="10" rx="2" fill="#34B0C7" />
        </svg>
        <template v-if="variant !== 'icon-only'">
            <!-- Benga bleu foncé, Dok vert, contour pilule teal -->
            <div
                class="inline-flex items-center gap-0.5 rounded-full px-4 py-1"
                style="border: 2px solid #34B0C7;"
            >
                <span class="text-xl font-semibold tracking-tight text-[#2C4685] md:text-2xl">Benga</span>
                <span class="text-xl font-semibold tracking-tight text-[#5BB034] md:text-2xl">Dok</span>
            </div>
            <p class="text-xs text-[#AAAAAA] md:text-sm">
                Votre centre d'appel médical
            </p>
        </template>
    </div>
</template>
