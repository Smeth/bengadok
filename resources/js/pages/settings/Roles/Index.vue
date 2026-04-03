<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Pencil,
    Trash2,
    ShieldCheck,
    Shield,
    Users,
    Check,
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
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type RoleItem = {
    id: number;
    name: string;
    label: string;
    description: string;
    permissions_count: number;
    users_count: number;
    permissions: Array<{ name: string; label: string }>;
    is_system: boolean;
};

type PermissionsGroupées = Record<
    string,
    Array<{ name: string; label: string }>
>;

const props = defineProps<{
    rolesBackoffice: RoleItem[];
    rolesPharmacie: RoleItem[];
    permissionsGroupées: PermissionsGroupées;
    allPermissions: string[];
    permissionLabels: Record<string, string>;
    filters: { search?: string; onglet?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Mon profil', href: '/settings/profile' },
    { title: 'Gestion des rôles', href: '/settings/roles' },
];

const searchQuery = ref(props.filters.search ?? '');
const activeTab = ref<'backoffice' | 'pharmacie'>(
    props.filters.onglet === 'pharmacie' ? 'pharmacie' : 'backoffice',
);
const modalCreate = ref(false);
const modalDetail = ref(false);
const modalEdit = ref(false);
const roleDetail = ref<RoleItem | null>(null);
const roleToEdit = ref<RoleItem | null>(null);
const showDeleteRoleModal = ref(false);
const roleToDelete = ref<RoleItem | null>(null);

const formCreate = ref({
    name: '',
    description: '',
    type: 'backoffice' as 'backoffice' | 'pharmacie',
    permissions: [] as string[],
});
const formEdit = ref({
    permissions: [] as string[],
});

const rolesDisplay = computed(() =>
    activeTab.value === 'backoffice'
        ? props.rolesBackoffice
        : props.rolesPharmacie,
);

const filteredRoles = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return rolesDisplay.value;
    return rolesDisplay.value.filter(
        (r) =>
            r.label.toLowerCase().includes(q) ||
            r.name.toLowerCase().includes(q) ||
            r.description.toLowerCase().includes(q),
    );
});

function filtrer() {
    router.get(
        '/settings/roles',
        {
            search: searchQuery.value.trim() || undefined,
            onglet: activeTab.value,
        },
        { preserveState: true },
    );
}

function ouvrirCreate() {
    formCreate.value = {
        name: '',
        description: '',
        type: activeTab.value,
        permissions: [],
    };
    modalCreate.value = true;
}

function ouvrirDetail(role: RoleItem) {
    roleDetail.value = role;
    modalDetail.value = true;
}

function ouvrirEdit(role: RoleItem) {
    roleToEdit.value = role;
    formEdit.value = { permissions: role.permissions.map((p) => p.name) };
    modalEdit.value = true;
}

function toggleCreatePermission(name: string) {
    const i = formCreate.value.permissions.indexOf(name);
    if (i >= 0) formCreate.value.permissions.splice(i, 1);
    else formCreate.value.permissions.push(name);
}

function toggleEditPermission(name: string) {
    const i = formEdit.value.permissions.indexOf(name);
    if (i >= 0) formEdit.value.permissions.splice(i, 1);
    else formEdit.value.permissions.push(name);
}

function creerRole() {
    if (!formCreate.value.name.trim()) return;
    router.post(
        '/settings/roles',
        {
            name: formCreate.value.name.trim(),
            description: formCreate.value.description.trim() || null,
            type: formCreate.value.type,
            permissions: formCreate.value.permissions,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                modalCreate.value = false;
            },
        },
    );
}

function modifierRole() {
    if (!roleToEdit.value) return;
    router.patch(`/settings/roles/${roleToEdit.value.id}`, formEdit.value, {
        preserveScroll: true,
        onSuccess: () => {
            modalEdit.value = false;
            roleToEdit.value = null;
        },
    });
}

function openDeleteRoleModal(role: RoleItem) {
    if (role.is_system) return;
    if (role.users_count > 0) {
        alert('Impossible de supprimer : des utilisateurs ont ce rôle.');
        return;
    }
    roleToDelete.value = role;
    showDeleteRoleModal.value = true;
}

