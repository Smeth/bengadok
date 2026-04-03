<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Search,
    Link2,
    Package,
    LayoutGrid,
    List,
    Database,
    Pencil,
    Trash2,
    Plus,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Produit = {
    id: number;
    designation: string;
    dosage?: string;
    forme?: string;
    type: string;
    pu: number;
    created_at?: string;
    ventes: number;
    ca: number;
    prix_moyen: number;
    prix_min: number;
    prix_max: number;
    classement?: number;
    pharmacies: Array<{ id: number; designation: string; prix: number }>;
};

type DbMedicamentRow = {
    id: number;
    designation: string;
    dosage?: string | null;
    forme?: string | null;
    prix?: number | null;
    laboratoire?: string | null;
    type?: string | null;
    code_article?: string | null;
    notes?: string | null;
    created_at?: string;
};

type MedFormState = {
    designation: string;
    dosage: string;
    forme: string;
    laboratoire: string;
    type: string;
    code_article: string;
    prix: string;
    notes: string;
};

const TYPE_DB_OPTIONS = ['Vente libre', 'Sur ordonnance'] as const;

function emptyMedForm(): MedFormState {
    return {
        designation: '',
        dosage: '',
        forme: '',
        laboratoire: '',
        type: 'Vente libre',
        code_article: '',
        prix: '',
        notes: '',
    };
}

type PaginatedData<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
};

const props = withDefaults(
    defineProps<{
        produits: PaginatedData<Produit>;
        pharmacies: Array<{ id: number; designation: string }>;
        filters: {
            search?: string;
            type?: string;
            pharmacie_id?: string;
            tri?: string;
        };
        onglet?: string;
        dbMedicaments?: PaginatedData<DbMedicamentRow>;
    }>(),
    {
        onglet: 'catalogue',
        dbMedicaments: () => ({
            data: [],
            links: [],
            current_page: 1,
            last_page: 1,
            from: 0,
            to: 0,
            total: 0,
        }),
    },
);

const page = usePage();
const flashStatus = computed(
    () => (page.props.flash as { status?: string })?.status,
);
const isAdmin = computed(() => {
    const roles =
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [];
    return roles.some((r) => ['admin', 'super_admin'].includes(r));
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Médicaments', href: '/medicaments' },
];

const searchQuery = ref(props.filters.search ?? '');
type TabId = 'catalogue' | 'statistiques' | 'db_medicament';
const activeTab = ref<TabId>(
    props.onglet === 'db_medicament' ? 'db_medicament' : 'catalogue',
);
const vueMode = ref<'cartes' | 'liste'>('cartes');

const medModalOpen = ref(false);
const medModalMode = ref<'create' | 'edit'>('create');
const editingId = ref<number | null>(null);
const medForm = ref<MedFormState>(emptyMedForm());

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);

watch(
    () => props.onglet,
    (o) => {
        if (o === 'db_medicament') activeTab.value = 'db_medicament';
    },
);

function goCatalogueTab() {
    if (activeTab.value === 'catalogue') return;
    const wasDb = activeTab.value === 'db_medicament';
    activeTab.value = 'catalogue';
    if (wasDb) {
        router.get(
            '/medicaments',
            { ...props.filters, onglet: 'catalogue' },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['produits', 'onglet', 'filters'],
            },
        );
    }
}

function goDbMedicamentTab() {
    if (activeTab.value === 'db_medicament') return;
    activeTab.value = 'db_medicament';
    router.get(
        '/medicaments',
        { ...props.filters, onglet: 'db_medicament' },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['dbMedicaments', 'onglet'],
        },
    );
}

function filtrer(key: string, value: string) {
    activeTab.value = 'catalogue';
    router.get(
        '/medicaments',
        { ...props.filters, [key]: value || undefined, onglet: 'catalogue' },
        { preserveState: true },
    );
}

function designationComplete(p: Produit) {
    return [p.designation, p.dosage].filter(Boolean).join(' ');
}

function openCreateMedicamentModal() {
    medModalMode.value = 'create';
    editingId.value = null;
    medForm.value = emptyMedForm();
    medModalOpen.value = true;
}

function openEditMedicament(row: DbMedicamentRow) {
    medModalMode.value = 'edit';
    editingId.value = row.id;
    medForm.value = {
        designation: row.designation,
        dosage: row.dosage ?? '',
        forme: row.forme ?? '',
        laboratoire: row.laboratoire ?? '',
        type: row.type === 'Sur ordonnance' ? 'Sur ordonnance' : 'Vente libre',
        code_article: row.code_article ?? '',
        prix:
            row.prix != null && Number.isFinite(row.prix)
                ? String(row.prix)
                : '',
        notes: row.notes ?? '',
    };
    medModalOpen.value = true;
}

