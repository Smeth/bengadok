<script setup lang="ts">
import AppToast from '@/components/AppToast.vue';
import { useFlashToast } from '@/composables/useFlashToast';

const props = withDefaults(
    defineProps<{
        /** Affiche un bandeau inline pour la réinit. mot de passe (mot de passe visible). */
        inlinePasswordReset?: boolean;
        skipStatusWhenCreatedUsername?: boolean;
    }>(),
    {
        inlinePasswordReset: false,
        skipStatusWhenCreatedUsername: true,
    },
);

const { successToast, errorToast, inlinePasswordResetMessage } = useFlashToast({
    skipStatusWhenCreatedUsername: props.skipStatusWhenCreatedUsername,
});
</script>

<template>
    <div
        v-if="inlinePasswordReset && inlinePasswordResetMessage"
        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
        role="status"
    >
        {{ inlinePasswordResetMessage }}
    </div>

    <AppToast v-model:show="successToast.show" :title="successToast.title" />
    <AppToast
        v-model:show="errorToast.show"
        variant="error"
        :title="errorToast.title"
    />
</template>
