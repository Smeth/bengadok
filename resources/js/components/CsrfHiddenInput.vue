<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const token = computed(() => {
    const raw = page.props.csrf_token;
    return typeof raw === 'string' ? raw : '';
});
</script>

<template>
    <input type="hidden" name="_token" :value="token" />
</template>
