<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pill, Truck, Users } from 'lucide-vue-next';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="BengaDok - Gestion des commandes de pharmacie">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="flex min-h-screen flex-col items-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-6 text-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950 dark:text-slate-100 lg:justify-center lg:p-8"
    >
        <header class="mb-6 w-full max-w-[335px] text-sm lg:max-w-4xl">
            <nav class="flex items-center justify-end gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-block rounded-lg border border-emerald-600/30 bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700"
                >
                    Tableau de bord
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-block rounded-lg border border-emerald-600 bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700 dark:border-emerald-500 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                    >
                        Connexion
                    </Link>
                </template>
            </nav>
        </header>

        <main
            class="flex w-full max-w-[335px] flex-col items-center opacity-100 transition-opacity duration-750 lg:max-w-4xl lg:grow starting:opacity-0"
        >
            <div class="flex flex-col items-center text-center">
                <div
                    class="mb-6 flex size-20 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-10 text-emerald-600 dark:text-emerald-400"
                    >
                        <path d="m10.5 20.5 8-4.5V8l-8-4.5L2.5 8v8l8 4.5Z" />
                        <path d="m2.5 8 8 4.5 8-4.5" />
                        <path d="m10.5 20.5 8-4.5" />
                        <path d="m10.5 11.5-8-3.5" />
                        <path d="m10.5 11.5 8 4.5" />
                    </svg>
                </div>
                <h1 class="mb-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white lg:text-4xl">
                    BengaDok
                </h1>
                <p class="mb-8 max-w-md text-lg text-slate-600 dark:text-slate-300">
                    Votre pharmacie, connectée à plus de patients chaque jour.
                </p>

                <div
                    class="mb-10 grid gap-4 rounded-2xl border border-slate-200/80 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-800/50 lg:grid-cols-3"
                >
                    <div class="flex flex-col items-center gap-2">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40">
                            <Pill class="size-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Commandes simplifiées
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Gérez vos commandes de médicaments en un clin d'œil
                        </span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-900/40">
                            <Truck class="size-6 text-teal-600 dark:text-teal-400" />
                        </div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Livraison proche
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Pharmacie la plus proche selon l'adresse du client
                        </span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40">
                            <Users class="size-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Gestion complète
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Gérants, vendeurs et agents call center
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="inline-flex items-center justify-center rounded-xl border-2 border-emerald-600 bg-emerald-600 px-8 py-3 text-base font-semibold text-white transition-colors hover:bg-emerald-700"
                    >
                        Se connecter
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center justify-center rounded-xl border-2 border-emerald-600 bg-emerald-600 px-8 py-3 text-base font-semibold text-white transition-colors hover:bg-emerald-700"
                    >
                        Accéder au tableau de bord
                    </Link>
                </div>
            </div>
        </main>

        <footer class="mt-auto hidden pt-8 text-center text-sm text-slate-500 dark:text-slate-400 lg:block">
            BengaDok — Plateforme de gestion des commandes de pharmacie
        </footer>
    </div>
</template>
