<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Pencil,
    Lock,
    Trash2,
    ShieldCheck,
    Users,
    Eye,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    email: string;
    username?: string;
    phone?: string | null;
    role: {
        name: string;
        label: string;
        permissions?: Array<{ name: string; label: string }>;
    } | null;
    permissions: string[];
    permission_names?: string[];
    direct_permissions?: string[];
    role_permission_names?: string[];
};

type RoleOption = {
    id: number;
    name: string;
    label: string;
    description: string;
    permissions: Array<{ name: string; label: string }>;
};

type PermissionsGroupées = Record<
    string,
    Array<{ name: string; label: string }>
>;

const props = defineProps<{
    users: UserItem[];
    roles: RoleOption[];
    permissionLabels: Record<string, string>;
    permissionsGroupées?: PermissionsGroupées;
    filters?: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Utilisateurs Backoffice', href: '/utilisateurs' },
];

const searchQuery = ref(props.filters?.search ?? '');
const modalCreate = ref(false);
const modalEdit = ref(false);
const modalDetail = ref(false);
const modalGestionAcces = ref(false);
const showDeleteUserModal = ref(false);
const userToDelete = ref<UserItem | null>(null);
const userToEdit = ref<UserItem | null>(null);
const userDetail = ref<UserItem | null>(null);
const userGestionAcces = ref<UserItem | null>(null);
const formGestionAcces = ref<Record<string, boolean>>({});
const formCreate = ref({
    name: '',
    email: '',
    username: '',
    phone: '',
    password: '',
    role: 'admin',
});
const formEdit = ref({
    name: '',
    email: '',
    username: '',
    phone: '',
    role: 'admin',
});

const selectedRole = computed(
    () =>
        props.roles.find((r) => r.name === formCreate.value.role) ||
        props.roles.find((r) => r.name === formEdit.value.role),
);

function ouvrirCreate() {
    formCreate.value = {
        name: '',
        email: '',
        username: '',
        phone: '',
        password: '',
        role: 'admin',
    };
    modalCreate.value = true;
}

function ouvrirEdit(user: UserItem) {
    userToEdit.value = user;
    formEdit.value = {
        name: user.name,
        email: user.email,
        username: user.username ?? '',
        phone: user.phone ?? '',
        role: user.role?.name ?? 'admin',
    };
    modalEdit.value = true;
}

function ouvrirDetail(user: UserItem) {
    userDetail.value = user;
    modalDetail.value = true;
}

function ouvrirGestionAcces(user: UserItem) {
    userGestionAcces.value = user;
    const current = new Set(user.permission_names ?? []);
    const allPerms: string[] = [];
    for (const perms of Object.values(props.permissionsGroupées ?? {})) {
        for (const p of perms) allPerms.push(p.name);
    }
    formGestionAcces.value = Object.fromEntries(
        allPerms.map((p) => [p, current.has(p)]),
    );
    modalGestionAcces.value = true;
}

function toggleGestionPerm(name: string) {
    formGestionAcces.value[name] = !formGestionAcces.value[name];
}

function getDirectPermissionsToSync(): string[] {
    if (!userGestionAcces.value) return [];
    const rolePerms = new Set(
        userGestionAcces.value.role_permission_names ?? [],
    );
    return Object.entries(formGestionAcces.value)
        .filter(([, checked]) => checked)
        .map(([name]) => name)
        .filter((name) => !rolePerms.has(name));
}

function soumettreGestionAcces() {
    if (!userGestionAcces.value) return;
    const perms = getDirectPermissionsToSync();
    router.patch(
        `/utilisateurs/${userGestionAcces.value.id}/permissions`,
        { permissions: perms },
        {
            preserveScroll: true,
            onSuccess: () => {
                modalGestionAcces.value = false;
                userGestionAcces.value = null;
                formGestionAcces.value = {};
            },
        },
    );
}

function creerUtilisateur() {
    router.post('/utilisateurs', formCreate.value);
    modalCreate.value = false;
}

function modifierUtilisateur() {
    if (!userToEdit.value) return;
    router.patch(`/utilisateurs/${userToEdit.value.id}`, formEdit.value);
    modalEdit.value = false;
    userToEdit.value = null;
}

function openDeleteUserModal(user: UserItem) {
    userToDelete.value = user;
    showDeleteUserModal.value = true;
}

