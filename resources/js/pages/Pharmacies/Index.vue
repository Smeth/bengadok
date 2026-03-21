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
    Users,
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

type PaginatedData<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
};

type PharmacieMap = {
    id: number;
    designation: string;
    adresse: string;
    latitude: number;
    longitude: number;
    de_garde: boolean;
    type_pharmacie: { designation: string } | null;
};

const props = defineProps<{
    pharmacies: PaginatedData<Pharmacie>;
    pharmaciesForMap: PharmacieMap[];
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
    const data = props.pharmacies.data ?? [];
    return data.length > 0 && data.every((p) => selectedIds.value.has(p.id));
});
const someSelected = computed(() => selectedIds.value.size > 0);

function toggleAll() {
    const data = props.pharmacies.data ?? [];
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
    const data = props.pharmacies.data ?? [];
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

// Carte Leaflet (onglet Maps)
const mapContainerRef = ref<HTMLElement | null>(null);
let mapInstance: ReturnType<typeof import('leaflet').map> | null = null;
let markersLayer: ReturnType<typeof import('leaflet').layerGroup> | null = null;
let leafletModule: typeof import('leaflet') | null = null;

function getMarkerColor(p: PharmacieMap): string {
    if (p.de_garde) return '#ef4444';
    const type = p.type_pharmacie?.designation?.toLowerCase() ?? '';
    if (type.includes('nuit')) return '#8b5cf6';
    if (type.includes('24') || type.includes('24h')) return '#22c55e';
    return '#3b82f6';
}

function createCustomMarker(p: PharmacieMap, L: any) {
    const color = getMarkerColor(p);
    const html = `
        <div class="relative flex items-center justify-center w-8 h-8 rounded-full shadow-md transition-transform hover:scale-110" style="background-color: ${color}; border: 2.5px solid white;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
            <div class="absolute -bottom-[5px] left-1/2 -translate-x-1/2 w-0 h-0 border-l-[5px] border-r-[5px] border-t-[6px] border-l-transparent border-r-transparent" style="border-top-color: ${color};"></div>
            <div class="absolute -bottom-[7px] left-1/2 -translate-x-1/2 w-0 h-0 border-l-[6px] border-r-[6px] border-t-[7px] border-l-transparent border-r-transparent -z-10" style="border-top-color: white;"></div>
        </div>
    `;
    const icon = L.divIcon({
        html,
        className: 'custom-leaflet-marker',
        iconSize: [32, 40],
        iconAnchor: [16, 40],
        popupAnchor: [0, -40],
    });
    return L.marker([p.latitude, p.longitude], { icon });
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
    props.pharmaciesForMap.forEach((p) => {
        if (p.latitude == null || p.longitude == null) return;
        const marker = createCustomMarker(p, L);
        marker.bindPopup(
            `<div class="p-1">
                <b class="text-[#0d6efd] text-sm">${p.designation}</b><br/>
                <span class="text-xs text-gray-500">${p.adresse}</span><br/>
                <a href="/pharmacies/${p.id}" class="inline-block mt-2 text-xs font-semibold underline text-[#3995D2]">Voir détails</a>
            </div>`
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
    if (viewMode.value === 'carte') nextTick(() => initMap());
});

watch(viewMode, async (mode) => {
    if (mode === 'carte') {
        await nextTick();
        initMap();
    } else {
        destroyMap();
    }
});

watch(() => props.pharmaciesForMap, () => {
    if (viewMode.value === 'carte' && mapInstance && markersLayer && leafletModule) {
        const L = leafletModule.default;
        markersLayer.clearLayers();
        props.pharmaciesForMap.forEach((p) => {
            if (p.latitude == null || p.longitude == null) return;
            const marker = createCustomMarker(p, L);
            marker.bindPopup(
                `<div class="p-1">
                    <b class="text-[#0d6efd] text-sm">${p.designation}</b><br/>
                    <span class="text-xs text-gray-500">${p.adresse}</span><br/>
                    <a href="/pharmacies/${p.id}" class="inline-block mt-2 text-xs font-semibold underline text-[#3995D2]">Voir détails</a>
                </div>`
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
        <div class="flex flex-1 flex-col gap-6 min-h-full overflow-x-auto rounded-xl p-8" style="background: linear-gradient(60.02deg, rgb(57, 149, 210) 35.89%, rgb(91, 182, 110) 92.85%);">
            <!-- Header & Actions -->
            <div class="flex flex-col gap-5 mb-2">
                <!-- Ligne Haut : Titre + Bouton Créer -->
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="rounded-lg bg-[#0d6efd] px-4 py-2 shadow-sm">
                        <h2 class="font-bold text-white text-[15px]">Gestion des pharmacies</h2>
                    </div>
                    <Button
                        class="bg-[#0d6efd] text-white hover:bg-blue-600 rounded-lg px-4 h-10 shadow-sm"
                        @click="openModal"
                    >
                        <Plus class="mr-2 size-4" />
                        Nouvelle Pharmacie
                    </Button>
                </div>

                <!-- Ligne Bas : Recherche + Toggle Vues/Badges -->
                <div class="flex flex-wrap items-center justify-between gap-4 w-full">
                    <form class="flex w-full max-w-[480px]" @submit.prevent="search">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Recherche une pharmacie..."
                                class="rounded-full bg-white border-0 pl-11 shadow-sm h-10 w-full focus-visible:ring-2 focus-visible:ring-blue-500 text-[14px]"
                                @keyup.enter="search"
                            />
                        </div>
                    </form>
                    
                    <div class="flex items-center gap-4">
                        <!-- Switch Liste / Card / Carte -->
                        <div class="flex bg-white rounded-lg shadow-sm p-1 gap-1">
                            <Button
                                :variant="viewMode === 'liste' ? 'secondary' : 'ghost'"
                                size="sm"
                                class="rounded-md h-8 px-3 font-semibold transition-colors"
                                :class="viewMode === 'liste' ? 'bg-gray-600 text-white hover:bg-gray-700' : 'text-gray-600 hover:text-gray-900'"
                                @click="viewMode = 'liste'"
                            >
                                <List class="mr-2 size-4" />
                                Liste
                            </Button>
                            <Button
                                :variant="viewMode === 'card' ? 'secondary' : 'ghost'"
                                size="sm"
                                class="rounded-md h-8 px-3 font-semibold transition-colors"
                                :class="viewMode === 'card' ? 'bg-gray-600 text-white hover:bg-gray-700' : 'text-gray-600 hover:text-gray-900'"
                                @click="viewMode = 'card'"
                            >
                                <LayoutGrid class="mr-2 size-4" />
                                Card
                            </Button>
                            <Button
                                :variant="viewMode === 'carte' ? 'secondary' : 'ghost'"
                                size="sm"
                                class="rounded-md h-8 px-3 font-semibold transition-colors"
                                :class="viewMode === 'carte' ? 'bg-gray-600 text-white hover:bg-gray-700' : 'text-gray-600 hover:text-gray-900'"
                                @click="viewMode = 'carte'"
                            >
                                <MapIcon class="mr-2 size-4" />
                                Carte
                            </Button>
                        </div>
                        
                        <!-- Badges Compteurs -->
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 min-w-[50px] items-center justify-center gap-1.5 rounded-[10px] bg-white text-red-600 font-extrabold shadow-sm px-3 border border-red-50">
                                <span class="text-[16px]">{{ stats.de_garde }}</span>
                                <ShieldAlert class="size-4" />
                            </div>
                            <div class="flex h-10 min-w-[50px] items-center justify-center gap-1.5 rounded-[10px] bg-[#0d6efd] text-white font-extrabold shadow-sm px-3">
                                <span class="text-[16px]">{{ stats.total }}</span>
                                <Building2 class="size-4" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                <div class="overflow-x-auto rounded-[20px] border border-gray-100 bg-white shadow-sm">
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
                            v-for="pharmacie in pharmacies.data"
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
            <div v-else-if="viewMode === 'card'" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="pharmacie in pharmacies.data"
                    :key="pharmacie.id"
                    class="rounded-[20px] bg-white p-6 shadow-sm flex flex-col transition-shadow hover:shadow-md h-full relative"
                >
                    <!-- Header Carte -->
                    <div class="mb-5 flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#e3eefa] text-[#0d6efd]">
                            <Sun v-if="pharmacie.type_pharmacie?.designation?.toLowerCase().includes('jour')" class="size-5" />
                            <Moon v-else-if="pharmacie.type_pharmacie?.designation?.toLowerCase().includes('nuit')" class="size-5" />
                            <Building2 v-else class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pr-16 mt-1">
                            <h3 class="font-extrabold text-[16px] text-gray-900 leading-tight">{{ pharmacie.designation }}</h3>
                        </div>
                    </div>
                    
                    <!-- Badges (Absolus en haut à droite) -->
                    <div class="absolute top-6 right-6 flex items-end gap-1.5 flex-col">
                        <span v-if="pharmacie.type_pharmacie?.designation?.toLowerCase().includes('jour')" class="rounded-lg bg-[#0d6efd] px-2.5 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                            Jour
                        </span>
                        <span v-if="pharmacie.de_garde" class="rounded-lg bg-red-600 px-2.5 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                            De Garde
                        </span>
                    </div>

                    <!-- Infos détaillées -->
                    <div class="space-y-3 text-[13px] text-gray-500 mb-6 flex-1">
                        <p class="flex items-center gap-3">
                            <MapPin class="size-4 shrink-0 text-gray-400" />
                            <span class="truncate">{{ pharmacie.adresse }}</span>
                        </p>
                        <p class="flex items-center gap-3">
                            <Clock class="size-4 shrink-0 text-gray-400" />
                            <span>{{ pharmacie.heurs ? `${pharmacie.heurs.ouverture} - ${pharmacie.heurs.fermeture}` : '-' }}</span>
                        </p>
                        <p class="flex items-center gap-3">
                            <Phone class="size-4 shrink-0 text-gray-400" />
                            <span>{{ pharmacie.telephone }}</span>
                        </p>
                        <p v-if="pharmacie.email" class="flex items-center gap-3">
                            <Mail class="size-4 shrink-0 text-gray-400" />
                            <span class="truncate" :title="pharmacie.email">{{ pharmacie.email }}</span>
                        </p>
                    </div>

                    <!-- Bloc Propriétaire -->
                    <div class="mb-5 rounded-[12px] border border-gray-200 p-3">
                        <p class="text-[11px] font-bold text-gray-400 mb-0.5">Propriétaire</p>
                        <p class="font-bold text-[13px] text-gray-900 truncate">{{ pharmacie.proprio_nom || '-' }}</p>
                        <p v-if="pharmacie.proprio_tel" class="text-[12px] text-gray-500 mt-0.5">
                            <Phone class="size-3 inline-block mr-1 text-gray-400"/>{{ pharmacie.proprio_tel }}
                        </p>
                    </div>

                    <!-- Pied de carte -->
                    <div class="mt-auto">
                        <div class="mb-4 rounded-lg bg-gray-50/50 p-2 px-3 border border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-[12px] text-gray-500">
                                <Users class="size-4" />
                                <span>Utilisateurs liés</span>
                            </div>
                            <span class="font-bold text-gray-900 text-[14px]">{{ pharmacie.users_count }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button variant="secondary" class="flex-1 bg-[#6c757d] text-white hover:bg-[#5a6268] h-10 rounded-[10px] font-bold shadow-sm" as-child>
                                <Link :href="`/pharmacies/${pharmacie.id}`">Détails</Link>
                            </Button>
                            <Button
                                v-if="pharmacie.de_garde"
                                variant="outline"
                                class="h-10 px-3 rounded-[10px] border border-red-200 bg-white text-red-600 hover:bg-red-50 hover:text-red-700 font-bold shadow-sm flex items-center gap-1.5"
                                @click="toggleGarde(pharmacie, $event)"
                            >
                                <ShieldAlert class="size-[18px]" />
                                Retirer Garde
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                class="h-10 px-3 rounded-[10px] border border-red-200 bg-white text-red-600 hover:bg-red-50 hover:text-red-700 font-bold shadow-sm flex items-center gap-1.5"
                                @click="toggleGarde(pharmacie, $event)"
                            >
                                <ShieldAlert class="size-[18px]" />
                                De Garde
                            </Button>
                            <Button variant="outline" class="h-10 w-10 p-0 rounded-[10px] border border-red-200 bg-white text-red-600 hover:bg-red-50 hover:text-red-700 shadow-sm shrink-0" @click="openDeleteModal(pharmacie)">
                                <Trash2 class="size-[18px]" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vue Carte (Maps) -->
            <div v-else-if="viewMode === 'carte'" class="relative rounded-[20px] border-[6px] border-white/40 overflow-hidden shadow-md" style="min-height: 550px;">
                <div ref="mapContainerRef" class="h-[550px] w-full" />
                <div class="absolute left-6 top-6 z-[1000] rounded-xl border border-gray-100 bg-white/95 p-4 shadow-lg backdrop-blur-md min-w-[200px]">
                    <p class="mb-3 text-[14px] font-extrabold text-gray-900">Légende</p>
                    <div class="space-y-3 text-[13px] font-semibold text-gray-600">
                        <div class="flex items-center gap-3">
                            <span class="inline-block size-4 rounded-full bg-[#3b82f6] border-2 border-white shadow-sm" />
                            Pharmacie de jour
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-block size-4 rounded-full bg-[#8b5cf6] border-2 border-white shadow-sm" />
                            Pharmacie de nuit
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-block size-4 rounded-full bg-[#22c55e] border-2 border-white shadow-sm" />
                            Pharmacie 24h/24
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-block size-4 rounded-full bg-[#ef4444] border-2 border-white shadow-sm" />
                            De garde
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination (Liste et Card) — carte lisible sur dégradé, style BengaDok -->
            <div
                v-if="viewMode === 'liste' || viewMode === 'card'"
                class="mt-6 flex flex-col gap-4 rounded-[28px] border border-white/90 bg-white/95 px-4 py-4 shadow-[0_8px_32px_rgba(0,0,0,0.14)] backdrop-blur-md sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-4"
            >
                <p
                    class="text-center text-[13px] leading-snug text-[#5c5959] sm:text-left sm:text-[14px]"
                >
                    Affichage de
                    <span class="tabular-nums font-black text-black">{{ pharmacies.from }}</span>
                    à
                    <span class="tabular-nums font-black text-black">{{ pharmacies.to }}</span>
                    sur
                    <span class="tabular-nums font-black text-[#3995d2]">{{ pharmacies.total }}</span>
                    résultats
                </p>
                <nav
                    class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-end"
                    aria-label="Pagination"
                >
                    <template v-for="(link, pIndex) in pharmacies.links" :key="pIndex">
                        <div
                            v-if="link.url === null"
                            class="flex min-h-9 min-w-9 items-center justify-center rounded-full bg-gray-100 px-2.5 py-2 text-[13px] font-semibold text-gray-400"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="flex min-h-9 min-w-9 items-center justify-center rounded-full border px-3 py-2 text-[13px] font-bold transition-all duration-150"
                            :class="
                                link.active
                                    ? 'border-[#3995d2] bg-[#3995d2] text-white shadow-[0_4px_12px_rgba(57,149,210,0.45)] ring-2 ring-white/70'
                                    : 'border-gray-200/90 bg-white text-[#5c5959] shadow-sm hover:border-[#3995d2]/50 hover:text-[#3995d2] hover:shadow-md'
                            "
                            v-html="link.label"
                        />
                    </template>
                </nav>
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
