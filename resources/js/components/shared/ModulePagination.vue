<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    modulePaginationActiveClass,
    modulePaginationInactiveClass,
} from '@/lib/bengadokUi';

defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
    from?: number | null;
    to?: number | null;
    total?: number;
}>();
</script>

<template>
    <div
        v-if="links.length > 3"
        class="flex items-center justify-between px-2"
    >
        <div
            v-if="from != null && to != null && total != null"
            class="hidden text-sm text-muted-foreground sm:block"
        >
            Affichage de
            <span class="font-medium text-foreground">{{ from }}</span>
            à
            <span class="font-medium text-foreground">{{ to }}</span>
            sur
            <span class="font-medium text-foreground">{{ total }}</span>
            résultats
        </div>
        <div class="flex flex-wrap items-center gap-1">
            <template v-for="(link, pIndex) in links" :key="pIndex">
                <div
                    v-if="link.url === null"
                    class="flex min-w-9 items-center justify-center rounded-lg border border-transparent px-3 py-1.5 text-sm text-muted-foreground"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    class="flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-muted/50"
                    :class="
                        link.active
                            ? modulePaginationActiveClass
                            : modulePaginationInactiveClass
                    "
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
