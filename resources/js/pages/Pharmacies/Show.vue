<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Clock,
    MapPin,
    Mail,
    Phone,
    Users,
    Plus,
    Copy,
    Lock,
    Trash2,
    ShieldAlert,
    Pencil,
    ShieldCheck,
    Shield,
    RefreshCw,
    Eye,
    EyeOff,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type UserItem = {
    id: number;
    name: string;
    username: string;
    role: string;
};

type Zone = { id: number; designation: string };
type TypePharmacie = {
    id: number;
    designation: string;
    heurs?: { ouverture: string; fermeture: string };
};

const props = defineProps<{
    pharmacie: {
        id: number;
        designation: string;
        adresse: string;
        telephone: string;
        email: string | null;
        de_garde: boolean;
        proprio_nom: string | null;
        proprio_tel: string | null;
        proprio_email: string | null;
        zone_id: number | null;
        zone: { designation: string } | null;
        type_pharmacie_id: number | null;
        type_pharmacie: { designation: string } | null;
        heurs: { ouverture: string; fermeture: string } | null;
        heure_ouverture: string;
        heure_fermeture: string;
        users: UserItem[];
    };
    zones: Zone[];
    types: TypePharmacie[];
    nextUserId?: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Pharmacies', href: '/pharmacies' },
    {
        title: props.pharmacie.designation,
        href: `/pharmacies/${props.pharmacie.id}`,
    },
];

const showEditModal = ref(false);
const modalCreateUser = ref(false);
const showPasswordManual = ref(false);
const showResetPasswordModal = ref(false);
const showRemoveUserModal = ref(false);
const userForAction = ref<UserItem | null>(null);
const formUser = ref({
    name: '',
    email: '',
    role: 'vendeur' as 'gerant' | 'vendeur',
    password: '',
    passwordMode: 'auto' as 'auto' | 'manual',
});
const form = ref({
    designation: props.pharmacie.designation,
    adresse: props.pharmacie.adresse,
    telephone: props.pharmacie.telephone,
    email: props.pharmacie.email ?? '',
    zone_id: props.pharmacie.zone_id?.toString() ?? '',
    type_pharmacie_id: props.pharmacie.type_pharmacie_id?.toString() ?? '',
    heure_ouverture: props.pharmacie.heure_ouverture ?? '08:00',
    heure_fermeture: props.pharmacie.heure_fermeture ?? '19:00',
    proprio_nom: props.pharmacie.proprio_nom ?? '',
    proprio_email: props.pharmacie.proprio_email ?? '',
    proprio_tel: props.pharmacie.proprio_tel ?? '',
});
const errors = ref<Record<string, string>>({});

const page = usePage();
const flashStatus = computed(
    () => (page.props.flash as { status?: string })?.status,
);
const createdUsername = computed(
    () => (page.props.flash as { createdUsername?: string })?.createdUsername,
);

const typeJour = computed(() =>
    props.types.find((t) => t.designation?.toLowerCase().includes('jour')),
);
const typeNuit = computed(() =>
    props.types.find((t) => t.designation?.toLowerCase().includes('nuit')),
);

function openEditModal() {
    form.value = {
        designation: props.pharmacie.designation,
        adresse: props.pharmacie.adresse,
        telephone: props.pharmacie.telephone,
        email: props.pharmacie.email ?? '',
        zone_id: props.pharmacie.zone_id?.toString() ?? '',
        type_pharmacie_id: props.pharmacie.type_pharmacie_id?.toString() ?? '',
        heure_ouverture: props.pharmacie.heure_ouverture ?? '08:00',
        heure_fermeture: props.pharmacie.heure_fermeture ?? '19:00',
        proprio_nom: props.pharmacie.proprio_nom ?? '',
        proprio_email: props.pharmacie.proprio_email ?? '',
        proprio_tel: props.pharmacie.proprio_tel ?? '',
    };
    errors.value = {};
    showEditModal.value = true;
}

