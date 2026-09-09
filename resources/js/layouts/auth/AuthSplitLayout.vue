<script setup lang="ts">
import { ClipboardList, Sparkles } from 'lucide-vue-next';
import LoginLogo from '@/components/LoginLogo.vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    {
        icon: ClipboardList,
        title: 'Commandes en temps réel',
        text: 'Nouvelles commandes, validations et suivi centralisés.',
    },
    {
        icon: Sparkles,
        title: 'Expérience unifiée',
        text: 'Interface claire, alertes et notifications intégrées.',
    },
] as const;
</script>

<template>
    <div class="flex min-h-svh">
        <!-- Panneau branding (desktop) -->
        <div
            class="relative hidden flex-1 overflow-hidden lg:flex lg:flex-col lg:justify-center"
            style="
                background: linear-gradient(
                    145deg,
                    #459cd1 0%,
                    #52a878 28%,
                    #5bb66e 58%,
                    #67cb88 100%
                );
            "
        >
            <!-- Halos décoratifs -->
            <div
                class="pointer-events-none absolute -left-[15%] top-[8%] size-[420px] rounded-full bg-white/10 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -bottom-[10%] right-[5%] size-[400px] rounded-full bg-[#5bb66e]/35 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute bottom-[15%] left-[25%] size-[280px] rounded-full bg-[#67cb88]/30 blur-3xl"
                aria-hidden="true"
            />

            <div
                class="relative z-10 mx-auto flex w-full max-w-xl flex-col gap-8 px-10 py-12 xl:px-14"
            >
                <div
                    class="flex w-fit items-center rounded-full bg-white/95 px-4 py-2.5 shadow-[0_8px_24px_rgba(0,0,0,0.12)]"
                >
                    <LoginLogo size="compact" />
                </div>

                <div class="space-y-4">
                    <h2
                        class="max-w-md text-3xl font-bold leading-tight tracking-tight text-white drop-shadow-sm xl:text-4xl"
                    >
                        Votre pharmacie, connectée à plus de patients chaque
                        jour.
                    </h2>
                    <p class="max-w-md text-base leading-relaxed text-white/85">
                        Plateforme de gestion des commandes pour pharmacies et
                        back-office BengaDok.
                    </p>
                </div>

                <ul class="space-y-4">
                    <li
                        v-for="item in highlights"
                        :key="item.title"
                        class="flex gap-3"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/20"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <div class="min-w-0 pt-0.5">
                            <p class="text-sm font-semibold text-white">
                                {{ item.title }}
                            </p>
                            <p class="mt-0.5 text-sm leading-snug text-white/75">
                                {{ item.text }}
                            </p>
                        </div>
                    </li>
                </ul>

                <p class="text-xs text-white/45">
                    © {{ new Date().getFullYear() }} BengaDok — Tous droits
                    réservés
                </p>
            </div>
        </div>

        <!-- Partie droite : zone pour la carte formulaire -->
        <div
            class="flex w-full flex-col items-center justify-center px-4 py-8 md:w-[42%] md:min-w-[380px] md:px-8 md:py-12 lg:min-w-[420px]"
            style="
                background: linear-gradient(to right, #459cd1 0%, #67cb88 100%);
            "
        >
            <div
                class="w-full max-w-md rounded-2xl border-0 bg-white p-6 shadow-xl dark:border dark:border-border dark:bg-card md:-ml-6 md:rounded-[22px] md:p-8"
                style="
                    box-shadow:
                        0 4px 24px rgba(0, 0, 0, 0.08),
                        0 8px 32px rgba(0, 0, 0, 0.06);
                "
            >
                <h1
                    class="mb-6 text-2xl font-bold text-[#333333] dark:text-foreground md:text-3xl"
                >
                    {{ title }}
                </h1>
                <p v-if="description" class="mb-6 text-sm text-slate-500">
                    {{ description }}
                </p>
                <slot />
            </div>
        </div>
    </div>
</template>
