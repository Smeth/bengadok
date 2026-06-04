<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Database } from 'lucide-vue-next';

export type MedicamentsSectionTab =
    | 'catalogue'
    | 'statistiques'
    | 'db_medicament'
    | 'doublons';

defineProps<{
    active?: MedicamentsSectionTab;
}>();

const tabs: {
    id: MedicamentsSectionTab;
    label: string;
    href: string;
    icon?: 'database';
}[] = [
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
        icon: 'database',
    },
    {
        id: 'doublons',
        label: 'Gestion des doublons',
        href: '/medicaments/doublons',
    },
];

const inactiveClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-white/80 text-muted-foreground hover:bg-white';
const activeClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium bg-[#459cd1] text-white';
</script>

<template>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex flex-wrap gap-2">
            <template v-for="tab in tabs" :key="tab.id">
                <span v-if="active === tab.id" :class="activeClass">
                    <Database
                        v-if="tab.icon === 'database'"
                        class="size-4 shrink-0"
                    />
                    {{ tab.label }}
                </span>
                <Link v-else :href="tab.href" :class="inactiveClass">
                    <Database
                        v-if="tab.icon === 'database'"
                        class="size-4 shrink-0"
                    />
                    {{ tab.label }}
                </Link>
            </template>
        </div>
    </div>
</template>
