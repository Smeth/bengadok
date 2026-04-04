<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
    /** Texte clair sur fond dégradé (header) — surcharge text-muted du fil d’Ariane */
    light?: boolean;
};

withDefaults(defineProps<Props>(), {
    light: false,
});
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList
            :class="
                light
                    ? '!text-white/95 drop-shadow-[0_1px_1px_rgba(0,0,0,0.2)] [&_a]:!text-white/95 [&_a:hover]:!text-white [&_span[aria-current=page]]:!text-white [&_span[aria-current=page]]:font-semibold [&_svg]:text-white/85'
                    : undefined
            "
        >
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
