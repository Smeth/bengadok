<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
const processingCommandes = ref(false);
const processingClients = ref(false);
const processingPharmacies = ref(false);
const processingFull = ref(false);
const processingApp = ref(false);

const confirmationCommandes = ref('');
const confirmationClients = ref('');
const confirmationPharmacies = ref('');
const confirmationFull = ref('');

const allowPharmacyReset = computed(
    () =>
        Boolean(
            (page.props as { allowPharmacyReset?: boolean }).allowPharmacyReset,
        ),
);

const allowLocalAppReset = computed(
    () =>
        Boolean(
            (page.props as { allowLocalAppReset?: boolean }).allowLocalAppReset,
        ),
);

const flashError = computed(
    () => (page.props.flash as { error?: string })?.error,
);
const flashStatus = computed(
    () => (page.props.flash as { status?: string })?.status,
);

function postReset(
    url: string,
    body: Record<string, string>,
    processing: { value: boolean },
    onSuccess?: () => void,
) {
    processing.value = true;
    router.post(url, body, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            onSuccess?.();
        },
    });
}

function resetCommandes() {
    postReset(
        '/settings/reset/commandes',
        { confirmation: confirmationCommandes.value },
        processingCommandes,
        () => {
            confirmationCommandes.value = '';
        },
    );
}

function resetClients() {
    postReset(
        '/settings/reset/clients',
        { confirmation: confirmationClients.value },
        processingClients,
        () => {
            confirmationClients.value = '';
        },
    );
}

function resetPharmacies() {
    postReset(
        '/settings/reset/pharmacies',
        { confirmation: confirmationPharmacies.value },
        processingPharmacies,
        () => {
            confirmationPharmacies.value = '';
        },
    );
}

function resetFull() {
    postReset(
        '/settings/reset/full',
        { confirmation: confirmationFull.value },
        processingFull,
        () => {
            confirmationFull.value = '';
        },
    );
}