function confirmDeleteRole() {
    if (!roleToDelete.value) return;
    router.delete(`/settings/roles/${roleToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteRoleModal.value = false;
            roleToDelete.value = null;
            modalDetail.value = false;
            roleDetail.value = null;
        },
    });
}

/** Permissions à afficher dans l'aperçu : si "gérer-tout" est présent, on n'affiche que celle-ci pour éviter la redondance */
function permissionsApercu(
    role: RoleItem,
): Array<{ name: string; label: string }> {
    const gererTout = role.permissions.find((p) => p.name === 'gérer-tout');
    if (gererTout) {
        return [gererTout];
    }
    return role.permissions.slice(0, 5);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Gestion des rôles - BengaDok" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold">Gestion des rôles</h2>
                    <Button @click="ouvrirCreate">
                        <Plus class="mr-2 size-4" />
                        Nouveau Rôle
                    </Button>
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'backoffice'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/80'
                        "
                        @click="
                            activeTab = 'backoffice';
                            filtrer();
                        "
                    >
                        Rôles Utilisateurs Backoffice
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'pharmacie'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/80'
                        "
                        @click="
                            activeTab = 'pharmacie';
                            filtrer();
                        "
                    >
                        Rôles agents pharmacie
                    </button>
                </div>

                <div class="relative">
                    <Search
                        class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher un rôle"
                        class="pl-9"
                        @keyup.enter="filtrer"
                    />
                </div>

                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="role in filteredRoles"
                        :key="role.id"
                        class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div
                                    class="mb-2 flex flex-wrap items-center gap-2"
                                >
                                    <Shield
                                        class="size-5 shrink-0 text-primary"
                                    />
                                    <span
                                        class="min-w-0 break-words font-semibold"
                                        >{{ role.label }}</span
                                    >
                                    <span
                                        v-if="role.permissions_count"
                                        class="inline-flex shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-full bg-primary/15 px-2.5 py-1 text-xs font-medium text-primary"
                                    >
                                        {{ role.permissions_count }}
                                        permission{{
                                            role.permissions_count > 1
                                                ? 's'
                                                : ''
                                        }}
                                    </span>
                                </div>
                                <p
                                    class="line-clamp-3 break-words text-sm text-muted-foreground"
                                >
                                    {{ role.description }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    title="Détails"
                                    @click="ouvrirDetail(role)"
                                >
                                    <ShieldCheck class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    title="Modifier"
                                    @click="ouvrirEdit(role)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    v-if="
                                        !role.is_system &&
                                        role.users_count === 0
                                    "
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive hover:text-destructive"
                                    title="Supprimer"
                                    @click="openDeleteRoleModal(role)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <div class="mt-4 min-w-0 space-y-1.5 overflow-hidden">
                            <template
                                v-for="perm in permissionsApercu(role)"
                                :key="perm.name"
                            >
                                <div
                                    class="flex w-full min-w-0 items-center gap-2 overflow-hidden rounded-lg bg-muted/80 px-2.5 py-1.5 text-xs text-muted-foreground"
                                    :title="perm.label"
                                >
                                    <Check
                                        class="size-3.5 shrink-0 text-primary"
                                    />
                                    <span class="min-w-0 flex-1 break-words">{{
                                        perm.label
                                    }}</span>
                                </div>
                            </template>
                            <div
                                v-if="
                                    role.permissions.length >
                                    permissionsApercu(role).length
                                "
                                class="rounded-lg bg-muted/60 px-2.5 py-1.5 text-center text-xs text-muted-foreground"
                            >
                                +
                                {{
                                    role.permissions.length -
                                    permissionsApercu(role).length
                                }}
                                {{
                                    role.permissions.length -
                                        permissionsApercu(role).length ===
                                    1
                                        ? 'autre'
                                        : 'autres'
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <p
                    v-if="!filteredRoles.length"
                    class="py-12 text-center text-muted-foreground"
                >
                    Aucun rôle trouvé.
                </p>
            </div>

            <!-- Modal Créer rôle -->
            <Dialog :open="modalCreate" @update:open="modalCreate = $event">
                <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Plus class="size-5" />
                            Créer un rôle
                        </DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="creerRole">
                        <div class="space-y-2">
                            <Label>Libellé</Label>
                            <Input
                                v-model="formCreate.name"
                                placeholder="Ex : agent call-center"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Description</Label>
                            <Input
                                v-model="formCreate.description"
                                placeholder="Responsabilités du rôle"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Type</Label>
                            <select
                                v-model="formCreate.type"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                            >
                                <option value="backoffice">
                                    Rôles Utilisateurs Backoffice
                                </option>
                                <option value="pharmacie">
                                    Rôles agents pharmacie
                                </option>
                            </select>
                        </div>
                        <div class="space-y-3">
                            <Label>Permissions</Label>
                            <div
                                v-for="(perms, cat) in permissionsGroupées"
                                :key="cat"
                                class="space-y-2"
                            >
                                <p
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    {{ cat }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="p in perms"
                                        :key="p.name"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                                        :class="
                                            formCreate.permissions.includes(
                                                p.name,
                                            )
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-muted/50'
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                formCreate.permissions.includes(
                                                    p.name,
                                                )
                                            "
                                            class="rounded"
                                            @change="
                                                toggleCreatePermission(p.name)
                                            "
                                        />
                                        {{ p.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                @click="modalCreate = false"
                                >Annuler</Button
                            >
                            <Button type="submit">Créer le rôle</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Modal Détails rôle -->
            <Dialog :open="modalDetail" @update:open="modalDetail = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader v-if="roleDetail">
                        <DialogTitle class="flex items-center gap-2">
                            <Shield class="size-5" />
                            {{ roleDetail.label }}
                        </DialogTitle>
                    </DialogHeader>
                    <div v-if="roleDetail" class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ roleDetail.description }}
                        </p>
                        <div class="flex gap-4">
                            <div class="flex items-center gap-2">
                                <Users class="size-4" />
                                <span class="text-sm font-medium"
                                    >{{
                                        roleDetail.users_count
                                    }}
                                    utilisateur(s)</span
                                >
                            </div>
                            <div class="flex items-center gap-2">
                                <ShieldCheck class="size-4" />
                                <span class="text-sm font-medium"
                                    >{{
                                        roleDetail.permissions_count
                                    }}
                                    permission(s)</span
                                >
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Permissions</Label>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="p in roleDetail.permissions"
                                    :key="p.name"
                                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary"
                                >
                                    <Check class="size-3" />
                                    {{ p.label }}
                                </span>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                @click="modalDetail = false"
                                >Fermer</Button
                            >
                            <Button
                                @click="
                                    ouvrirEdit(roleDetail);
                                    modalDetail = false;
                                "
                                >Modifier</Button
                            >
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Modal Modifier rôle -->
            <Dialog
                :open="modalEdit"
                @update:open="
                    (v) => {
                        if (!v) {
                            modalEdit = false;
                            roleToEdit = null;
                        }
                    }
                "
            >
                <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
                    <DialogHeader v-if="roleToEdit">
                        <DialogTitle class="flex items-center gap-2">
                            <Pencil class="size-5" />
                            Modifier {{ roleToEdit.label }}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        v-if="roleToEdit"
                        class="space-y-4"
                        @submit.prevent="modifierRole"
                    >
                        <div class="space-y-3">
                            <Label>Permissions</Label>
                            <div
                                v-for="(perms, cat) in permissionsGroupées"
                                :key="cat"
                                class="space-y-2"
                            >
                                <p
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    {{ cat }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="p in perms"
                                        :key="p.name"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                                        :class="
                                            formEdit.permissions.includes(
                                                p.name,
                                            )
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-muted/50'
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                formEdit.permissions.includes(
                                                    p.name,
                                                )
                                            "
                                            class="rounded"
                                            @change="
                                                toggleEditPermission(p.name)
                                            "
                                        />
                                        {{ p.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                @click="modalEdit = false"
                                >Annuler</Button
                            >
                            <Button type="submit">Enregistrer</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmModal
                :open="showDeleteRoleModal"
                title="Supprimer le rôle"
                :description="
                    roleToDelete
                        ? `Supprimer le rôle « ${roleToDelete.label} » ? Cette action est irréversible.`
                        : ''
                "
                confirm-text="Supprimer"
                variant="destructive"
                @update:open="showDeleteRoleModal = $event"
                @confirm="confirmDeleteRole"
            />
        </SettingsLayout>
    </AppLayout>
</template>
