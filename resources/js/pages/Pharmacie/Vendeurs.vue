<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Users,
    ShieldCheck,
    Shield,
    RefreshCw,
    CheckCircle2,
    X,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    moduleCardClass,
    modulePrimaryButtonSolidClass,
    modulePrimarySelectedCardClass,
    modulePrimaryTextClass,
} from '@/lib/bengadokUi';
import { Label } from '@/components/ui/label';
import AppToast from '@/components/AppToast.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
import IdentifiantsCreesDialog from '@/components/IdentifiantsCreesDialog.vue';
import { previewPharmacieUsername } from '@/lib/laravelSlug';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';

const props = defineProps<{
    vendeurs: Array<{
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        username?: string;
    }>;
    pharmacie: { id: number; designation: string };
    nextUserId?: number;
}>();

const modalCreate = ref(false);
const formErrors = ref<Record<string, string>>({});
const createSuccessToast = ref<{
    show: boolean;
    title: string;
    description?: string;
}>({ show: false, title: '' });
const credentialsDialogOpen = ref(false);
const lastCreatedCredentials = ref<{
    username: string;
    password: string;
} | null>(null);
const form = ref({
    name: '',
    email: '',
    phone: '',
    role: 'vendeur' as 'gerant' | 'vendeur',
    password: '',
});

const identifiantPreview = computed(() =>
    previewPharmacieUsername(
        props.pharmacie.designation,
        form.value.role,
        form.value.name,
        props.nextUserId ?? 0,
    ),
);