function resetApp() {
    processingApp.value = true;
    router.post(
        '/settings/reset',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingApp.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <Head title="Réinitialiser l'application" />

        <h1 class="sr-only">Réinitialiser l'application</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    title="Réinitialisation des données"
                    description="Actions réservées aux super administrateurs. Chaque opération est définitive."
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

                <!-- Commandes -->
                <div
                    class="rounded-lg border border-border bg-card p-4 space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Supprimer toutes les commandes"
                        description="Efface toutes les commandes et les ordonnances associées (y compris les fichiers joints). Les pharmacies, les clients et le catalogue ne sont pas modifiés."
                    />
                    <p
                        v-if="!allowPharmacyReset"
                        class="text-sm text-muted-foreground"
                    >
                        Cette opération n’est pas activée sur cet environnement.
                    </p>
                    <div v-else class="space-y-3">
                        <div class="space-y-2">
                            <Label for="confirm-commandes"
                                >Tapez
                                <span class="font-mono font-semibold"
                                    >SUPPRIMER COMMANDES</span
                                >
                                pour confirmer</Label
                            >
                            <Input
                                id="confirm-commandes"
                                v-model="confirmationCommandes"
                                class="font-mono"
                                placeholder="SUPPRIMER COMMANDES"
                                autocomplete="off"
                            />
                        </div>
                        <AlertDialog>
                            <AlertDialogTrigger as-child>
                                <Button
                                    variant="destructive"
                                    :disabled="
                                        processingCommandes ||
                                        confirmationCommandes !==
                                            'SUPPRIMER COMMANDES'
                                    "
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Supprimer les commandes
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle
                                        >Confirmer la suppression</AlertDialogTitle
                                    >
                                    <AlertDialogDescription>
                                        Toutes les commandes et ordonnances seront
                                        définitivement supprimées.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        @click="resetCommandes"
                                    >
                                        Confirmer
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <!-- Clients -->
                <div
                    class="rounded-lg border border-border bg-card p-4 space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Supprimer les clients et l’historique des commandes"
                        description="Supprime toutes les fiches clients, les regroupements de doublons, ainsi que toutes les commandes et ordonnances. Les pharmacies et le catalogue ne sont pas modifiés."
                    />
                    <p
                        v-if="!allowPharmacyReset"
                        class="text-sm text-muted-foreground"
                    >
                        Cette opération n’est pas activée sur cet environnement.
                    </p>
                    <div v-else class="space-y-3">
                        <div class="space-y-2">
                            <Label for="confirm-clients"
                                >Tapez
                                <span class="font-mono font-semibold"
                                    >SUPPRIMER CLIENTS</span
                                >
                                pour confirmer</Label
                            >
                            <Input
                                id="confirm-clients"
                                v-model="confirmationClients"
                                class="font-mono"
                                placeholder="SUPPRIMER CLIENTS"
                                autocomplete="off"
                            />
                        </div>
                        <AlertDialog>
                            <AlertDialogTrigger as-child>
                                <Button
                                    variant="destructive"
                                    :disabled="
                                        processingClients ||
                                        confirmationClients !==
                                            'SUPPRIMER CLIENTS'
                                    "
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Supprimer les clients
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle
                                        >Confirmer la suppression</AlertDialogTitle
                                    >
                                    <AlertDialogDescription>
                                        Les clients, l’historique des commandes et
                                        les ordonnances seront définitivement
                                        supprimés.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        @click="resetClients"
                                    >
                                        Confirmer
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <!-- Pharmacies -->
                <div
                    class="rounded-lg border border-destructive/50 bg-destructive/5 p-4 space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Supprimer toutes les pharmacies"
                        description="Supprime toutes les pharmacies, toutes les commandes et ordonnances (fichiers inclus), et les comptes utilisateurs rattachés à une pharmacie (gérants, vendeurs). Les comptes administrateur du back-office ne sont pas supprimés."
                    />
                    <p
                        v-if="!allowPharmacyReset"
                        class="text-sm text-muted-foreground"
                    >
                        Cette opération n’est pas activée sur cet environnement.
                    </p>
                    <div v-else class="space-y-3">
                        <div class="space-y-2">
                            <Label for="confirm-pharmacies"
                                >Tapez
                                <span class="font-mono font-semibold"
                                    >SUPPRIMER PHARMACIES</span
                                >
                                pour confirmer</Label
                            >
                            <Input
                                id="confirm-pharmacies"
                                v-model="confirmationPharmacies"
                                class="font-mono"
                                placeholder="SUPPRIMER PHARMACIES"
                                autocomplete="off"
                            />
                        </div>
                        <AlertDialog>
                            <AlertDialogTrigger as-child>
                                <Button
                                    variant="destructive"
                                    :disabled="
                                        processingPharmacies ||
                                        confirmationPharmacies !==
                                            'SUPPRIMER PHARMACIES'
                                    "
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Supprimer les pharmacies
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle
                                        >Dernière confirmation</AlertDialogTitle
                                    >
                                    <AlertDialogDescription>
                                        Toutes les pharmacies et les données
                                        liées seront définitivement supprimées.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        @click="resetPharmacies"
                                    >
                                        Confirmer la suppression
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <!-- Réinit complète -->
                <div
                    class="rounded-lg border border-destructive/60 bg-destructive/10 p-4 space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Réinitialisation complète (données métier)"
                        description="Supprime les commandes, les clients, les pharmacies, le catalogue des médicaments et tous les comptes utilisateurs sauf les profils administrateur (admin et super administrateur). À utiliser avec une extrême prudence."
                    />
                    <p
                        v-if="!allowPharmacyReset"
                        class="text-sm text-muted-foreground"
                    >
                        Cette opération n’est pas activée sur cet environnement.
                    </p>
                    <div v-else class="space-y-3">
                        <div class="space-y-2">
                            <Label for="confirm-full"
                                >Tapez
                                <span class="font-mono font-semibold"
                                    >VIDER TOUTES LES DONNEES</span
                                >
                                pour confirmer</Label
                            >
                            <Input
                                id="confirm-full"
                                v-model="confirmationFull"
                                class="font-mono"
                                placeholder="VIDER TOUTES LES DONNEES"
                                autocomplete="off"
                            />
                        </div>
                        <AlertDialog>
                            <AlertDialogTrigger as-child>
                                <Button
                                    variant="destructive"
                                    :disabled="
                                        processingFull ||
                                        confirmationFull !==
                                            'VIDER TOUTES LES DONNEES'
                                    "
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Lancer la réinitialisation complète
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle
                                        >Confirmer la réinitialisation
                                        complète</AlertDialogTitle
                                    >
                                    <AlertDialogDescription>
                                        Presque toutes les données seront
                                        effacées. Seuls les comptes administrateur
                                        seront conservés. Cette action est
                                        irréversible.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        @click="resetFull"
                                    >
                                        Confirmer
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <!-- Base neuve (local) -->
                <div
                    v-if="allowLocalAppReset"
                    class="rounded-lg border border-amber-500/50 bg-amber-500/10 p-4 space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Remettre la base à l’état initial (développement)"
                        description="Efface toutes les données et recharge l’application avec les jeux de données de démonstration. Réservé à l’environnement de développement sur cette machine. Vous serez déconnecté."
                    />
                    <div
                        class="rounded-lg border border-amber-600/40 bg-amber-500/5 p-4"
                    >
                        <p class="text-sm text-amber-900 dark:text-amber-100">
                            Cette action efface l’intégralité du contenu de la base
                            et le remplace par les données initiales prévues pour les
                            tests.
                        </p>
                    </div>
                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button variant="destructive" :disabled="processingApp">
                                <RotateCcw class="h-4 w-4" />
                                Réinitialiser l’application
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle
                                    >Confirmer la réinitialisation</AlertDialogTitle
                                >
                                <AlertDialogDescription>
                                    Toutes les données seront supprimées et
                                    remplacées par les données de démonstration.
                                    Cette action est irréversible.
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
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
