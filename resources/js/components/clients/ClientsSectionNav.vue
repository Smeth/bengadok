<script setup lang="ts">
import { Database } from 'lucide-vue-next';
import ModuleSectionTabs from '@/components/shared/ModuleSectionTabs.vue';
import type { ModuleSectionTab } from '@/components/shared/ModuleSectionTabs.vue';

export type ClientsSectionTab =
    | 'liste'
    | 'prospects'
    | 'doublons'
    | 'statistiques';

defineProps<{
    active?: ClientsSectionTab;
}>();

const tabs: ModuleSectionTab[] = [
    { id: 'liste', label: 'Liste des clients', href: '/clients' },
    { id: 'prospects', label: 'Prospects', href: '/clients/prospects' },
    {
        id: 'doublons',
        label: 'Gestion des doublons',
        href: '/clients/doublons',
    },
    {
        id: 'statistiques',
        label: 'Statistiques',
        href: '/clients?tab=statistiques',
    },
];
</script>

<template>
    <ModuleSectionTabs :tabs="tabs" :active="active" />
</template>
