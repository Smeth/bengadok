<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    Clock,
    MapPin,
    Mail,
    Phone,
    Search,
    Plus,
    Trash2,
    ShieldAlert,
    List,
    LayoutGrid,
    MapPin as MapIcon,
    Sun,
    Moon,
    Download,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import ConfirmModal from '@/components/ConfirmModal.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

type Pharmacie = {
    id: number;
    designation: string;
    adresse: string;
    telephone: string;
    email: string | null;
    latitude: number;
    longitude: number;
    de_garde: boolean;
    proprio_nom: string | null;
    proprio_tel: string | null;
    zone: { designation: string } | null;
    type_pharmacie: { designation: string } | null;
    heurs: { ouverture: string; fermeture: string } | null;
    users_count: number;
};

const props = defineProps<{
    pharmacies: Pharmacie[];
    filters: { search?: string };
    stats: { de_garde: number; total: number };
    zones: Array<{ id: number; designation: string }>;
    types: Array<{ id: number; designation: string; heurs?: { ouverture: string; fermeture: string } }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Pharmacies', href: '/pharmacies' },
];

const searchQuery = ref(props.filters.search ?? '');
const showModal = ref(false);
const showDeleteModal = ref(false);
const pharmacieToDelete = ref<Pharmacie | null>(null);
const viewMode = ref<'liste' | 'card' | 'carte'>('card');
const selectedIds = ref<Set<number>>(new Set());

const allSelected = computed(() => {
    const data = props.pharmacies ?? [];
    return data.length > 0 && data.every((p) => selectedIds.value.has(p.id));
});
const someSelected = computed(() => selectedIds.value.size > 0);

function toggleAll() {
    const data = props.pharmacies ?? [];
    if (allSelected.value) {
        data.forEach((p) => selectedIds.value.delete(p.id));
    } else {
        data.forEach((p) => selectedIds.value.add(p.id));
    }
    selectedIds.value = new Set(selectedIds.value);
}
function toggleOne(id: number) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}
function clearSelection() {
    selectedIds.value = new Set();
}
function exportSelectedCSV() {
    const data = props.pharmacies ?? [];
    const selected = data.filter((p) => selectedIds.value.has(p.id));
    if (!selected.length) return;
    const headers = ['Pharmacie', 'Zone', 'Adresse', 'Tél', 'Heures', 'De garde'];
    const rows = selected.map((p) => [
        p.designation,
        p.zone?.designation ?? '-',
        p.adresse,
        p.telephone,
        p.heurs ? `${p.heurs.ouverture} - ${p.heurs.fermeture}` : '-',
        p.de_garde ? 'Oui' : 'Non',
    ]);
    const csv = [headers.join(';'), ...rows.map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(';'))].join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `pharmacies_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

const form = ref({
    designation: '',
    adresse: '',
    telephone: '',
    email: '',
    zone_id: props.zones[0]?.id?.toString() ?? '',
    type_pharmacie_id: '',
    heure_ouverture: '08:00',
    heure_fermeture: '19:00',
    proprio_nom: '',
    proprio_email: '',
    proprio_tel: '',
});

const errors = ref<Record<string, string>>({});

function search() {
    router.get('/pharmacies', { search: searchQuery.value || undefined }, { preserveState: true });
}

function openModal() {
    form.value = {
        designation: '',
        adresse: '',
        telephone: '',
        email: '',
        zone_id: props.zones[0]?.id?.toString() ?? '',
        type_pharmacie_id: props.types[0]?.id?.toString() ?? '',
        heure_ouverture: '08:00',
        heure_fermeture: '19:00',
        proprio_nom: '',
        proprio_email: '',
        proprio_tel: '',
    };
    errors.value = {};
    showModal.value = true;
}

function submitCreate() {
    errors.value = {};
    router.post('/pharmacies', {
        ...form.value,
        zone_id: form.value.zone_id || props.zones[0]?.id,
    }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError: (e) => { errors.value = e as Record<string, string>; },
    });
}

function toggleGarde(pharmacie: Pharmacie, e: Event) {
    e.preventDefault();
    router.patch(`/pharmacies/${pharmacie.id}/toggle-garde`, {}, { preserveScroll: true });
}

function openDeleteModal(pharmacie: Pharmacie) {
    pharmacieToDelete.value = pharmacie;
    showDeleteModal.value = true;
}

function confirmDelete() {
    if (!pharmacieToDelete.value) return;
    router.delete(`/pharmacies/${pharmacieToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            pharmacieToDelete.value = null;
        },
    });
}

