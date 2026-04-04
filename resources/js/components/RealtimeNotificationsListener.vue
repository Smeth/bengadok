<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RealtimeNotificationsSubscriber from '@/components/RealtimeNotificationsSubscriber.vue';

const page = usePage();
const userId = computed(
    () => (page.props.auth as { user?: { id: number } } | undefined)?.user?.id,
);

const horsLigne = ref(false);
</script>

<template>
    <RealtimeNotificationsSubscriber
        v-if="userId"
        :key="userId"
        :user-id="userId"
        @hors_ligne="horsLigne = $event"
    />
    <div
        v-if="horsLigne"
        class="fixed right-4 bottom-4 z-50 flex items-center gap-2 rounded-lg border border-yellow-300 bg-yellow-100 px-3 py-2 text-sm text-yellow-800 shadow-md"
        role="alert"
        aria-live="polite"
    >
        <span class="h-2 w-2 animate-pulse rounded-full bg-yellow-500" />
        Notifications temps réel hors ligne — reconnexion en cours…
    </div>
</template>