function generatePassword(): string {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    let pwd = '';
    for (let i = 0; i < 8; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pwd;
}

function regeneratePassword() {
    form.value.password = generatePassword();
}

function resetCreateForm() {
    formErrors.value = {};
    form.value = {
        name: '',
        email: '',
        phone: '',
        role: 'vendeur',
        password: generatePassword(),
    };
}

function ouvrirCreate() {
    resetCreateForm();
    router.reload({
        only: ['nextUserId'],
        preserveScroll: true,
        onFinish: () => {
            modalCreate.value = true;
        },
    });
}

function creerVendeur() {
    formErrors.value = {};
    router.post(
        '/pharmacie/vendeurs',
        {
            name: form.value.name,
            email: form.value.email || undefined,
            phone: form.value.phone,
            password: form.value.password,
            role: form.value.role,
        },
        {
            onSuccess: (page) => {
                modalCreate.value = false;
                const flash = (
                    page.props as {
                        flash?: {
                            status?: string;
                            createdUsername?: string;
                            createdPassword?: string;
                        };
                    }
                ).flash;
                if (!flash?.createdUsername) {
                    resetCreateForm();
                    return;
                }
                const password =
                    flash.createdPassword ?? form.value.password;
                lastCreatedCredentials.value = {
                    username: flash.createdUsername,
                    password,
                };
                resetCreateForm();
                credentialsDialogOpen.value = true;
                createSuccessToast.value = {
                    show: true,
                    title:
                        flash.status?.trim() ||
                        'Utilisateur créé. Copiez les identifiants ci-dessous.',
                    description: `Identifiant : ${flash.createdUsername}`,
                };
            },
            onError: (e) => {
                formErrors.value = e as Record<string, string>;
            },
        },
    );
}
</script>

<template>
    <Head title="Vendeurs - BengaDok" />

    <PharmacyLayout page-title="Vendeurs">
        <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">
                    Vendeurs - {{ pharmacie?.designation }}
                </h1>
                <Button
                    :class="modulePrimaryButtonSolidClass"
                    @click="ouvrirCreate"
                >
                    <Users class="mr-2 size-4" />
                    Créer un utilisateur
                </Button>
            </div>

            <div :class="moduleCardClass">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nom</th>
                            <th class="px-4 py-3 text-left font-medium">
                                Email
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Téléphone
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Identifiant
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="v in vendeurs"
                            :key="v.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">{{ v.name }}</td>
                            <td class="px-4 py-3">{{ v.email || '—' }}</td>
                            <td class="px-4 py-3">{{ v.phone || '—' }}</td>
                            <td
                                class="px-4 py-3 font-mono text-muted-foreground"
                            >
                                {{ v.username || '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!vendeurs?.length"
                    class="py-8 text-center text-muted-foreground"
                >
                    Aucun vendeur. Cliquez sur "Créer un utilisateur" pour en
                    ajouter.
                </p>
            </div>
        </div>

        <!-- Modal Créer un utilisateur -->
        <Dialog :open="modalCreate" @update:open="modalCreate = $event">
            <DialogContent
                class="flex max-h-[min(92vh,36rem)] max-w-lg flex-col gap-0 overflow-hidden p-0"
            >
                <DialogHeader class="shrink-0 space-y-1 border-b px-5 py-4">
                    <DialogTitle class="flex items-center gap-2 text-base">
                        <Users class="size-5" :class="modulePrimaryTextClass" />
                        Créer un utilisateur
                    </DialogTitle>
                    <p class="text-xs text-muted-foreground">
                        Nouveau membre — {{ pharmacie?.designation }}
                    </p>
                </DialogHeader>

                <form
                    class="flex min-h-0 flex-1 flex-col"
                    @submit.prevent="creerVendeur"
                >
                    <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        <div class="space-y-1.5">
                            <Label for="name" class="text-sm">Nom complet</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                required
                                class="h-9"
                                placeholder="Ex: Fofana Didier"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="vendeur-phone" class="text-sm"
                                    >Téléphone</Label
                                >
                                <Input
                                    id="vendeur-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    required
                                    autocomplete="tel"
                                    class="h-9"
                                    placeholder="+242 06 123 45 67"
                                />
                                <p
                                    v-if="formErrors.phone"
                                    class="text-xs text-red-600"
                                >
                                    {{ formErrors.phone }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="email" class="text-sm"
                                    >Email (facultatif)</Label
                                >
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    class="h-9"
                                    placeholder="Optionnel"
                                />
                                <p
                                    v-if="formErrors.email"
                                    class="text-xs text-red-600"
                                >
                                    {{ formErrors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm">Rôle</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border-2 px-3 py-2 transition-colors"
                                    :class="
                                        form.role === 'gerant'
                                            ? modulePrimarySelectedCardClass
                                            : 'border-input hover:bg-muted/30'
                                    "
                                >
                                    <ShieldCheck
                                        class="size-5 shrink-0"
                                        :class="modulePrimaryTextClass"
                                    />
                                    <div class="min-w-0">
                                        <span
                                            class="block text-sm font-semibold leading-tight"
                                            >Gérant</span
                                        >
                                        <span
                                            class="block text-[10px] text-muted-foreground"
                                            >Accès complet</span
                                        >
                                    </div>
                                    <input
                                        v-model="form.role"
                                        type="radio"
                                        value="gerant"
                                        class="sr-only"
                                    />
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border-2 px-3 py-2 transition-colors"
                                    :class="
                                        form.role === 'vendeur'
                                            ? modulePrimarySelectedCardClass
                                            : 'border-input hover:bg-muted/30'
                                    "
                                >
                                    <Shield
                                        class="size-5 shrink-0"
                                        :class="modulePrimaryTextClass"
                                    />
                                    <div class="min-w-0">
                                        <span
                                            class="block text-sm font-semibold leading-tight"
                                            >Vendeur</span
                                        >
                                        <span
                                            class="block text-[10px] text-muted-foreground"
                                            >Commandes</span
                                        >
                                    </div>
                                    <input
                                        v-model="form.role"
                                        type="radio"
                                        value="vendeur"
                                        class="sr-only"
                                    />
                                </label>
                            </div>
                        </div>

                        <div
                            class="space-y-2 rounded-lg border border-border/60 bg-muted/40 p-3"
                        >
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Identifiant (aperçu)</Label
                                >
                                <input
                                    type="text"
                                    readonly
                                    tabindex="-1"
                                    :value="identifiantPreview"
                                    class="flex h-8 w-full rounded-md border border-input bg-muted/80 px-2.5 font-mono text-xs"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Mot de passe temporaire</Label
                                >
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        tabindex="-1"
                                        :value="form.password"
                                        class="h-8 min-w-0 flex-1 rounded-md border border-input bg-background px-2.5 font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="size-8 shrink-0"
                                        title="Régénérer le mot de passe"
                                        @click="regeneratePassword"
                                    >
                                        <RefreshCw class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                            <p class="text-[10px] leading-snug text-muted-foreground">
                                L'identifiant exact et le mot de passe seront
                                affichés dans une fenêtre de confirmation après
                                création.
                            </p>
                        </div>
                    </div>

                    <DialogFooter
                        class="shrink-0 gap-2 border-t bg-background px-5 py-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="modalCreate = false"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            :class="modulePrimaryButtonSolidClass"
                        >
                            Créer l'utilisateur
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <IdentifiantsCreesDialog
            v-if="lastCreatedCredentials"
            v-model:open="credentialsDialogOpen"
            :username="lastCreatedCredentials.username"
            :password="lastCreatedCredentials.password"
        />

        <AppToast
            v-model:show="createSuccessToast.show"
            :title="createSuccessToast.title"
            :description="createSuccessToast.description"
            :duration-ms="10000"
        />

        <FlashToastHost />
    </PharmacyLayout>
</template>
