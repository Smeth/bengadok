<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pill } from 'lucide-vue-next';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-6 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-semibold text-slate-800 dark:text-white"
                    >
                        <div
                            class="flex size-14 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40"
                        >
                            <Pill class="size-7 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-lg">BengaDok</span>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