function closeMedModal() {
    medModalOpen.value = false;
    editingId.value = null;
}

function parsePrixInput(v: string): number | null {
    const s = v
        .trim()
        .replace(/\s/g, '')
        .replace(/\u00a0/g, '')
        .replace(',', '.');
    if (s === '') return null;
    const n = Number(s);
    return Number.isFinite(n) ? n : null;
}

const medFormPrixValide = computed(() => {
    const n = parsePrixInput(medForm.value.prix);
    return n !== null && n >= 0;
});

const peutSoumettreMed = computed(
    () =>
        medForm.value.designation.trim().length > 0 && medFormPrixValide.value,
);

function submitMedModal() {
    if (!peutSoumettreMed.value) return;
    const prix = parsePrixInput(medForm.value.prix);
    if (prix === null) return;
    const payload = {
        designation: medForm.value.designation.trim(),
        dosage: medForm.value.dosage.trim() || undefined,
        forme: medForm.value.forme.trim() || undefined,
        prix,
        laboratoire: medForm.value.laboratoire.trim() || undefined,
        type: medForm.value.type.trim() || undefined,
        code_article: medForm.value.code_article.trim() || undefined,
        notes: medForm.value.notes.trim() || undefined,
    };
    if (medModalMode.value === 'create') {
        router.post('/medicaments/db-medicaments', payload, {
            preserveScroll: true,
            onSuccess: () => closeMedModal(),
        });
    } else if (editingId.value != null) {
        router.patch(
            `/medicaments/db-medicaments/${editingId.value}`,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => closeMedModal(),
            },
        );
    }
}

function destroyRow(id: number) {
    if (!confirm('Supprimer cette référence de la base locale ?')) return;
    router.delete(`/medicaments/db-medicaments/${id}`, {
        preserveScroll: true,
    });
}

const badgeTotal = computed(() =>
    activeTab.value === 'db_medicament'
        ? props.dbMedicaments.total
        : props.produits.total,
);
</script>

