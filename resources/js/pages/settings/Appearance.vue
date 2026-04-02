<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import SettingsPageShell from '@/components/settings/SettingsPageShell.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres d\'apparence',
        href: edit(),
    },
];
</script>

<template>
    <SettingsPageShell :breadcrumbs="breadcrumbItems">
        <Head title="Paramètres d'apparence" />

        <h1 class="sr-only">Paramètres d'apparence</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Apparence"
                    description="Personnalisez l'apparence de votre compte"
                />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </SettingsPageShell>
</template>
