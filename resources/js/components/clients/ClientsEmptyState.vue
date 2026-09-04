<script setup lang="ts">
defineProps<{
    message: string;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-border bg-white px-6 py-12 text-center shadow-sm dark:border-white/10 dark:bg-white/[0.96]"
    >
        <p class="text-sm text-muted-foreground">{{ message }}</p>
    </div>
</template>
