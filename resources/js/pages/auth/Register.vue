<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { login } from '@/routes';

/**
 * Inscription publique désactivée.
 * Les comptes se créent depuis le dashboard.
 */
onMounted(() => {
    router.visit(login());
});
</script>

<template>
    <Head title="Inscription - BengaDok" />
    <div class="flex min-h-svh items-center justify-center">
        <p class="text-muted-foreground">
            Redirection vers la page de connexion...
        </p>
    </div>
</template>