const typeJour = props.types.find(t => t.designation?.toLowerCase().includes('jour'));
const typeNuit = props.types.find(t => t.designation?.toLowerCase().includes('nuit'));

watch(() => form.value.type_pharmacie_id, (id) => {
    const type = props.types.find(t => t.id?.toString() === id);
    if (type?.heurs) {
        form.value.heure_ouverture = type.heurs.ouverture || '08:00';
        form.value.heure_fermeture = type.heurs.fermeture || '19:00';
    }
});

// Carte Leaflet
const mapContainerRef = ref<HTMLElement | null>(null);
let mapInstance: ReturnType<typeof import('leaflet').map> | null = null;
let markersLayer: ReturnType<typeof import('leaflet').layerGroup> | null = null;
let leafletModule: typeof import('leaflet') | null = null;

function getMarkerColor(p: Pharmacie): string {
    if (p.de_garde) return '#22c55e';
    const type = p.type_pharmacie?.designation?.toLowerCase() ?? '';
    if (type.includes('nuit')) return '#1e3a5f';
    if (type.includes('24') || type.includes('24h')) return '#ef4444';
    return '#3b82f6';
}

async function initMap() {
    if (!mapContainerRef.value || typeof window === 'undefined') return;
    leafletModule = await import('leaflet');
    const L = leafletModule.default;
    await import('leaflet/dist/leaflet.css');

    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }

    mapInstance = L.map(mapContainerRef.value).setView([-4.2694, 15.2712], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(mapInstance);

    markersLayer = L.layerGroup().addTo(mapInstance);

    props.pharmacies.forEach((p) => {
        if (p.latitude == null || p.longitude == null) return;
        const color = getMarkerColor(p);
        const marker = L.circleMarker([p.latitude, p.longitude], {
            radius: 12,
            fillColor: color,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9,
        });
        marker.bindPopup(
            `<b>${p.designation}</b><br/>${p.adresse}<br/><a href="/pharmacies/${p.id}">Voir détails</a>`
        );
        markersLayer?.addLayer(marker);
    });
}

function destroyMap() {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
}

onMounted(() => {
    if (viewMode.value === 'carte') {
        nextTick(() => initMap());
    }
});

watch(viewMode, async (mode) => {
    if (mode === 'carte') {
        await nextTick();
        initMap();
    } else {
        destroyMap();
    }
});

watch(() => props.pharmacies, () => {
    if (viewMode.value === 'carte' && mapInstance && markersLayer && leafletModule) {
        const L = leafletModule.default;
        markersLayer.clearLayers();
        props.pharmacies.forEach((p) => {
            if (p.latitude == null || p.longitude == null) return;
            const color = getMarkerColor(p);
            const marker = L.circleMarker([p.latitude, p.longitude], {
                radius: 12,
                fillColor: color,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9,
            });
            marker.bindPopup(
                `<b>${p.designation}</b><br/>${p.adresse}<br/><a href="/pharmacies/${p.id}">Voir détails</a>`
            );
            markersLayer?.addLayer(marker);
        });
    }
}, { deep: true });

onUnmounted(destroyMap);
</script>

