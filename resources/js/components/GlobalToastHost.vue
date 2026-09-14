<script setup lang="ts">
import AppToast from '@/components/AppToast.vue';
import { globalToast } from '@/lib/globalToast';
</script>

<template>
    <AppToast
        v-model:show="globalToast.show"
        :variant="globalToast.variant"
        :title="globalToast.title"
    />
</template>
