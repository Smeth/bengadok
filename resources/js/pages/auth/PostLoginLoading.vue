<script setup lang="ts">
/**
 * Écran de chargement post-connexion — aligné Figma node 326:4 (loading).
 * Fond blanc, halo dégradé bas, disque logo centré, anneaux animés autour du logo.
 */
import { onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    redirectTo: string;
}>();

const MIN_DISPLAY_MS = 1500;
const navigated = ref(false);

onMounted(() => {
    const started = Date.now();
    const go = () => {
        if (navigated.value) return;
        navigated.value = true;
        router.visit(props.redirectTo, {
            replace: true,
            preserveScroll: false,
        });
    };
    const elapsed = () => Date.now() - started;
    const schedule = () => {
        const wait = Math.max(0, MIN_DISPLAY_MS - elapsed());
        window.setTimeout(go, wait);
    };
    schedule();
});
</script>

<template>
    <Head title="Chargement — BengaDok" />

    <div
        class="relative isolate flex min-h-svh w-full flex-col items-center justify-center overflow-hidden bg-white"
        data-page="post-login-loading"
    >
        <!-- Halo dégradé bas (Figma : blur fort, bleu → vert) -->
        <div
            class="pointer-events-none absolute -bottom-[20%] left-1/2 h-[55vh] w-[200%] max-w-[2200px] -translate-x-1/2 rounded-[50%] opacity-95 bengadok-loader-glow-drift"
            style="
                background: linear-gradient(
                    90deg,
                    rgb(57, 149, 210) 0%,
                    rgb(91, 182, 110) 55%,
                    rgb(57, 149, 210) 100%
                );
                filter: blur(56px);
            "
            aria-hidden="true"
        />
        <!-- Reflet animé sur le halo -->
        <div
            class="pointer-events-none absolute -bottom-[18%] left-0 h-[45vh] w-[45%] max-w-[900px] opacity-30 bengadok-loader-shimmer"
            style="
                background: linear-gradient(
                    105deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.55) 45%,
                    transparent 90%
                );
                filter: blur(20px);
            "
            aria-hidden="true"
        />

        <!-- Zone logo + effets -->
        <div class="relative z-10 flex flex-col items-center px-4">
            <div class="relative flex size-[min(82vw,300px)] items-center justify-center">
                <!-- Anneaux pulsants concentriques -->
                <div
                    class="bengadok-loader-ring-1 pointer-events-none absolute inset-[-8%] rounded-full border-2 border-[#3995d2]/35"
                    aria-hidden="true"
                />
                <div
                    class="bengadok-loader-ring-2 pointer-events-none absolute inset-[-18%] rounded-full border border-[#5bb66e]/30"
                    aria-hidden="true"
                />
                <div
                    class="bengadok-loader-ring-3 pointer-events-none absolute inset-[-28%] rounded-full border border-[#3995d2]/20"
                    aria-hidden="true"
                />

                <!-- Arc SVG rotatif (effet chargement) -->
                <div
                    class="pointer-events-none absolute inset-[-6%] flex items-center justify-center bengadok-loader-spin"
                    aria-hidden="true"
                >
                    <svg class="size-full" viewBox="0 0 100 100" fill="none">
                        <defs>
                            <linearGradient id="bengadok-loader-arc" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#3995d2" />
                                <stop offset="100%" stop-color="#5bb66e" />
                            </linearGradient>
                        </defs>
                        <circle
                            cx="50"
                            cy="50"
                            r="46"
                            stroke="url(#bengadok-loader-arc)"
                            stroke-width="3"
                            stroke-linecap="round"
                            fill="none"
                            stroke-dasharray="72 216"
                            opacity="0.85"
                        />
                    </svg>
                </div>

                <!-- Cercle blanc Figma + ombre 5px 0 10 -->
                <div
                    class="bengadok-loader-logo-in relative flex aspect-square w-[min(72vw,260px)] flex-col items-center justify-center gap-2 rounded-full bg-white px-4 py-6 opacity-0 shadow-[5px_0px_10px_0px_rgba(0,0,0,0.25)]"
                >
                    <!-- Icône officielle (même esprit que LoginLogo) -->
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 80 80"
                        class="size-[72px] shrink-0 sm:size-[88px]"
                        aria-hidden="true"
                    >
                        <defs>
                            <linearGradient id="bengadok-pl-bg" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#3d9bad" />
                                <stop offset="100%" stop-color="#34B0C7" />
                            </linearGradient>
                        </defs>
                        <circle cx="40" cy="40" r="38" fill="url(#bengadok-pl-bg)" />
                        <g transform="rotate(-22 40 40)" fill="none" stroke="#2C4685" stroke-width="5" stroke-linecap="round">
                            <path d="M30 30 Q30 50 42 58" />
                            <path d="M50 30 Q50 50 38 58" />
                        </g>
                        <rect x="35" y="22" width="10" height="36" rx="2" fill="#34B0C7" />
                        <rect x="22" y="35" width="36" height="10" rx="2" fill="#34B0C7" />
                    </svg>

                    <div
                        class="inline-flex items-center gap-0.5 rounded-full px-3 py-0.5"
                        style="border: 2px solid #2c4685; background: rgba(255, 255, 255, 0.95)"
                    >
                        <span class="text-lg font-semibold tracking-tight text-[#2C4685] sm:text-xl">Benga</span>
                        <span class="text-lg font-semibold tracking-tight text-[#5BB034] sm:text-xl">Dok</span>
                    </div>
                    <p class="max-w-[200px] text-center text-[11px] font-medium leading-tight text-[#94a3b8] sm:text-xs">
                        Votre centre d'appel médical
                    </p>
                </div>
            </div>

            <p class="mt-8 text-sm font-medium text-[#64748b]" role="status" aria-live="polite">
                Chargement de votre espace…
            </p>
        </div>
    </div>
</template>
