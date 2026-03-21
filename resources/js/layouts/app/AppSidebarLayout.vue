<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isDashboard = computed(() => page.url === '/dashboard' || page.url.startsWith('/dashboard?'));
const isCommandes = computed(() => page.url.startsWith('/commandes'));
const hasGradientBg = computed(() => isDashboard.value || isCommandes.value);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div
                class="relative isolate"
                :class="hasGradientBg ? 'min-h-svh' : 'min-h-full'"
                :style="hasGradientBg
                    ? { background: 'linear-gradient(60.02deg, rgb(57, 149, 210) 35.89%, rgb(91, 182, 110) 92.85%)' }
                    : undefined"
            >
                <AppSidebarHeader
                    header-class="relative z-20"
                    :breadcrumbs="breadcrumbs"
                    :variant="hasGradientBg ? 'gradient' : 'default'"
                />
                <div class="relative z-10 min-h-0">
                    <slot />
                </div>
            </div>
        </AppContent>
    </AppShell>
</template>
