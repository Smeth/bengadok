<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bell, Volume2 } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import SettingsPageShell from '@/components/settings/SettingsPageShell.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useOrderAlertPreferencesSettings } from '@/composables/useOrderAlertPreferencesSettings';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Alertes commandes',
        href: '/settings/alertes',
    },
];

const {
    isBackofficePortalUser,
    soundEnabled,
    soundPreset,
    soundPresetOptions,
    browserPermission,
    statusMessage,
    statusVariant,
    permissionLabel,
    setSoundEnabled,
    setSoundPreset,
    requestBrowserNotifications,
    showReminderBannerAgain,
    testAlertSound,
} = useOrderAlertPreferencesSettings();
</script>

<template>
    <SettingsPageShell :breadcrumbs="breadcrumbItems">
        <Head title="Alertes commandes" />

        <h1 class="sr-only">Alertes commandes</h1>

        <SettingsLayout>
            <div class="max-w-2xl space-y-8">
                <Heading
                    variant="small"
                    title="Alertes commandes"
                    description="Gérez le son, les notifications navigateur et le rappel d’activation pour ne manquer aucune commande."
                />

                <section class="space-y-4 rounded-xl border bg-card p-5">
                    <div class="flex items-start gap-3">
                        <Volume2
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1 space-y-3">
                            <div>
                                <h2 class="text-sm font-semibold">
                                    Son des alertes
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Bip sonore lors d’une nouvelle commande ou
                                    d’un retour pharmacie (les toasts restent
                                    affichés même si le son est coupé).
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <Checkbox
                                    id="order-alert-sound"
                                    :checked="soundEnabled"
                                    @update:checked="
                                        (value) =>
                                            setSoundEnabled(value === true)
                                    "
                                />
                                <Label for="order-alert-sound">
                                    Activer le son des alertes
                                </Label>
                            </div>

                            <div class="space-y-2">
                                <Label for="order-alert-sound-preset">
                                    Type de signal
                                </Label>
                                <select
                                    id="order-alert-sound-preset"
                                    :value="soundPreset"
                                    class="flex h-9 w-full max-w-sm rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:border-ring focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    @change="
                                        setSoundPreset(
                                            ($event.target as HTMLSelectElement)
                                                .value as typeof soundPreset,
                                        )
                                    "
                                >
                                    <option
                                        v-for="opt in soundPresetOptions"
                                        :key="opt.id"
                                        :value="opt.id"
                                    >
                                        {{ opt.label }} — {{ opt.description }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Enregistré dans ce navigateur uniquement
                                    (chaque poste peut choisir un signal
                                    différent).
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="testAlertSound"
                            >
                                Tester le son
                            </Button>
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border bg-card p-5">
                    <div class="flex items-start gap-3">
                        <Bell
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1 space-y-3">
                            <div>
                                <h2 class="text-sm font-semibold">
                                    Notifications navigateur
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Popups système lorsque l’onglet BengaDok
                                    n’est pas au premier plan.
                                </p>
                            </div>

                            <p class="text-sm">
                                État actuel :
                                <span
                                    class="font-medium"
                                    :class="{
                                        'text-emerald-700':
                                            browserPermission === 'granted',
                                        'text-red-700':
                                            browserPermission === 'denied',
                                        'text-amber-700':
                                            browserPermission === 'default',
                                    }"
                                >
                                    {{ permissionLabel() }}
                                </span>
                            </p>

                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    :disabled="
                                        browserPermission === 'unsupported' ||
                                        browserPermission === 'granted'
                                    "
                                    @click="requestBrowserNotifications"
                                >
                                    Autoriser les notifications
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showReminderBannerAgain"
                                >
                                    Réafficher le rappel d’activation
                                </Button>
                            </div>

                            <p
                                v-if="browserPermission === 'denied'"
                                class="text-sm text-muted-foreground"
                            >
                                Si vous avez refusé les notifications, ouvrez
                                les paramètres du site dans la barre d’adresse
                                (icône cadenas ou « i ») → Notifications →
                                Autoriser, puis rechargez la page.
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    v-if="isBackofficePortalUser"
                    class="rounded-xl border border-dashed bg-muted/30 p-4 text-sm text-muted-foreground"
                >
                    <p>
                        Back-office : alertes pour les
                        <strong>retours pharmacie</strong> et les
                        <strong>nouvelles commandes</strong> à traiter.
                    </p>
                </section>

                <p
                    v-if="statusMessage"
                    class="rounded-lg px-3 py-2 text-sm"
                    :class="{
                        'bg-emerald-50 text-emerald-900':
                            statusVariant === 'success',
                        'bg-red-50 text-red-900': statusVariant === 'error',
                        'bg-sky-50 text-sky-900': statusVariant === 'info',
                    }"
                    role="status"
                >
                    {{ statusMessage }}
                </p>
            </div>
        </SettingsLayout>
    </SettingsPageShell>
</template>
