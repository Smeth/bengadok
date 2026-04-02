<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const page = usePage();
const processing = ref(false);

const flashError = computed(() => (page.props.flash as { error?: string })?.error);
const flashStatus = computed(() => (page.props.flash as { status?: string })?.status);

function resetApp() {
    processing.value = true;
    router.post('/settings/reset', {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Réinitialiser l'application" />

        <h1 class="sr-only">Réinitialiser l'application</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Réinitialiser l'application"
                    description="Réinitialise toutes les données (migrate:fresh --seed). Disponible uniquement en environnement local."
                />

                <div
                    v-if="flashError"
                    class="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive"
                >
                    {{ flashError }}
                </div>
                <div
                    v-else-if="flashStatus"
                    class="rounded-lg border border-green-500/50 bg-green-500/10 p-4 text-sm text-green-700 dark:text-green-400"
                >
                    {{ flashStatus }}
                </div>

                <div class="rounded-lg border border-amber-500/50 bg-amber-500/10 p-4">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        Cette action supprimera toutes les données de l'application et réexécutera les migrations
                        avec les seeders. Vous serez déconnecté et devrez vous reconnecter.
                    </p>
                </div>

                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button
                            variant="destructive"
                            :disabled="processing"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Réinitialiser l'application
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Confirmer la réinitialisation</AlertDialogTitle>
                            <AlertDialogDescription>
                                Êtes-vous sûr de vouloir réinitialiser l'application ? Toutes les données seront
                                supprimées et remplacées par les données de démonstration. Cette action est irréversible.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                @click="resetApp"
                            >
                                Réinitialiser
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