<template>
    <Head title="Médicaments - BengaDok" />

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
            <p
                v-if="flashStatus"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
            >
                {{ flashStatus }}
            </p>

            <!-- Badge + tabs -->
            <div
                class="relative z-10 flex shrink-0 flex-wrap items-center justify-between gap-4"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center gap-1 rounded-full bg-emerald-500 text-white"
                    >
                        <Package class="size-4" />
                        <span class="text-sm font-bold">{{ badgeTotal }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="
                                activeTab === 'catalogue'
                                    ? 'bg-[#459cd1] text-white'
                                    : 'bg-white/80 text-muted-foreground hover:bg-white'
                            "
                            @click="goCatalogueTab"
                        >
                            Catalogue Médicaments
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="
                                activeTab === 'statistiques'
                                    ? 'bg-[#459cd1] text-white'
                                    : 'bg-white/80 text-muted-foreground hover:bg-white'
                            "
                            @click="activeTab = 'statistiques'"
                        >
                            Statistiques
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="
                                activeTab === 'db_medicament'
                                    ? 'bg-[#459cd1] text-white'
                                    : 'bg-white/80 text-muted-foreground hover:bg-white'
                            "
                            @click="goDbMedicamentTab"
                        >
                            <Database class="size-4 shrink-0" />
                            DB médicament
                        </button>
                        <Link
                            href="/medicaments/doublons"
                            class="inline-flex rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-white/80 text-muted-foreground hover:bg-white hover:text-foreground cursor-pointer"
                        >
                            Gestion des doublons
                        </Link>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'catalogue'" class="space-y-4">
                <form
                    class="flex flex-wrap gap-4"
                    @submit.prevent="filtrer('search', searchQuery)"
                >
                    <div class="relative min-w-[200px] flex-1">
                        <Search
                            class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Rechercher un médicament..."
                            class="pl-9"
                        />
                    </div>
                    <select
                        :value="filters.tri"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'tri',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Trier par</option>
                        <option value="designation">Nom</option>
                        <option value="prix">Prix</option>
                        <option value="ventes">Ventes</option>
                    </select>
                    <select
                        :value="filters.type"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'type',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Prescription</option>
                        <option value="Vente libre">Vente libre</option>
                        <option value="Sur ordonnance">Sur ordonnance</option>
                    </select>
                    <select
                        :value="filters.pharmacie_id"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'pharmacie_id',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Toutes les pharmacies</option>
                        <option
                            v-for="ph in pharmacies"
                            :key="ph.id"
                            :value="String(ph.id)"
                        >
                            {{ ph.designation }}
                        </option>
                    </select>
                    <Button type="submit">Rechercher</Button>
                    <div
                        class="flex gap-1 rounded-lg border border-input bg-muted/30 p-1"
                    >
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="
                                vueMode === 'cartes'
                                    ? 'bg-white shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="vueMode = 'cartes'"
                        >
                            <LayoutGrid class="size-4" />
                            Cartes
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="
                                vueMode === 'liste'
                                    ? 'bg-white shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="vueMode = 'liste'"
                        >
                            <List class="size-4" />
                            Liste
                        </button>
                    </div>
                </form>

                <!-- Vue cartes -->
                <div
                    v-if="vueMode === 'cartes'"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="p in produits.data"
                        :key="p.id"
                        class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                    >
                        <div class="mb-3 flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <Link2
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <h3 class="font-semibold text-foreground">
                                        {{ designationComplete(p) }}
                                    </h3>
                                    <span
                                        class="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30"
                                    >
                                        {{ p.type || 'Vente libre' }}
                                    </span>
                                </div>
                            </div>
                            <Button variant="secondary" size="sm" as-child>
                                <Link :href="`/medicaments/${p.id}`"
                                    >Détails</Link
                                >
                            </Button>
                        </div>

                        <div class="space-y-2 text-sm">
                            <p class="flex flex-wrap gap-1">
                                <span class="text-muted-foreground">Forme:</span
                                ><span class="ml-1 font-medium">{{
                                    p.forme || '-'
                                }}</span>
                            </p>
                            <p class="flex flex-wrap gap-1">
                                <span class="text-muted-foreground"
                                    >Prix moyen:</span
                                ><strong class="ml-1 text-emerald-600"
                                    >{{
                                        Number(p.prix_moyen).toLocaleString(
                                            'fr-FR',
                                        )
                                    }}
                                    xaf</strong
                                >
                            </p>
                            <p class="flex flex-wrap gap-1">
                                <span class="text-muted-foreground"
                                    >Fourchette:</span
                                ><strong class="ml-1 text-emerald-600"
                                    >{{
                                        Number(p.prix_min).toLocaleString(
                                            'fr-FR',
                                        )
                                    }}
                                    -
                                    {{
                                        Number(p.prix_max).toLocaleString(
                                            'fr-FR',
                                        )
                                    }}
                                    xaf</strong
                                >
                            </p>
                            <p class="flex flex-wrap gap-1">
                                <span class="text-muted-foreground">Vente:</span
                                ><strong class="ml-1 text-emerald-600"
                                    >{{ p.ventes }} Unités</strong
                                >
                            </p>
                            <p class="flex flex-wrap gap-1">
                                <span class="text-muted-foreground"
                                    >CA généré:</span
                                ><strong class="ml-1 text-emerald-600"
                                    >{{
                                        Number(p.ca).toLocaleString('fr-FR')
                                    }}
                                    xaf</strong
                                >
                            </p>
                        </div>

                        <div class="mt-3">
                            <p class="mb-2 text-xs text-muted-foreground">
                                Dernière disponibilité connue:
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="ph in p.pharmacies"
                                    :key="ph.id"
                                    class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800"
                                >
                                    {{ ph.designation }}:
                                    {{
                                        Number(ph.prix).toLocaleString('fr-FR')
                                    }}
                                    xaf
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vue liste -->
                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-white/80 bg-white shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="px-4 py-3 text-left font-medium">
                                        Médicament
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        Type
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        Forme
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Prix moyen
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Fourchette
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Ventes
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        CA généré
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    ></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in produits.data"
                                    :key="p.id"
                                    class="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        {{ designationComplete(p) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30"
                                        >
                                            {{ p.type || 'Vente libre' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ p.forme || '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-medium text-emerald-600"
                                    >
                                        {{
                                            Number(p.prix_moyen).toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                        xaf
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-muted-foreground"
                                    >
                                        {{
                                            Number(p.prix_min).toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                        -
                                        {{
                                            Number(p.prix_max).toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                        xaf
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ p.ventes }} Unités
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-medium text-emerald-600"
                                    >
                                        {{
                                            Number(p.ca).toLocaleString('fr-FR')
                                        }}
                                        xaf
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="`/medicaments/${p.id}`"
                                                >Détails</Link
                                            >
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="produits.links.length > 3"
                    class="mt-4 flex items-center justify-between px-2"
                >
                    <div class="hidden text-sm text-muted-foreground sm:block">
                        Affichage de
                        <span class="font-medium text-foreground">{{
                            produits.from
                        }}</span>
                        à
                        <span class="font-medium text-foreground">{{
                            produits.to
                        }}</span>
                        sur
                        <span class="font-medium text-foreground">{{
                            produits.total
                        }}</span>
                        résultats
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <template
                            v-for="(link, pIndex) in produits.links"
                            :key="pIndex"
                        >
                            <div
                                v-if="link.url === null"
                                class="flex min-w-9 items-center justify-center rounded-lg border border-transparent px-3 py-1.5 text-sm text-muted-foreground"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-muted/50"
                                :class="
                                    link.active
                                        ? 'border-[#459cd1] bg-[#459cd1] text-white'
                                        : 'border-input bg-white text-foreground'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

            <div
                v-else-if="activeTab === 'db_medicament'"
                class="space-y-5 rounded-xl border border-white/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/95"
            >
                <div
                    class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-xl bg-[#459cd1]/15 text-[#459cd1]"
                            >
                                <Database class="size-5" />
                            </div>
                            <h2
                                class="text-xl font-semibold tracking-tight text-foreground"
                            >
                                DB médicament
                            </h2>
                        </div>
                        <p
                            class="max-w-2xl text-sm leading-relaxed text-muted-foreground"
                        >
                            Référentiel local indépendant du catalogue et des
                            commandes. Utilisez le bouton ci-contre pour saisir
                            une fiche complète (prix, laboratoire, etc.).
                        </p>
                        <p
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200"
                        >
                            <strong>Module isolé :</strong> Les médicaments
                            ajoutés ici ne sont pas utilisés dans les commandes,
                            le catalogue ou la recherche produit. Ce module est
                            indépendant du reste du système.
                        </p>
                    </div>
                    <Button
                        v-if="isAdmin"
                        class="h-10 shrink-0 gap-2 bg-[#459cd1] px-4 text-white hover:bg-[#3a87b8]"
                        @click="openCreateMedicamentModal"
                    >
                        <Plus class="size-4" />
                        Nouveau médicament
                    </Button>
                </div>

                <p v-if="!isAdmin" class="text-sm text-muted-foreground">
                    Seuls les administrateurs peuvent ajouter ou modifier des
                    entrées.
                </p>

                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="px-3 py-3 text-left font-medium">
                                    Désignation
                                </th>
                                <th class="px-3 py-3 text-left font-medium">
                                    Dosage
                                </th>
                                <th class="px-3 py-3 text-left font-medium">
                                    Forme
                                </th>
                                <th class="px-3 py-3 text-right font-medium">
                                    Prix (FCFA)
                                </th>
                                <th class="px-3 py-3 text-left font-medium">
                                    Type
                                </th>
                                <th class="px-3 py-3 text-left font-medium">
                                    Laboratoire
                                </th>
                                <th class="px-3 py-3 text-left font-medium">
                                    Code
                                </th>
                                <th
                                    class="px-3 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Créé le
                                </th>
                                <th
                                    v-if="isAdmin"
                                    class="px-3 py-3 text-right font-medium w-[100px]"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in dbMedicaments.data"
                                :key="row.id"
                                class="border-b border-border/60 last:border-0 transition-colors hover:bg-muted/30"
                            >
                                <td class="px-3 py-3 font-medium">
                                    {{ row.designation }}
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ row.dosage || '—' }}
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ row.forme || '—' }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-medium tabular-nums text-emerald-700"
                                >
                                    {{
                                        row.prix != null
                                            ? Number(row.prix).toLocaleString(
                                                  'fr-FR',
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ row.type || '—' }}
                                </td>
                                <td
                                    class="max-w-[140px] truncate px-3 py-3 text-muted-foreground"
                                    :title="row.laboratoire || ''"
                                >
                                    {{ row.laboratoire || '—' }}
                                </td>
                                <td
                                    class="px-3 py-3 font-mono text-xs text-muted-foreground"
                                >
                                    {{ row.code_article || '—' }}
                                </td>
                                <td
                                    class="px-3 py-3 text-xs text-muted-foreground whitespace-nowrap"
                                >
                                    {{ row.created_at || '—' }}
                                </td>
                                <td v-if="isAdmin" class="px-3 py-3 text-right">
                                    <div class="flex justify-end gap-0.5">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            title="Modifier"
                                            @click="openEditMedicament(row)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-destructive"
                                            title="Supprimer"
                                            @click="destroyRow(row.id)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="!dbMedicaments.data.length"
                    class="rounded-lg border border-dashed py-12 text-center text-sm text-muted-foreground"
                >
                    Aucune entrée. Cliquez sur « Nouveau médicament » pour créer
                    la première fiche.
                </p>

                <div
                    v-if="dbMedicaments.links.length > 3"
                    class="flex flex-wrap items-center justify-between gap-2 pt-1"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ dbMedicaments.from }}–{{ dbMedicaments.to }} sur
                        {{ dbMedicaments.total }}
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="(link, i) in dbMedicaments.links"
                            :key="i"
                        >
                            <div
                                v-if="link.url === null"
                                class="flex min-w-9 items-center justify-center rounded-lg px-3 py-1.5 text-sm text-muted-foreground"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm transition-colors"
                                :class="
                                    link.active
                                        ? 'border-[#459cd1] bg-[#459cd1] text-white'
                                        : 'border-input bg-white'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                        </template>
                    </div>
                </div>

                <Dialog
                    :open="medModalOpen"
                    @update:open="(v: boolean) => !v && closeMedModal()"
                >
                    <DialogContent
                        class="flex max-h-[min(90vh,720px)] max-w-2xl flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl"
                    >
                        <DialogHeader
                            class="shrink-0 border-b px-6 py-4 text-left"
                        >
                            <DialogTitle class="text-lg">
                                {{
                                    medModalMode === 'create'
                                        ? 'Nouveau médicament'
                                        : 'Modifier le médicament'
                                }}
                            </DialogTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Fiche détaillée pour la base locale (hors flux
                                commandes).
                            </p>
                        </DialogHeader>
                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <Label class="text-xs font-medium"
                                        >Désignation
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="medForm.designation"
                                        class="mt-1.5"
                                        placeholder="Ex. Paracétamol 500 mg"
                                        autocomplete="off"
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs font-medium"
                                        >Dosage</Label
                                    >
                                    <Input
                                        v-model="medForm.dosage"
                                        class="mt-1.5"
                                        placeholder="Ex. 500 mg"
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs font-medium"
                                        >Forme galénique</Label
                                    >
                                    <Input
                                        v-model="medForm.forme"
                                        class="mt-1.5"
                                        placeholder="Comprimé, sirop, gélule…"
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs font-medium"
                                        >Prix unitaire (FCFA)
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="medForm.prix"
                                        class="mt-1.5"
                                        inputmode="decimal"
                                        placeholder="Ex. 2500"
                                    />
                                    <p
                                        v-if="
                                            medForm.prix.trim() &&
                                            !medFormPrixValide
                                        "
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        Saisissez un montant valide (≥ 0).
                                    </p>
                                </div>
                                <div>
                                    <Label class="text-xs font-medium"
                                        >Type de délivrance</Label
                                    >
                                    <select
                                        v-model="medForm.type"
                                        class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option
                                            v-for="opt in TYPE_DB_OPTIONS"
                                            :key="opt"
                                            :value="opt"
                                        >
                                            {{ opt }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="text-xs font-medium"
                                        >Laboratoire / fabricant</Label
                                    >
                                    <Input
                                        v-model="medForm.laboratoire"
                                        class="mt-1.5"
                                        placeholder="Optionnel"
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs font-medium"
                                        >Code article / référence</Label
                                    >
                                    <Input
                                        v-model="medForm.code_article"
                                        class="mt-1.5"
                                        placeholder="Optionnel"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <Label class="text-xs font-medium"
                                        >Notes</Label
                                    >
                                    <textarea
                                        v-model="medForm.notes"
                                        rows="3"
                                        class="mt-1.5 w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        placeholder="Observations, conditionnement, etc."
                                    />
                                </div>
                            </div>
                        </div>
                        <DialogFooter
                            class="shrink-0 gap-2 border-t bg-muted/30 px-6 py-4 sm:justify-end"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeMedModal"
                                >Annuler</Button
                            >
                            <Button
                                type="button"
                                class="bg-[#459cd1] text-white hover:bg-[#3a87b8]"
                                :disabled="!peutSoumettreMed"
                                @click="submitMedModal"
                            >
                                {{
                                    medModalMode === 'create'
                                        ? 'Créer'
                                        : 'Enregistrer'
                                }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div
                v-else
                class="rounded-xl border bg-white p-8 text-center dark:border-white/10 dark:bg-white/95"
            >
                <p class="text-muted-foreground">Statistiques – à venir</p>
            </div>
        </div>
    </AppLayout>
</template>
