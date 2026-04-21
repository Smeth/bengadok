<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Users,
    ShieldCheck,
    Shield,
    Copy,
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
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Ma pharmacie', href: '/dok-pharma' },
    { title: 'Vendeurs', href: '#' },
];

const modalCreate = ref(false);
const formErrors = ref<Record<string, string>>({});
const createSuccessToast = ref<{
    show: boolean;
    title: string;
}>({ show: false, title: '' });
const form = ref({
    name: '',
    email: '',
    phone: '',
    role: 'vendeur' as 'gerant' | 'vendeur',
    password: '',
});

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
    if (!form.value.name.trim()) return 'pharmacie_role_nom_?';
    const slugPharma = slugify(props.pharmacie.designation);
    const slugNom = slugify(form.value.name);
    const role = form.value.role;
    const nextId = props.nextUserId ?? 0;
    return `${slugPharma}_${role}_${slugNom}_${nextId}`;
});

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
    modalCreate.value = true;
}

function copyCredentials() {
    const text = `Identifiant : ${identifiantPreview.value}\nMot de passe : ${form.value.password}`;
    navigator.clipboard.writeText(text);
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
                resetCreateForm();
                const flash = (
                    page.props as { flash?: { status?: string } }
                ).flash;
                createSuccessToast.value = {
                    show: true,
                    title:
                        flash?.status?.trim() ||
                        'Utilisateur créé. Transmettez les identifiants au collaborateur.',
                };
                window.setTimeout(() => {
                    createSuccessToast.value.show = false;
                }, 10000);
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

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">
                    Vendeurs - {{ pharmacie?.designation }}
                </h1>
                <Button
                    class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                    @click="ouvrirCreate"
                >
                    <Users class="mr-2 size-4" />
                    Créer un utilisateur
                </Button>
            </div>

            <div class="rounded-xl border bg-white shadow-sm">
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

        <!-- Modal Créer un utilisateur (design image 2) -->
        <Dialog :open="modalCreate" @update:open="modalCreate = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Users class="size-5 text-[#459cd1]" />
                        Créer un utilisateur
                    </DialogTitle>
                </DialogHeader>
                <p class="text-sm text-[#459cd1]">
                    Ajoutez un nouveau membre à une pharmacie
                </p>

                <form class="space-y-6" @submit.prevent="creerVendeur">
                    <div class="space-y-2">
                        <Label for="name">Nom complet</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            placeholder="Ex: Fofana Didier"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="vendeur-phone"
                            >Téléphone (obligatoire — SMS / OTP)</Label
                        >
                        <Input
                            id="vendeur-phone"
                            v-model="form.phone"
                            type="tel"
                            required
                            autocomplete="tel"
                            placeholder="Ex: +242 06 123 45 67"
                        />
                        <p
                            v-if="formErrors.phone"
                            class="text-sm text-red-600"
                        >
                            {{ formErrors.phone }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="email">Email (facultatif)</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="Laisser vide si non utilisé"
                        />
                        <p
                            v-if="formErrors.email"
                            class="text-sm text-red-600"
                        >
                            {{ formErrors.email }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <Label>Définir le rôle de l'utilisateur</Label>
                        <div class="flex gap-4">
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="
                                    form.role === 'gerant'
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
                                    v-model="form.role"
                                    type="radio"
                                    value="gerant"
                                    class="sr-only"
                                />
                            </label>
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="
                                    form.role === 'vendeur'
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
                                    v-model="form.role"
                                    type="radio"
                                    value="vendeur"
                                    class="sr-only"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-4 space-y-4">
                        <h4 class="font-medium text-muted-foreground">
                            Infos propriétaire
                        </h4>
                        <div class="space-y-2">
                            <Label>Identifiant (auto-généré)</Label>
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                :value="identifiantPreview"
                                class="flex h-9 w-full min-w-0 rounded-md border border-input bg-muted px-3 py-1 font-mono text-sm shadow-xs outline-none md:text-sm"
                            />
                            <p class="text-xs text-[#459cd1]">
                                L'identifiant sera généré automatiquement à
                                partir du nom
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Mot de passe temporaire</Label>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    readonly
                                    tabindex="-1"
                                    :value="form.password"
                                    class="flex h-9 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 py-1 font-mono text-base shadow-xs outline-none md:text-sm"
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
                            <p class="text-xs text-[#459cd1]">
                                L'utilisateur devra changer ce mot de passe à la
                                première connexion
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
                            <span class="font-mono">{{ form.password }}</span>
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
                            @click="modalCreate = false"
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

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="createSuccessToast.show"
                    class="fixed bottom-6 right-6 z-[200] flex max-w-md items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-lg dark:border-emerald-800 dark:bg-emerald-950/90 dark:text-emerald-100"
                    role="status"
                >
                    <CheckCircle2
                        class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <p class="min-w-0 flex-1 font-semibold">
                        {{ createSuccessToast.title }}
                    </p>
                    <button
                        type="button"
                        class="rounded p-1 text-emerald-700 hover:bg-emerald-100 dark:hover:bg-emerald-900"
                        aria-label="Fermer"
                        @click="createSuccessToast.show = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
