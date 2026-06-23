<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    href: string;
}>();
</script>

<template>
    <Link :href="href" class="bengadok-back-link">
        <ArrowLeft class="size-4 shrink-0" aria-hidden="true" />
        <slot />
    </Link>
</template>
