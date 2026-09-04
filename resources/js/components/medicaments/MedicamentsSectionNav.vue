<script setup lang="ts">
import { Database } from 'lucide-vue-next';
import ModuleSectionTabs from '@/components/shared/ModuleSectionTabs.vue';
import type { ModuleSectionTab } from '@/components/shared/ModuleSectionTabs.vue';

export type MedicamentsSectionTab =
    | 'catalogue'
    | 'statistiques'
    | 'db_medicament'
    | 'doublons';

defineProps<{
    active?: MedicamentsSectionTab;
}>();

const tabs: ModuleSectionTab[] = [
    { id: 'catalogue', label: 'Catalogue Médicaments', href: '/medicaments' },
    {
        id: 'statistiques',
        label: 'Statistiques',
        href: '/medicaments?onglet=statistiques',
    },
    {
        id: 'db_medicament',
        label: 'DB médicament',
        href: '/medicaments?onglet=db_medicament',
        icon: Database,
    },
    {
        id: 'doublons',
        label: 'Gestion des doublons',
        href: '/medicaments/doublons',
    },
];
</script>

<template>
    <ModuleSectionTabs :tabs="tabs" :active="active" />
</template>