<template>
    <Head title="Gestion des pharmacies - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6" style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);">
            <!-- Header: boutons + badges -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button
                        class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                        @click="openModal"
                    >
                        <Plus class="mr-2 size-4" />
                        Nouvelle Pharmacie
                    </Button>
                    <div class="flex gap-2">
                        <Button
                            :variant="viewMode === 'liste' ? 'default' : 'outline'"
                            size="sm"
                            @click="viewMode = 'liste'"
                        >
                            <List class="mr-1 size-4" />
                            Liste
                        </Button>
                        <Button
                            :variant="viewMode === 'card' ? 'default' : 'outline'"
                            size="sm"
                            @click="viewMode = 'card'"
                        >
                            <LayoutGrid class="mr-1 size-4" />
                            Card
                        </Button>
                        <Button
                            :variant="viewMode === 'carte' ? 'default' : 'outline'"
                            size="sm"
                            @click="viewMode = 'carte'"
                        >
                            <MapIcon class="mr-1 size-4" />
                            Carte
                        </Button>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex size-9 items-center justify-center gap-1 rounded-full bg-red-500 text-white">
                            <ShieldAlert class="size-4" />
                            <span class="text-sm font-bold">{{ stats.de_garde }}</span>
                        </div>
                        <div class="flex size-9 items-center justify-center gap-1 rounded-full bg-blue-500 text-white">
                            <Building2 class="size-4" />
                            <span class="text-sm font-bold">{{ stats.total }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet + recherche -->
            <div class="rounded-lg bg-[#67cb88]/30 px-4 py-2">
                <h2 class="font-semibold text-foreground">Gestion des pharmacies</h2>
            </div>

            <form class="flex gap-4" @submit.prevent="search">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Recherche une pharmacie..."
                        class="rounded-lg pl-10"
                        @keyup.enter="search"
                    />
                </div>
                <Button type="submit" variant="secondary">Rechercher</Button>
            </form>

            <!-- Vue Liste (tableau) -->
            <div v-if="viewMode === 'liste'" class="space-y-2">
                <div
                    v-if="someSelected"
                    class="flex flex-wrap items-center gap-3 rounded-lg border bg-primary/5 px-4 py-2"
                >
                    <span class="font-medium">{{ selectedIds.size }} pharmacie(s) sélectionnée(s)</span>
                    <Button variant="outline" size="sm" @click="clearSelection">Tout désélectionner</Button>
                    <Button variant="outline" size="sm" @click="exportSelectedCSV">
                        <span class="mr-2">📥</span>
                        Exporter CSV
                    </Button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-border/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/95">
                <table class="w-full min-w-[920px] table-fixed text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="w-10 px-2 py-3">
                                <Checkbox
                                    :checked="allSelected"
                                    :indeterminate="someSelected && !allSelected"
                                    @update:checked="toggleAll"
                                />
                            </th>
                            <th class="min-w-[180px] px-4 py-3 text-left font-medium">Pharmacie</th>
                            <th class="min-w-[90px] px-4 py-3 text-left font-medium">Zone</th>
                            <th class="min-w-[160px] px-4 py-3 text-left font-medium">Adresse</th>
                            <th class="min-w-[130px] px-4 py-3 text-left font-medium">Tél</th>
                            <th class="min-w-[100px] shrink-0 px-4 py-3 text-left font-medium">Heures</th>
                            <th class="min-w-[120px] shrink-0 px-4 py-3 text-left font-medium">Statut</th>
                            <th class="min-w-[100px] shrink-0 px-4 py-3 text-left font-medium">Utilisateurs</th>
                            <th class="min-w-[180px] shrink-0 px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="pharmacie in pharmacies"
                            :key="pharmacie.id"
                            class="border-t transition-colors hover:bg-muted/30"
                        >
                            <td class="w-10 px-2 py-3">
                                <Checkbox
                                    :checked="selectedIds.has(pharmacie.id)"
                                    @update:checked="() => toggleOne(pharmacie.id)"
                                />
                            </td>
                            <td class="max-w-[200px] px-4 py-3">
                                <div class="flex min-w-0 items-center gap-2">
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                                        <Building2 class="size-4" />
                                    </div>
                                    <span class="min-w-0 truncate font-medium" :title="pharmacie.designation">{{ pharmacie.designation }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{{ pharmacie.zone?.designation ?? '-' }}</td>
                            <td class="max-w-[180px] px-4 py-3">
                                <span class="block min-w-0 truncate text-muted-foreground" :title="pharmacie.adresse">{{ pharmacie.adresse }}</span>
                            </td>
                            <td class="max-w-[140px] px-4 py-3">
                                <span class="block min-w-0 truncate text-muted-foreground" :title="pharmacie.telephone">{{ pharmacie.telephone }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
                                {{ pharmacie.heurs ? `${pharmacie.heurs.ouverture} - ${pharmacie.heurs.fermeture}` : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-if="pharmacie.type_pharmacie?.designation?.toLowerCase().includes('jour')"
                                        class="whitespace-nowrap rounded-full bg-blue-500 px-2.5 py-0.5 text-xs font-medium text-white"
                                    >
                                        Ouvert
                                    </span>
                                    <span
                                        v-if="pharmacie.de_garde"
                                        class="whitespace-nowrap rounded-full bg-red-500 px-2.5 py-0.5 text-xs font-medium text-white"
                                    >
                                        En Garde
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{{ pharmacie.users_count }} Utilisateur(s)</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Button variant="secondary" size="sm" as-child>
                                        <Link :href="`/pharmacies/${pharmacie.id}`">Détails</Link>
                                    </Button>
                                    <Button
                                        :variant="pharmacie.de_garde ? 'destructive' : 'outline'"
                                        size="sm"
                                        @click="toggleGarde(pharmacie, $event)"
                                    >
                                        <ShieldAlert class="size-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="size-8 text-red-600 hover:text-red-700" @click="openDeleteModal(pharmacie)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Vue Card (cartes) -->
            <div v-else-if="viewMode === 'card'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="pharmacie in pharmacies"
                    :key="pharmacie.id"
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                                <Building2 class="size-4" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-foreground">{{ pharmacie.designation }}</h3>
                                <div class="flex gap-1">
                                    <span
                                        v-if="pharmacie.type_pharmacie?.designation?.toLowerCase().includes('jour')"
                                        class="rounded-full bg-blue-500 px-2 py-0.5 text-xs text-white"
                                    >
                                        Jour
                                    </span>
                                    <span
                                        v-if="pharmacie.de_garde"
                                        class="rounded-full bg-red-500 px-2 py-0.5 text-xs text-white"
                                    >
                                        De Garde
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm text-muted-foreground">
                        <p class="flex items-center gap-2">
                            <MapPin class="size-4 shrink-0" />
                            {{ pharmacie.adresse }}
                        </p>
                        <p class="flex items-center gap-2">
                            <Clock class="size-4 shrink-0" />
                            {{ pharmacie.heurs ? `${pharmacie.heurs.ouverture} - ${pharmacie.heurs.fermeture}` : '-' }}
                        </p>
                        <p class="flex items-center gap-2">
                            <Phone class="size-4 shrink-0" />
                            {{ pharmacie.telephone }}
                        </p>
                        <p v-if="pharmacie.email" class="flex items-center gap-2">
                            <Mail class="size-4 shrink-0" />
                            {{ pharmacie.email }}
                        </p>
                    </div>

                    <div class="my-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                        <p class="text-xs font-medium text-muted-foreground">Propriétaire</p>
                        <p class="font-medium">{{ pharmacie.proprio_nom || '-' }}</p>
                        <p v-if="pharmacie.proprio_tel" class="text-sm text-muted-foreground">{{ pharmacie.proprio_tel }}</p>
                    </div>

                    <p class="mb-3 flex items-center gap-1 text-sm">
                        <span class="text-muted-foreground">{{ pharmacie.users_count }}</span> Utilisateur(s)
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="secondary" size="sm" as-child>
                            <Link :href="`/pharmacies/${pharmacie.id}`">Détails</Link>
                        </Button>
                        <Button
                            :variant="pharmacie.de_garde ? 'destructive' : 'outline'"
                            size="sm"
                            @click="toggleGarde(pharmacie, $event)"
                        >
                            <ShieldAlert class="mr-1 size-4" />
                            {{ pharmacie.de_garde ? 'Retirer Garde' : 'De Garde' }}
                        </Button>
                        <Button variant="ghost" size="icon" class="text-red-600" @click="openDeleteModal(pharmacie)">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Vue Carte -->
            <div v-else class="relative rounded-xl border border-white/80 overflow-hidden bg-white dark:border-white/10 dark:bg-white/95" style="min-height: 480px;">
                <div ref="mapContainerRef" class="h-[480px] w-full" />
                <div class="absolute left-4 top-4 z-[1000] rounded-lg border bg-white p-3 shadow-md">
                    <p class="mb-2 text-sm font-semibold">Légende</p>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-4 rounded-full bg-[#3b82f6] border-2 border-white shadow" />
                            Pharmacie de jour
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-4 rounded-full bg-[#1e3a5f] border-2 border-white shadow" />
                            Pharmacie de nuit
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-4 rounded-full bg-[#ef4444] border-2 border-white shadow" />
                            Pharmacie 24h/24
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-4 rounded-full bg-[#22c55e] border-2 border-white shadow" />
                            De garde
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal création -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-h-[90vh] max-w-[min(28rem,calc(100vw-2rem))] overflow-y-auto overflow-x-hidden">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Building2 class="size-5 text-[#459cd1]" />
                        Créer une nouvelle pharmacie
                    </DialogTitle>
                </DialogHeader>

                <form class="min-w-0 space-y-6" @submit.prevent="submitCreate">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="designation">Nom de la pharmacie</Label>
                            <Input
                                id="designation"
                                v-model="form.designation"
                                placeholder="Ex : Fofana Didier"
                            />
                            <p v-if="errors.designation" class="text-sm text-red-600">{{ errors.designation }}</p>
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="adresse">Adresse</Label>
                            <Input
                                id="adresse"
                                v-model="form.adresse"
                                placeholder="Ex : 20 rue Loby Moungali"
                            />
                            <p v-if="errors.adresse" class="text-sm text-red-600">{{ errors.adresse }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="telephone">Téléphone</Label>
                            <Input
                                id="telephone"
                                v-model="form.telephone"
                                placeholder="+242 06 800 8008"
                            />
                            <p v-if="errors.telephone" class="text-sm text-red-600">{{ errors.telephone }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="email">Email de la pharmacie</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="Exemple@gmail.com"
                            />
                            <p v-if="errors.email" class="text-sm text-red-600">{{ errors.email }}</p>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-lg border border-input bg-muted/30 p-4">
                        <Label>Type de pharmacie</Label>
                        <div class="flex flex-col gap-3 sm:flex-row sm:gap-4">
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="form.type_pharmacie_id === (typeJour?.id?.toString() ?? '') ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/20' : 'border-input hover:bg-muted/50'"
                            >
                                <input
                                    v-model="form.type_pharmacie_id"
                                    type="radio"
                                    :value="typeJour?.id?.toString() ?? ''"
                                    class="sr-only"
                                />
                                <Sun class="size-8 text-amber-500" />
                                <span class="text-center text-sm font-medium">Pharmacie de jour</span>
                                <span class="text-xs text-muted-foreground">08h-19h</span>
                            </label>
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
                                :class="form.type_pharmacie_id === (typeNuit?.id?.toString() ?? '') ? 'border-blue-400 bg-blue-50 dark:bg-blue-950/20' : 'border-input hover:bg-muted/50'"
                            >
                                <input
                                    v-model="form.type_pharmacie_id"
                                    type="radio"
                                    :value="typeNuit?.id?.toString() ?? ''"
                                    class="sr-only"
                                />
                                <Moon class="size-8 text-blue-500" />
                                <span class="text-center text-sm font-medium">Pharmacie de nuit</span>
                                <span class="text-xs text-muted-foreground">19h-08h</span>
                            </label>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="heure_ouverture">Heure d'ouverture</Label>
                                <Input
                                    id="heure_ouverture"
                                    v-model="form.heure_ouverture"
                                    type="time"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="heure_fermeture">Heure de fermeture</Label>
                                <Input
                                    id="heure_fermeture"
                                    v-model="form.heure_fermeture"
                                    type="time"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-lg border border-input bg-muted/30 p-4">
                        <Label class="text-muted-foreground">Infos propriétaire</Label>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="proprio_nom">Nom complet propriétaire</Label>
                                <Input
                                    id="proprio_nom"
                                    v-model="form.proprio_nom"
                                    placeholder="Ex : Fofana Didier"
                                />
                                <p v-if="errors.proprio_nom" class="text-sm text-red-600">{{ errors.proprio_nom }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="proprio_email">Email propriétaire</Label>
                                <Input
                                    id="proprio_email"
                                    v-model="form.proprio_email"
                                    type="email"
                                    placeholder="Exemple@gmail.com"
                                />
                                <p v-if="errors.proprio_email" class="text-sm text-red-600">{{ errors.proprio_email }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="proprio_tel">Téléphone propriétaire</Label>
                                <Input
                                    id="proprio_tel"
                                    v-model="form.proprio_tel"
                                    placeholder="+242 06 800 8008"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="destructive" @click="showModal = false">
                            Annuler
                        </Button>
                        <Button type="submit" class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]">
                            Créer la pharmacie
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmModal
            :open="showDeleteModal"
            title="Supprimer la pharmacie"
            :description="pharmacieToDelete ? `Êtes-vous sûr de vouloir supprimer « ${pharmacieToDelete.designation} » ? Cette action est irréversible.` : ''"
            confirm-text="Supprimer"
            @update:open="showDeleteModal = $event"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
