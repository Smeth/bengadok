<script setup lang="ts">
import { computed } from 'vue';
import { usePharmacyPortal } from '@/composables/usePharmacyPortal';
import AppLayout from '@/layouts/AppLayout.vue';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    { breadcrumbs: () => [] },
);

const { isPharmacyPortalUser } = usePharmacyPortal();

const layoutComponent = computed(() =>
    isPharmacyPortalUser.value ? PharmacyLayout : AppLayout,
);

const layoutProps = computed(() =>
    isPharmacyPortalUser.value ? {} : { breadcrumbs: props.breadcrumbs },
);
</script>

<template>
    <component :is="layoutComponent" v-bind="layoutProps">
        <slot />
    </component>
</template>
