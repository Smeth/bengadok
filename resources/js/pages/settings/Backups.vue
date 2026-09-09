<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Archive,
    CheckCircle2,
    Download,
    HardDrive,
    RotateCcw,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type BackupArchive = {
    id: string;
    filename: string;
    path: string;
    size: number;
    date: string;
};

type BackupHealth = {
    status: 'healthy' | 'warning' | 'critical';
    issues: string[];
    archive_count: number;
    total_size_bytes: number;
    newest_backup_at: string | null;
    newest_backup_age_hours: number | null;
    disk_path: string;
    disk_free_bytes: number | null;
    disk_total_bytes: number | null;
    disk_used_percent: number | null;
    notifications_enabled: boolean;
    max_age_days: number;
    max_storage_mb: number;
    scheduled_at: { clean: string; run: string; monitor: string };
};

const props = defineProps<{
    backups: BackupArchive[];
    backupDisk: string;
    allowBackupRestore: boolean;
    health: BackupHealth;
}>();

const healthLabels: Record<BackupHealth['status'], string> = {
    healthy: 'Sain',
    warning: 'Attention',
    critical: 'Critique',
};

function healthStatusClass(status: BackupHealth['status']): string {
    if (status === 'healthy') {
        return 'border-emerald-500/40 bg-emerald-500/5 text-emerald-800 dark:text-emerald-200';
    }
    if (status === 'warning') {
        return 'border-amber-500/40 bg-amber-500/5 text-amber-900 dark:text-amber-100';
    }

    return 'border-red-500/40 bg-red-500/5 text-red-800 dark:text-red-200';
}

const processing = ref(false);
const deletingId = ref<string | null>(null);
const restoringId = ref<string | null>(null);
const importing = ref(false);

const confirmationRestore = ref('');
const confirmationImport = ref('');
const importFile = ref<File | null>(null);

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} o`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} Ko`;
    }
    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
    }

    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} Go`;
}

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

function runBackup() {
    processing.value = true;
    router.post(
        '/settings/backups',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function deleteBackup(id: string) {
    deletingId.value = id;
    router.delete(`/settings/backups/${id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function restoreBackup(id: string) {
    restoringId.value = id;
    router.post(
        `/settings/backups/${id}/restore`,
        { confirmation: confirmationRestore.value },
        {
            preserveScroll: true,
            onFinish: () => {
                restoringId.value = null;
                confirmationRestore.value = '';
            },
        },
    );
}

function onImportFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    importFile.value = input.files?.[0] ?? null;
}