function confirmDeleteUser() {
    if (!userToDelete.value) return;
    router.delete(`/utilisateurs/${userToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteUserModal.value = false;
            userToDelete.value = null;
        },
    });
}

function permissionsAffichage(permissions: string[], maxVisible = 3) {
    const visibles = permissions.slice(0, maxVisible);
    const autres = permissions.length - maxVisible;
    return { visibles, autres };
}

function rechercher() {
    router.get(
        '/utilisateurs',
        { search: searchQuery.value.trim() || undefined },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Utilisateurs Backoffice - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Utilisateurs Backoffice</h1>
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1.5"
                    >
                        <Users class="size-4 text-primary" />
                        <span class="text-sm font-medium text-primary">{{
                            users.length
                        }}</span>
                    </div>
                    <Button @click="ouvrirCreate">
                        <Plus class="size-4" />
                        Nouvel Utilisateur
                    </Button>
                </div>
            </div>

            <div class="relative">
                <Search
                    class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    placeholder="Rechercher un utilisateur..."
                    class="pl-9"
                    @keyup.enter="rechercher"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="flex flex-col rounded-xl border bg-card p-4 shadow-sm transition-shadow hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="font-semibold truncate">{{
                                    user.name
                                }}</span>
                                <span
                                    v-if="user.role"
                                    class="shrink-0 rounded-full bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary"
                                >
                                    {{ user.role.label }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground truncate">
                                {{ user.email }}
                            </p>
                            <p
                                v-if="user.phone"
                                class="text-xs text-muted-foreground truncate"
                            >
                                {{ user.phone }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                title="Détails"
                                @click="ouvrirDetail(user)"
                            >
                                <Eye class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                @click="ouvrirEdit(user)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                title="Gestion accès"
                                @click="ouvrirGestionAcces(user)"
                            >
                                <Lock class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 text-destructive hover:text-destructive"
                                @click="openDeleteUserModal(user)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <template
                            v-for="(perm, i) in permissionsAffichage(
                                user.permissions,
                            ).visibles"
                            :key="i"
                        >
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground"
                            >
                                {{ perm }}
                            </span>
                        </template>
                        <span
                            v-if="
                                permissionsAffichage(user.permissions).autres >
                                0
                            "
                            class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground"
                        >
                            +
                            {{ permissionsAffichage(user.permissions).autres }}
                            autres
                        </span>
                    </div>
                </div>
            </div>

            <p
                v-if="!users.length"
                class="py-12 text-center text-muted-foreground"
            >
                Aucun utilisateur backoffice trouvé.
            </p>
        </div>

        <!-- Modal Créer -->
        <Dialog :open="modalCreate" @update:open="modalCreate = $event">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Users class="size-5" />
                        Créer un utilisateur Backoffice
                    </DialogTitle>
                    <p class="text-sm text-muted-foreground">
                        Ajoutez un nouveau membre de l'équipe d'administration
                    </p>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="creerUtilisateur">
                    <div class="space-y-2">
                        <Label>
                            Nom complet
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="formCreate.name"
                            required
                            placeholder="Ex : Fofana Didier"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>
                            Email
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="formCreate.email"
                            type="email"
                            required
                            placeholder="Ex : marc@bengadok.cg"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Nom d'utilisateur</Label>
                        <Input
                            v-model="formCreate.username"
                            placeholder="Ex : FofDi"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Téléphone</Label>
                        <Input
                            v-model="formCreate.phone"
                            type="tel"
                            autocomplete="tel"
                            placeholder="Ex : +242 06 000 00 00"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>
                            Mot de passe
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="formCreate.password"
                            type="password"
                            required
                            placeholder="Minimum 8 caractères"
                        />
                    </div>

                    <div class="space-y-3">
                        <Label>
                            Rôles et permissions
                            <span class="text-destructive">*</span>
                        </Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="role in roles"
                                :key="role.name"
                                type="button"
                                class="relative flex flex-col items-start rounded-lg border p-3 text-left transition-colors"
                                :class="
                                    formCreate.role === role.name
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50'
                                "
                                @click="formCreate.role = role.name"
                            >
                                <ShieldCheck
                                    v-if="formCreate.role === role.name"
                                    class="absolute right-2 top-2 size-4 text-primary"
                                />
                                <span class="font-medium">{{
                                    role.label
                                }}</span>
                                <span
                                    class="mt-1 text-xs text-muted-foreground"
                                    >{{ role.description }}</span
                                >
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedRole" class="space-y-2">
                        <Label>Permissions accordées :</Label>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in selectedRole.permissions"
                                :key="perm.name"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs text-emerald-800"
                            >
                                {{ perm.label }}
                            </span>
                        </div>
                    </div>

                    <DialogFooter class="flex gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-destructive text-destructive"
                            @click="modalCreate = false"
                        >
                            Annuler
                        </Button>
                        <Button type="submit">Créer l'utilisateur</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal Éditer -->
        <Dialog
            :open="modalEdit"
            @update:open="
                (v) => {
                    if (!v) {
                        modalEdit = false;
                        userToEdit = null;
                    }
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Pencil class="size-5" />
                        Modifier l'utilisateur
                    </DialogTitle>
                </DialogHeader>
                <form
                    class="space-y-4"
                    @submit.prevent="modifierUtilisateur"
                    v-if="userToEdit"
                >
                    <div class="space-y-2">
                        <Label>Nom complet</Label>
                        <Input v-model="formEdit.name" required />
                    </div>
                    <div class="space-y-2">
                        <Label>Email</Label>
                        <Input v-model="formEdit.email" type="email" required />
                    </div>
                    <div class="space-y-2">
                        <Label>Nom d'utilisateur</Label>
                        <Input v-model="formEdit.username" />
                    </div>
                    <div class="space-y-2">
                        <Label>Téléphone</Label>
                        <Input
                            v-model="formEdit.phone"
                            type="tel"
                            autocomplete="tel"
                            placeholder="Ex : +242 06 000 00 00"
                        />
                    </div>
                    <div class="space-y-3">
                        <Label>Rôle</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="role in roles"
                                :key="role.name"
                                type="button"
                                class="relative flex flex-col items-start rounded-lg border p-3 text-left transition-colors"
                                :class="
                                    formEdit.role === role.name
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50'
                                "
                                @click="formEdit.role = role.name"
                            >
                                <ShieldCheck
                                    v-if="formEdit.role === role.name"
                                    class="absolute right-2 top-2 size-4 text-primary"
                                />
                                <span class="font-medium">{{
                                    role.label
                                }}</span>
                                <span
                                    class="mt-1 text-xs text-muted-foreground"
                                    >{{ role.description }}</span
                                >
                            </button>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="modalEdit = false"
                        >
                            Annuler
                        </Button>
                        <Button type="submit">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal Détails utilisateur -->
        <Dialog :open="modalDetail" @update:open="modalDetail = $event">
            <DialogContent class="max-w-md">
                <DialogHeader v-if="userDetail">
                    <DialogTitle class="flex items-center gap-2">
                        <Users class="size-5" />
                        {{ userDetail.name }}
                    </DialogTitle>
                </DialogHeader>
                <div v-if="userDetail" class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        {{ userDetail.email }}
                    </p>
                    <p
                        v-if="userDetail.username"
                        class="text-sm text-muted-foreground"
                    >
                        @{{ userDetail.username }}
                    </p>
                    <p
                        v-if="userDetail.phone"
                        class="text-sm text-muted-foreground"
                    >
                        {{ userDetail.phone }}
                    </p>
                    <div v-if="userDetail.role" class="flex items-center gap-2">
                        <ShieldCheck class="size-4 text-primary" />
                        <span
                            class="rounded-full bg-primary/15 px-2 py-0.5 text-sm font-medium text-primary"
                        >
                            {{ userDetail.role.label }}
                        </span>
                    </div>
                    <div class="space-y-2">
                        <Label>Permissions</Label>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(perm, i) in userDetail.permissions"
                                :key="i"
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs"
                            >
                                {{ perm }}
                            </span>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="modalDetail = false"
                            >Fermer</Button
                        >
                        <Button
                            @click="
                                ouvrirEdit(userDetail);
                                modalDetail = false;
                            "
                            >Modifier</Button
                        >
                        <Button
                            variant="secondary"
                            @click="
                                ouvrirGestionAcces(userDetail);
                                modalDetail = false;
                            "
                        >
                            Gestion accès
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Modal Gestion accès -->
        <Dialog
            :open="modalGestionAcces"
            @update:open="
                (v) => {
                    if (!v) {
                        modalGestionAcces = false;
                        userGestionAcces = null;
                    }
                }
            "
        >
            <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader v-if="userGestionAcces">
                    <DialogTitle class="flex items-center gap-2">
                        <Lock class="size-5" />
                        Gestion des accès – {{ userGestionAcces.name }}
                    </DialogTitle>
                    <p class="text-sm text-muted-foreground">
                        Permissions supplémentaires (en plus du rôle). Les
                        permissions du rôle ne peuvent pas être retirées ici.
                    </p>
                </DialogHeader>
                <form
                    v-if="userGestionAcces"
                    class="space-y-4"
                    @submit.prevent="soumettreGestionAcces"
                >
                    <div
                        v-for="(perms, cat) in permissionsGroupées ?? {}"
                        :key="cat"
                        class="space-y-2"
                    >
                        <Label class="text-muted-foreground">{{ cat }}</Label>
                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="p in perms"
                                :key="p.name"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                                :class="[
                                    formGestionAcces[p.name]
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50',
                                    (
                                        userGestionAcces.role_permission_names ??
                                        []
                                    ).includes(p.name)
                                        ? 'opacity-75'
                                        : '',
                                ]"
                            >
                                <input
                                    type="checkbox"
                                    :checked="formGestionAcces[p.name]"
                                    :disabled="
                                        (
                                            userGestionAcces.role_permission_names ??
                                            []
                                        ).includes(p.name)
                                    "
                                    class="rounded"
                                    @change="toggleGestionPerm(p.name)"
                                />
                                {{ p.label }}
                                <span
                                    v-if="
                                        (
                                            userGestionAcces.role_permission_names ??
                                            []
                                        ).includes(p.name)
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    (rôle)
                                </span>
                            </label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="modalGestionAcces = false"
                            >Annuler</Button
                        >
                        <Button type="submit">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmModal
            :open="showDeleteUserModal"
            title="Supprimer l'utilisateur"
            :description="
                userToDelete
                    ? `Supprimer l'utilisateur ${userToDelete.name} ? Cette action est irréversible.`
                    : ''
            "
            confirm-text="Supprimer"
            variant="destructive"
            @update:open="showDeleteUserModal = $event"
            @confirm="confirmDeleteUser"
        />
    </AppLayout>
</template>
