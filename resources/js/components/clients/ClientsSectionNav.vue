<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export type ClientsSectionTab =
    | 'liste'
    | 'prospects'
    | 'doublons'
    | 'statistiques';

defineProps<{
    active?: ClientsSectionTab;
}>();

const tabs: {
    id: ClientsSectionTab;
    label: string;
    href: string;
}[] = [
    { id: 'liste', label: 'Liste des clients', href: '/clients' },
    { id: 'prospects', label: 'Prospects', href: '/clients/prospects' },
    {
        id: 'doublons',
        label: 'Gestion des doublons Clients',
        href: '/clients/doublons',
    },
    {
        id: 'statistiques',
        label: 'Statistiques',
        href: '/clients?tab=statistiques',
    },
];

const inactiveClass =
    'rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-white/80 text-muted-foreground hover:bg-white';
const activeClass =
    'rounded-lg px-4 py-2 text-sm font-medium bg-[#459cd1] text-white';
</script>

<template>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex flex-wrap gap-2">
            <template v-for="tab in tabs" :key="tab.id">
                <span v-if="active === tab.id" :class="activeClass">
                    {{ tab.label }}
                </span>
                <Link v-else :href="tab.href" :class="inactiveClass">
                    {{ tab.label }}
                </Link>
            </template>
        </div>
    </div>
</template>
