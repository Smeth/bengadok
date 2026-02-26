<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pill, Store, Users } from 'lucide-vue-next';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh">
        <!-- Partie gauche : gradient, illustrations, slogan -->
        <div
            class="hidden flex-1 flex-col justify-between bg-gradient-to-b from-sky-200 via-sky-100 to-emerald-400 p-8 lg:flex dark:from-sky-900/40 dark:via-emerald-900/30 dark:to-emerald-800/50"
        >
            <div class="flex flex-wrap gap-4">
                <div
                    class="flex size-24 items-center justify-center overflow-hidden rounded-full bg-white/60 shadow-lg dark:bg-white/10"
                >
                    <Store class="size-10 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div
                    class="flex size-24 items-center justify-center overflow-hidden rounded-full bg-white/60 shadow-lg dark:bg-white/10"
                >
                    <Pill class="size-10 text-sky-600 dark:text-sky-400" />
                </div>
                <div
                    class="flex size-24 items-center justify-center overflow-hidden rounded-full bg-white/60 shadow-lg dark:bg-white/10"
                >
                    <Users class="size-10 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>

            <p class="max-w-sm text-lg font-medium text-slate-700 dark:text-slate-200">
                Votre pharmacie, connectée à plus de patients chaque jour.
            </p>

            <div class="flex justify-center">
                <Link
                    :href="home()"
                    class="flex flex-col items-center gap-2"
                >
                    <div
                        class="flex size-14 items-center justify-center rounded-full bg-sky-600 shadow-lg dark:bg-sky-500"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="white"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="size-7"
                        >
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </div>
                    <span class="font-semibold text-slate-800 dark:text-white">BengaDok</span>
                </Link>
            </div>
        </div>

        <!-- Partie droite : formulaire -->
        <div
            class="flex flex-1 flex-col items-center justify-center bg-slate-50 p-6 dark:bg-slate-900 md:p-10"
        >
            <div class="w-full max-w-md">
                <div
                    class="rounded-2xl border border-slate-200/80 bg-white p-8 shadow-xl dark:border-slate-700 dark:bg-slate-800"
                >
                    <h1 class="mb-2 text-2xl font-semibold text-slate-900 dark:text-white">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mb-6 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ description }}
                    </p>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