function importBackup() {
    if (!importFile.value) {
        return;
    }

    importing.value = true;
    router.post(
        '/settings/backups/import',
        {
            confirmation: confirmationImport.value,
            archive: importFile.value,
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                importing.value = false;
                confirmationImport.value = '';
                importFile.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <Head title="Sauvegardes - BengaDok" />

        <h1 class="sr-only">Sauvegardes</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    title="Sauvegardes"
                    description="Créez une archive manuelle, exportez-la en ZIP, ou restaurez une sauvegarde existante. Réservé aux super administrateurs."
                />

                <!-- Surveillance -->
                <div
                    class="rounded-lg border p-4 space-y-3"
                    :class="healthStatusClass(props.health.status)"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <CheckCircle2
                            v-if="props.health.status === 'healthy'"
                            class="size-5 shrink-0"
                        />
                        <AlertTriangle v-else class="size-5 shrink-0" />
                        <p class="font-semibold">
                            État :
                            {{ healthLabels[props.health.status] }}
                        </p>
                    </div>

                    <ul
                        v-if="props.health.issues.length > 0"
                        class="list-disc space-y-1 pl-5 text-sm"
                    >
                        <li v-for="issue in props.health.issues" :key="issue">
                            {{ issue }}
                        </li>
                    </ul>
                    <p v-else class="text-sm">
                        Les sauvegardes sont à jour et dans les limites
                        configurées.
                    </p>

                    <div
                        class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <p>
                            <span class="text-muted-foreground">Archives :</span>
                            {{ props.health.archive_count }}
                        </p>
                        <p>
                            <span class="text-muted-foreground">Volume :</span>
                            {{ formatBytes(props.health.total_size_bytes) }}
                            / {{ props.health.max_storage_mb }} Mo
                        </p>
                        <p>
                            <span class="text-muted-foreground"
                                >Dernière :</span
                            >
                            {{
                                props.health.newest_backup_at
                                    ? formatDate(props.health.newest_backup_at)
                                    : '—'
                            }}
                        </p>
                        <p>
                            <span class="text-muted-foreground">Alertes :</span>
                            {{
                                props.health.notifications_enabled
                                    ? 'e-mail actif'
                                    : 'non configurées'
                            }}
                        </p>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Planification automatique — nettoyage
                        {{ props.health.scheduled_at.clean }}, sauvegarde
                        {{ props.health.scheduled_at.run }}, contrôle
                        {{ props.health.scheduled_at.monitor }} (heure serveur).
                    </p>
                </div>

                <!-- Sauvegarde manuelle -->
                <div
                    class="rounded-lg border border-border bg-card p-4 space-y-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#459cd1]/10 text-[#459cd1]"
                            >
                                <HardDrive class="size-5" />
                            </div>
                            <div>
                                <p class="font-medium text-foreground">
                                    Sauvegarde manuelle
                                </p>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    Génère immédiatement une archive ZIP (base de
                                    données + ordonnances / pièces jointes). Une
                                    sauvegarde automatique est planifiée chaque
                                    nuit à 03:00.
                                </p>
                            </div>
                        </div>
                        <Button
                            class="shrink-0 bg-[#459cd1] hover:bg-[#3a87b8]"
                            :disabled="processing"
                            @click="runBackup"
                        >
                            <Spinner v-if="processing" class="mr-2" />
                            <Archive v-else class="mr-2 size-4" />
                            Sauvegarder maintenant
                        </Button>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Stockage : disque « {{ backupDisk }} » — utilisez
                        « Exporter » pour télécharger le ZIP sur votre
                        ordinateur.
                    </p>
                </div>

                <!-- Liste des archives -->
                <div class="rounded-lg border border-border bg-card">
                    <div class="border-b border-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-foreground">
                            Archives disponibles
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Exportez une archive ou restaurez-la sur le serveur.
                        </p>
                    </div>

                    <div
                        v-if="backups.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        Aucune sauvegarde pour le moment. Lancez une première
                        archive avec le bouton ci-dessus.
                    </div>

                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="backup in backups"
                            :key="backup.id"
                            class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate font-medium text-foreground"
                                >
                                    {{ backup.filename }}
                                </p>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    {{ formatDate(backup.date) }} —
                                    {{ formatBytes(backup.size) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="`/settings/backups/${backup.id}/download`"
                                    >
                                        <Download class="mr-1.5 size-4" />
                                        Exporter
                                    </a>
                                </Button>

                                <AlertDialog
                                    v-if="allowBackupRestore"
                                    @update:open="
                                        (open) => {
                                            if (!open) {
                                                confirmationRestore = '';
                                            }
                                        }
                                    "
                                >
                                    <AlertDialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="text-amber-700 hover:text-amber-800 dark:text-amber-300"
                                        >
                                            <RotateCcw class="mr-1.5 size-4" />
                                            Restaurer
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Restaurer cette sauvegarde ?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                L’application passera en
                                                maintenance le temps de
                                                l’opération. Toutes les données
                                                actuelles seront remplacées par
                                                celles de
                                                <span class="font-medium">{{
                                                    backup.filename
                                                }}</span
                                                >. Action irréversible.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <div class="space-y-2 py-2">
                                            <Label :for="`confirm-restore-${backup.id}`">
                                                Tapez
                                                <span class="font-mono font-semibold"
                                                    >RESTAURER SAUVEGARDE</span
                                                >
                                            </Label>
                                            <Input
                                                :id="`confirm-restore-${backup.id}`"
                                                v-model="confirmationRestore"
                                                class="font-mono"
                                                placeholder="RESTAURER SAUVEGARDE"
                                                autocomplete="off"
                                            />
                                        </div>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel
                                                >Annuler</AlertDialogCancel
                                            >
                                            <AlertDialogAction
                                                class="bg-amber-600 text-white hover:bg-amber-700"
                                                :disabled="
                                                    restoringId === backup.id ||
                                                    confirmationRestore !==
                                                        'RESTAURER SAUVEGARDE'
                                                "
                                                @click="
                                                    restoreBackup(backup.id)
                                                "
                                            >
                                                <Spinner
                                                    v-if="
                                                        restoringId ===
                                                        backup.id
                                                    "
                                                    class="mr-1.5"
                                                />
                                                Confirmer la restauration
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>

                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            :disabled="
                                                deletingId === backup.id
                                            "
                                        >
                                            <Spinner
                                                v-if="deletingId === backup.id"
                                                class="mr-1.5"
                                            />
                                            <Trash2
                                                v-else
                                                class="mr-1.5 size-4"
                                            />
                                            Supprimer
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Supprimer cette sauvegarde ?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Le fichier
                                                <span class="font-medium">{{
                                                    backup.filename
                                                }}</span>
                                                sera définitivement supprimé du
                                                serveur.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel
                                                >Annuler</AlertDialogCancel
                                            >
                                            <AlertDialogAction
                                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                @click="
                                                    deleteBackup(backup.id)
                                                "
                                            >
                                                Supprimer
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Import d'une archive exportée -->
                <div
                    v-if="allowBackupRestore"
                    class="rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Importer une archive exportée"
                        description="Restaure un fichier ZIP précédemment exporté depuis BengaDok (ou copié depuis le serveur)."
                    />
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <Label for="import-archive">Fichier ZIP</Label>
                            <Input
                                id="import-archive"
                                type="file"
                                accept=".zip,application/zip"
                                @change="onImportFileChange"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="confirm-import">
                                Tapez
                                <span class="font-mono font-semibold"
                                    >RESTAURER SAUVEGARDE</span
                                >
                                pour confirmer
                            </Label>
                            <Input
                                id="confirm-import"
                                v-model="confirmationImport"
                                class="font-mono"
                                placeholder="RESTAURER SAUVEGARDE"
                                autocomplete="off"
                            />
                        </div>
                        <Button
                            variant="destructive"
                            :disabled="
                                importing ||
                                !importFile ||
                                confirmationImport !== 'RESTAURER SAUVEGARDE'
                            "
                            @click="importBackup"
                        >
                            <Spinner v-if="importing" class="mr-2" />
                            <Upload v-else class="mr-2 size-4" />
                            Importer et restaurer
                        </Button>
                    </div>
                </div>

                <p
                    v-else
                    class="text-sm text-muted-foreground"
                >
                    La restauration n’est pas activée sur cet environnement.
                    Définissez
                    <span class="font-mono text-xs">ALLOW_BACKUP_RESTORE=true</span>
                    dans le fichier .env pour l’activer en production.
                </p>
            </div>
        </SettingsLayout>

        <FlashToastHost />
    </AppLayout>
</template>