function submitEdit() {
    errors.value = {};
    router.patch(`/pharmacies/${props.pharmacie.id}`, form.value, {
        preserveScroll: true,
        onError: (e) => {
            errors.value = e as Record<string, string>;
        },
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
}

function toggleGarde() {
    router.patch(
        `/pharmacies/${props.pharmacie.id}/toggle-garde`,
        {},
        { preserveScroll: true },
    );
}

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
}

function openResetPasswordModal(user: UserItem) {
    userForAction.value = user;
    showResetPasswordModal.value = true;
}

function confirmResetPassword() {
    if (!userForAction.value) return;
    router.patch(
        `/pharmacies/${props.pharmacie.id}/users/${userForAction.value.id}/reset-password`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showResetPasswordModal.value = false;
                userForAction.value = null;
            },
        },
    );
}

function openRemoveUserModal(user: UserItem) {
    userForAction.value = user;
    showRemoveUserModal.value = true;
}

function confirmRemoveUser() {
    if (!userForAction.value) return;
    router.delete(
        `/pharmacies/${props.pharmacie.id}/users/${userForAction.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                showRemoveUserModal.value = false;
                userForAction.value = null;
            },
        },
    );
}

function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/(^_|_$)/g, '');
}

function generatePassword(): string {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    let pwd = '';
    for (let i = 0; i < 8; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pwd;
}

const identifiantPreview = computed(() => {
    if (!formUser.value.name.trim()) return 'pharmacie_role_nom_?';
    const slugPharma = slugify(props.pharmacie.designation);
    const slugNom = slugify(formUser.value.name);
    const role = formUser.value.role;
    const nextId = props.nextUserId ?? 0;
    return `${slugPharma}_${role}_${slugNom}_${nextId}`;
});

function regeneratePassword() {
    formUser.value.password = generatePassword();
}

function ouvrirCreateUser() {
    errors.value = {};
    formUser.value = {
        name: '',
        email: '',
        role: 'vendeur',
        password: generatePassword(),
        passwordMode: 'auto',
    };
    modalCreateUser.value = true;
}

function copyCredentials() {
    const text = `Identifiant : ${identifiantPreview.value}\nMot de passe : ${formUser.value.password}`;
    navigator.clipboard.writeText(text);
}

function normalizeErrors(e: unknown): Record<string, string> {
    if (!e || typeof e !== 'object') return {};
    const raw = e as Record<string, unknown>;
    const errs =
        (raw.errors as Record<string, string | string[]> | undefined) ?? raw;
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(errs)) {
        const msg = Array.isArray(v) ? v[0] : v;
        if (typeof msg === 'string') {
            out[k] =
                msg.includes('identifiants') || msg.includes('credentials')
                    ? 'Votre session a peut-être expiré. Reconnectez-vous et réessayez.'
                    : msg;
        }
    }
    return out;
}

function creerUtilisateur() {
    if (
        formUser.value.passwordMode === 'manual' &&
        !formUser.value.password.trim()
    ) {
        errors.value = {
            password: 'Veuillez saisir un mot de passe (min. 8 caractères).',
        };
        return;
    }
    router.post(
        `/pharmacies/${props.pharmacie.id}/users`,
        {
            name: formUser.value.name,
            email: formUser.value.email,
            password: formUser.value.password,
            role: formUser.value.role,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                modalCreateUser.value = false;
            },
            onError: (e) => {
                errors.value = normalizeErrors(e);
            },
        },
    );
}
</script>

<template>
    <Head :title="`${pharmacie.designation} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="
                background: linear-gradient(
                    to right,
                    rgba(91, 176, 52, 0.14) 0%,
                    rgba(52, 176, 199, 0.14) 100%
                );
            "
        >
            <Button variant="link" class="w-fit -ml-2" as-child>
                <Link
                    href="/pharmacies"
                    class="flex items-center gap-2 text-[#459cd1]"
                >
                    <ArrowLeft class="size-4" />
                    Retour à la liste
                </Link>
            </Button>

            <div
                v-if="flashStatus || createdUsername"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
            >
                <p v-if="flashStatus">{{ flashStatus }}</p>
                <p v-if="createdUsername" class="mt-1 font-mono font-medium">
                    Identifiant à transmettre : {{ createdUsername }}
                </p>
            </div>

            <div
                class="rounded-xl border border-white/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/95"
            >
                <div class="mb-6 flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-sky-100 text-sky-600"
                        >
                            <Building2 class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-foreground">
                                {{ pharmacie.designation }}
                            </h1>
                            <div class="mt-1 flex gap-2">
                                <span
                                    v-if="pharmacie.de_garde"
                                    class="rounded-full bg-red-500 px-3 py-0.5 text-sm text-white"
                                >
                                    De Garde
                                </span>
                                <span
                                    v-if="pharmacie.type_pharmacie?.designation"
                                    class="rounded-full bg-blue-500 px-3 py-0.5 text-sm text-white"
                                >
                                    {{ pharmacie.type_pharmacie.designation }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <Button
                        class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                        @click="openEditModal"
                    >
                        <Pencil class="mr-2 size-4" />
                        Modifier
                    </Button>
                </div>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Coordonnées -->
                    <div
                        class="rounded-lg border border-sky-100 bg-sky-50/50 dark:border-sky-900/30 dark:bg-sky-900/10"
                    >
                        <h3
                            class="rounded-t-lg bg-sky-100 px-4 py-2 font-semibold text-sky-800 dark:bg-sky-900/50 dark:text-sky-200"
                        >
                            Coordonnées
                        </h3>
                        <div class="space-y-3 p-4">
                            <p class="flex items-center gap-2 text-sm">
                                <MapPin class="size-4 shrink-0 text-sky-600" />
                                {{ pharmacie.adresse }}
                            </p>
                            <p class="flex items-center gap-2 text-sm">
                                <Phone class="size-4 shrink-0 text-sky-600" />
                                {{ pharmacie.telephone }}
                            </p>
                            <p
                                v-if="pharmacie.email"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Mail class="size-4 shrink-0 text-sky-600" />
                                {{ pharmacie.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Horaires -->
                    <div
                        class="rounded-lg border border-sky-100 bg-sky-50/50 dark:border-sky-900/30 dark:bg-sky-900/10"
                    >
                        <h3
                            class="rounded-t-lg bg-sky-100 px-4 py-2 font-semibold text-sky-800 dark:bg-sky-900/50 dark:text-sky-200"
                        >
                            Horaires
                        </h3>
                        <div class="space-y-3 p-4">
                            <p class="flex items-center gap-2 text-sm">
                                <Clock class="size-4 shrink-0 text-sky-600" />
                                <span class="text-muted-foreground"
                                    >Ouverture :</span
                                >
                                {{ pharmacie.heurs?.ouverture ?? '-' }}
                            </p>
                            <p class="flex items-center gap-2 text-sm">
                                <Clock class="size-4 shrink-0 text-sky-600" />
                                <span class="text-muted-foreground"
                                    >Fermeture :</span
                                >
                                {{ pharmacie.heurs?.fermeture ?? '-' }}
                            </p>
                            <Button
                                v-if="pharmacie.de_garde"
                                variant="destructive"
                                size="sm"
                                @click="toggleGarde"
                            >
                                <ShieldAlert class="mr-2 size-4" />
                                Retirer Garde
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                class="border-red-300 text-red-600 hover:bg-red-50"
                                @click="toggleGarde"
                            >
                                <Plus class="mr-2 size-4" />
                                Mettre de Garde
                            </Button>
                        </div>
                    </div>

                    <!-- Propriétaire -->
                    <div
                        class="rounded-lg border border-sky-100 bg-sky-50/50 dark:border-sky-900/30 dark:bg-sky-900/10"
                    >
                        <h3
                            class="rounded-t-lg bg-sky-100 px-4 py-2 font-semibold text-sky-800 dark:bg-sky-900/50 dark:text-sky-200"
                        >
                            Propriétaire
                        </h3>
                        <div class="grid gap-2 p-4 text-sm">
                            <p>
                                <span class="text-muted-foreground">Nom :</span>
                                {{ pharmacie.proprio_nom || '-' }}
                            </p>
                            <p>
                                <span class="text-muted-foreground"
                                    >Email :</span
                                >
                                {{ pharmacie.proprio_email || '-' }}
                            </p>
                            <p>
                                <span class="text-muted-foreground"
                                    >Téléphone :</span
                                >
                                {{ pharmacie.proprio_tel || '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Utilisateurs -->
                <div
                    class="mt-6 rounded-lg border border-sky-100 dark:border-sky-900/30"
                >
                    <div
                        class="flex items-center justify-between rounded-t-lg bg-sky-100 px-4 py-2 dark:bg-sky-900/50"
                    >
                        <h3
                            class="flex items-center gap-2 font-semibold text-sky-800 dark:text-sky-200"
                        >
                            <Users class="size-4" />
                            Utilisateurs
                        </h3>
                        <Button
                            size="sm"
                            class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                            @click="ouvrirCreateUser"
                        >
                            <Plus class="mr-2 size-4" />
                            Ajouter un utilisateur
                        </Button>
                    </div>
                    <div class="divide-y p-4">
                        <div
                            v-for="user in pharmacie.users"
                            :key="user.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div>
                                <p class="font-medium">{{ user.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ user.username || '-' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                >
                                    {{ user.role || 'Utilisateur' }}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    title="Copier l'identifiant"
                                    @click="
                                        copyToClipboard(user.username || '')
                                    "
                                >
                                    <Copy class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    title="Réinitialiser le mot de passe"
                                    @click="openResetPasswordModal(user)"
                                >
                                    <Lock class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-red-600"
                                    title="Retirer de la pharmacie"
                                    @click="openRemoveUserModal(user)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <p
                            v-if="!pharmacie.users.length"
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            Aucun utilisateur associé
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal édition -->
        <Dialog :open="showEditModal" @update:open="showEditModal = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Pencil class="size-5 text-[#459cd1]" />
                        Modifier la pharmacie
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-6" @submit.prevent="submitEdit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="edit-designation"
                                >Nom de la pharmacie</Label
                            >
                            <Input
                                id="edit-designation"
                                v-model="form.designation"
                            />
                            <p
                                v-if="errors.designation"
                                class="text-sm text-red-600"
                            >
                                {{ errors.designation }}
                            </p>
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="edit-adresse">Adresse</Label>
                            <Input id="edit-adresse" v-model="form.adresse" />
                            <p
                                v-if="errors.adresse"
                                class="text-sm text-red-600"
                            >
                                {{ errors.adresse }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-telephone">Téléphone</Label>
                            <Input
                                id="edit-telephone"
                                v-model="form.telephone"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                v-model="form.email"
                                type="email"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-zone">Zone</Label>
                            <select
                                id="edit-zone"
                                v-model="form.zone_id"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            >
                                <option value="">Sélectionner</option>
                                <option
                                    v-for="z in zones"
                                    :key="z.id"
                                    :value="String(z.id)"
                                >
                                    {{ z.designation }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label>Type de pharmacie</Label>
                            <div class="flex gap-4">
                                <label
                                    class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm"
                                    :class="
                                        form.type_pharmacie_id ===
                                        (typeJour?.id?.toString() ?? '')
                                            ? 'border-amber-400 bg-amber-50'
                                            : 'border-input'
                                    "
                                >
                                    <input
                                        v-model="form.type_pharmacie_id"
                                        type="radio"
                                        :value="typeJour?.id?.toString() ?? ''"
                                    />
                                    Pharmacie de jour
                                </label>
                                <label
                                    class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm"
                                    :class="
                                        form.type_pharmacie_id ===
                                        (typeNuit?.id?.toString() ?? '')
                                            ? 'border-blue-400 bg-blue-50'
                                            : 'border-input'
                                    "
                                >
                                    <input
                                        v-model="form.type_pharmacie_id"
                                        type="radio"
                                        :value="typeNuit?.id?.toString() ?? ''"
                                    />
                                    Pharmacie de nuit
                                </label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-heure_ouverture"
                                >Heure d'ouverture</Label
                            >
                            <Input
                                id="edit-heure_ouverture"
                                v-model="form.heure_ouverture"
                                type="time"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-heure_fermeture"
                                >Heure de fermeture</Label
                            >
                            <Input
                                id="edit-heure_fermeture"
                                v-model="form.heure_fermeture"
                                type="time"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-muted-foreground"
                            >Propriétaire</Label
                        >
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="edit-proprio_nom">Nom</Label>
                                <Input
                                    id="edit-proprio_nom"
                                    v-model="form.proprio_nom"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="edit-proprio_email">Email</Label>
                                <Input
                                    id="edit-proprio_email"
                                    v-model="form.proprio_email"
                                    type="email"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="edit-proprio_tel">Téléphone</Label>
                                <Input
                                    id="edit-proprio_tel"
                                    v-model="form.proprio_tel"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditModal = false"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                        >
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal Créer un utilisateur -->
        <Dialog :open="modalCreateUser" @update:open="modalCreateUser = $event">
            <DialogContent
                class="max-h-[85vh] max-w-[min(32rem,calc(100vw-2rem))] overflow-y-auto overflow-x-hidden"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Users class="size-5 text-[#459cd1]" />
                        Créer un utilisateur
                    </DialogTitle>
                </DialogHeader>
                <p class="text-sm text-[#459cd1]">
                    Ajoutez un nouveau membre à cette pharmacie
                </p>

                <form class="space-y-6" @submit.prevent="creerUtilisateur">
                    <div class="space-y-2">
                        <Label for="user-name">Nom complet</Label>
                        <Input
                            id="user-name"
                            v-model="formUser.name"
                            required
                            placeholder="Ex: Fofana Didier"
                        />
                        <p v-if="errors.name" class="text-sm text-red-600">
                            {{ errors.name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="user-email">Email</Label>
                        <Input
                            id="user-email"
                            v-model="formUser.email"
                            type="email"
                            required
                            placeholder="Exemple@gmail.com"
                        />
                        <p v-if="errors.email" class="text-sm text-red-600">
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <Label>Définir le rôle de l'utilisateur</Label>
                        <div class="flex gap-4">
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="
                                    formUser.role === 'gerant'
                                        ? 'border-[#459cd1] bg-sky-50'
                                        : 'border-input hover:bg-muted/30'
                                "
                            >
                                <ShieldCheck class="size-8 text-[#459cd1]" />
                                <span class="font-semibold">Gérant</span>
                                <span class="text-xs text-muted-foreground"
                                    >Accès complet</span
                                >
                                <input
                                    v-model="formUser.role"
                                    type="radio"
                                    value="gerant"
                                    class="sr-only"
                                />
                            </label>
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="
                                    formUser.role === 'vendeur'
                                        ? 'border-[#459cd1] bg-sky-50'
                                        : 'border-input hover:bg-muted/30'
                                "
                            >
                                <Shield class="size-8 text-[#459cd1]" />
                                <span class="font-semibold">Vendeur</span>
                                <span class="text-xs text-muted-foreground"
                                    >Commandes uniquement</span
                                >
                                <input
                                    v-model="formUser.role"
                                    type="radio"
                                    value="vendeur"
                                    class="sr-only"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-4 space-y-4">
                        <h4 class="font-medium text-muted-foreground">
                            Identifiants
                        </h4>
                        <div class="space-y-2">
                            <Label>Identifiant (auto-généré)</Label>
                            <Input
                                :model-value="identifiantPreview"
                                readonly
                                class="bg-muted font-mono text-sm"
                            />
                            <p class="text-xs text-[#459cd1]">
                                L'aperçu affiche l'identifiant estimé (basé sur
                                le prochain ID). L'identifiant final sera
                                confirmé dans le message de succès après la
                                création.
                            </p>
                        </div>
                        <div class="space-y-3">
                            <Label>Mot de passe</Label>
                            <div class="flex gap-4">
                                <label
                                    class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm"
                                    :class="
                                        formUser.passwordMode === 'auto'
                                            ? 'border-[#459cd1] bg-sky-50'
                                            : 'border-input'
                                    "
                                >
                                    <input
                                        v-model="formUser.passwordMode"
                                        type="radio"
                                        value="auto"
                                        class="sr-only"
                                    />
                                    Générer automatiquement
                                </label>
                                <label
                                    class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm"
                                    :class="
                                        formUser.passwordMode === 'manual'
                                            ? 'border-[#459cd1] bg-sky-50'
                                            : 'border-input'
                                    "
                                >
                                    <input
                                        v-model="formUser.passwordMode"
                                        type="radio"
                                        value="manual"
                                        class="sr-only"
                                    />
                                    Saisir manuellement
                                </label>
                            </div>
                            <div
                                v-if="formUser.passwordMode === 'auto'"
                                class="flex gap-2"
                            >
                                <Input
                                    v-model="formUser.password"
                                    readonly
                                    type="text"
                                    class="flex-1 font-mono"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    title="Régénérer le mot de passe"
                                    @click="regeneratePassword"
                                >
                                    <RefreshCw class="size-4" />
                                </Button>
                            </div>
                            <div v-else class="relative">
                                <Input
                                    v-model="formUser.password"
                                    :type="
                                        showPasswordManual ? 'text' : 'password'
                                    "
                                    placeholder="Min. 8 caractères"
                                    class="font-mono pr-10"
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    :aria-label="
                                        showPasswordManual
                                            ? 'Masquer le mot de passe'
                                            : 'Afficher le mot de passe'
                                    "
                                    @click="
                                        showPasswordManual = !showPasswordManual
                                    "
                                >
                                    <EyeOff
                                        v-if="showPasswordManual"
                                        class="size-4"
                                    />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>
                            <p
                                v-if="errors.password"
                                class="text-sm text-red-600"
                            >
                                {{ errors.password }}
                            </p>
                            <p class="text-xs text-[#459cd1]">
                                {{
                                    formUser.passwordMode === 'auto'
                                        ? "L'utilisateur devra changer ce mot de passe à la première connexion"
                                        : "Choisissez un mot de passe sécurisé à transmettre à l'utilisateur"
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-sky-50 p-4 dark:bg-sky-950/30">
                        <h4
                            class="mb-2 font-medium text-sky-800 dark:text-sky-200"
                        >
                            Identifiants à transmettre
                        </h4>
                        <p class="text-sm text-muted-foreground">
                            Identifiant :
                            <span class="font-mono">{{
                                identifiantPreview
                            }}</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Mot de passe :
                            <span class="font-mono">{{
                                formUser.password
                            }}</span>
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="mt-3 border-sky-300 text-sky-700 hover:bg-sky-100"
                            @click="copyCredentials"
                        >
                            <Copy class="mr-2 size-4" />
                            Copier les identifiants
                        </Button>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="destructive"
                            @click="modalCreateUser = false"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                        >
                            Créer l'utilisateur
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmModal
            :open="showResetPasswordModal"
            title="Réinitialiser le mot de passe"
            :description="
                userForAction
                    ? `Réinitialiser le mot de passe de ${userForAction.name} ? Un nouveau mot de passe sera généré.`
                    : ''
            "
            confirm-text="Réinitialiser"
            variant="default"
            @update:open="showResetPasswordModal = $event"
            @confirm="confirmResetPassword"
        />
        <ConfirmModal
            :open="showRemoveUserModal"
            title="Retirer l'utilisateur"
            :description="
                userForAction
                    ? `Retirer ${userForAction.name} de cette pharmacie ? L'utilisateur ne pourra plus y accéder.`
                    : ''
            "
            confirm-text="Retirer"
            @update:open="showRemoveUserModal = $event"
            @confirm="confirmRemoveUser"
        />
    </AppLayout>
</template>
