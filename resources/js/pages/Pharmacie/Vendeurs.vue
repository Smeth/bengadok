<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Users,
    ShieldCheck,
    Shield,
    Copy,
    RefreshCw,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = defineProps<{
    vendeurs: Array<{ id: number; name: string; email: string; username?: string }>;
    pharmacie: { id: number; designation: string };
    nextUserId?: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Ma pharmacie', href: '/dok-pharma' },
    { title: 'Vendeurs', href: '#' },
];

const modalCreate = ref(false);
const form = ref({
    name: '',
    email: '',
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

function ouvrirCreate() {
    form.value = {
        name: '',
        email: '',
        role: 'vendeur',
        password: generatePassword(),
    };
    modalCreate.value = true;
}

function copyCredentials() {
    const text = `Identifiant : ${identifiantPreview.value}\nMot de passe : ${form.value.password}`;
    navigator.clipboard.writeText(text);
}

function creerVendeur() {
    router.post('/pharmacie/vendeurs', {
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        role: form.value.role,
    }, {
        onSuccess: () => { modalCreate.value = false; },
    });
}
</script>

<template>
    <Head title="Vendeurs - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Vendeurs - {{ pharmacie?.designation }}</h1>
                <Button class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]" @click="ouvrirCreate">
                    <Users class="mr-2 size-4" />
                    Créer un utilisateur
                </Button>
            </div>

            <div class="rounded-xl border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nom</th>
                            <th class="px-4 py-3 text-left font-medium">Email</th>
                            <th class="px-4 py-3 text-left font-medium">Identifiant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="v in vendeurs"
                            :key="v.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">{{ v.name }}</td>
                            <td class="px-4 py-3">{{ v.email }}</td>
                            <td class="px-4 py-3 font-mono text-muted-foreground">{{ v.username || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!vendeurs?.length" class="py-8 text-center text-muted-foreground">
                    Aucun vendeur. Cliquez sur "Créer un utilisateur" pour en ajouter.
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
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="Exemple@gmail.com"
                        />
                    </div>

                    <div class="space-y-3">
                        <Label>Définir le rôle de l'utilisateur</Label>
                        <div class="flex gap-4">
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="form.role === 'gerant' ? 'border-[#459cd1] bg-sky-50' : 'border-input hover:bg-muted/30'"
                            >
                                <ShieldCheck class="size-8 text-[#459cd1]" />
                                <span class="font-semibold">Gérant</span>
                                <span class="text-xs text-muted-foreground">Accès complet</span>
                                <input
                                    v-model="form.role"
                                    type="radio"
                                    value="gerant"
                                    class="sr-only"
                                />
                            </label>
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="form.role === 'vendeur' ? 'border-[#459cd1] bg-sky-50' : 'border-input hover:bg-muted/30'"
                            >
                                <Shield class="size-8 text-[#459cd1]" />
                                <span class="font-semibold">Vendeur</span>
                                <span class="text-xs text-muted-foreground">Commandes uniquement</span>
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
                        <h4 class="font-medium text-muted-foreground">Infos propriétaire</h4>
                        <div class="space-y-2">
                            <Label>Identifiant (auto-généré)</Label>
                            <Input
                                :model-value="identifiantPreview"
                                readonly
                                class="bg-muted font-mono text-sm"
                            />
                            <p class="text-xs text-[#459cd1]">
                                L'identifiant sera généré automatiquement à partir du nom
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Mot de passe temporaire</Label>
                            <div class="flex gap-2">
                                <Input
                                    v-model="form.password"
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
                            <p class="text-xs text-[#459cd1]">
                                L'utilisateur devra changer ce mot de passe à la première connexion
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-sky-50 p-4 dark:bg-sky-950/30">
                        <h4 class="mb-2 font-medium text-sky-800 dark:text-sky-200">Identifiants à transmettre</h4>
                        <p class="text-sm text-muted-foreground">
                            Identifiant : <span class="font-mono">{{ identifiantPreview }}</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Mot de passe : <span class="font-mono">{{ form.password }}</span>
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

                    <div class="space-y-2">
                        <Label for="email">Email *</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="Exemple@gmail.com"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="destructive" @click="modalCreate = false">
                            Annuler
                        </Button>
                        <Button type="submit" class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]">
                            Créer l'utilisateur
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
