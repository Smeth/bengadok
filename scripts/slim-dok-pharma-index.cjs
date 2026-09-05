const fs = require('fs');

const indexPath = 'resources/js/pages/DokPharma/Index.vue';
const lines = fs.readFileSync(indexPath, 'utf8').split(/\r?\n/);

const newScript = `<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DokPharmaCommandesTabs from '@/components/dok-pharma/DokPharmaCommandesTabs.vue';
import DokPharmaOngletAPreparer from '@/components/dok-pharma/DokPharmaOngletAPreparer.vue';
import DokPharmaOngletEnAttente from '@/components/dok-pharma/DokPharmaOngletEnAttente.vue';
import DokPharmaOngletLivrees from '@/components/dok-pharma/DokPharmaOngletLivrees.vue';
import DokPharmaOngletNouvelles from '@/components/dok-pharma/DokPharmaOngletNouvelles.vue';
import ModulePagination from '@/components/shared/ModulePagination.vue';
import AppToast from '@/components/AppToast.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
import DokPharmaOrdonnanceViewerModal from '@/components/dok-pharma/DokPharmaOrdonnanceViewerModal.vue';
import DokPharmaValiderRetraitModal from '@/components/dok-pharma/DokPharmaValiderRetraitModal.vue';
import { Input } from '@/components/ui/input';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';
import { useDokPharmaAccordion } from '@/composables/useDokPharmaAccordion';
import { modulePaginationWrapperClass } from '@/lib/bengadokUi';
import type {
    DokPharmaCommande,
    DokPharmaPaginatedCommandes,
} from '@/lib/dokPharmaCommande';

const props = defineProps<{
    commandes: DokPharmaPaginatedCommandes;
    stats: {
        nouvelles: number;
        en_attente: number;
        a_preparer: number;
        livrees: number;
    };
    onglet: string;
    search?: string;
    canViewHistorique?: boolean;
}>();

const searchQuery = ref(props.search ?? '');
watch(
    () => props.search,
    (s) => {
        searchQuery.value = s ?? '';
    },
);

function commandesQueryParams(onglet: string) {
    const q = searchQuery.value.trim();
    return {
        onglet,
        ...(q ? { search: q } : {}),
    };
}

watchDebounced(
    searchQuery,
    (val) => {
        const q = val.trim();
        const serverQ = (props.search ?? '').trim();
        if (q === serverQ) return;
        router.get('/dok-pharma/commandes', commandesQueryParams(props.onglet), {
            preserveScroll: true,
            replace: true,
        });
    },
    { debounce: 400 },
);

function changeOnglet(o: string) {
    router.get('/dok-pharma/commandes', commandesQueryParams(o), {
        preserveScroll: true,
    });
}

const { expandedCards, toggleCard } = useDokPharmaAccordion();

const confirmModal = ref<{ open: boolean; cmd: DokPharmaCommande | null }>({
    open: false,
    cmd: null,
});

const ordModal = ref({ open: false, url: '', isPdf: false, numero: '' });

const dispoSuccessToast = ref({ show: false, title: '', description: '' });
const retraitSuccessToast = ref({ show: false, title: '', description: '' });

function showToast(
    target: typeof dispoSuccessToast,
    title: string,
    description?: string,
) {
    target.value = { show: true, title, description: description ?? '' };
}

function onEnvoiSuccess() {
    showToast(
        dispoSuccessToast,
        'Envoyé au back-office',
        'Disponibilité et prix transmis. La commande est en attente de validation.',
    );
}

function openOrdonnance(cmd: DokPharmaCommande) {
    ordModal.value = {
        open: true,
        url: cmd.ordonnance_url ?? '',
        isPdf: cmd.ordonnance_is_pdf ?? false,
        numero: cmd.numero,
    };
}

function closeOrdonnance() {
    ordModal.value.open = false;
}

function askValiderAchat(cmd: DokPharmaCommande) {
    confirmModal.value = { open: true, cmd };
}

function annulerConfirm() {
    confirmModal.value = { open: false, cmd: null };
}

function confirmerAchat() {
    if (!confirmModal.value.cmd) return;
    const id = confirmModal.value.cmd.id;
    confirmModal.value = { open: false, cmd: null };
    router.post(
        \`/dok-pharma/\${id}/valider-retrait\`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(
                    retraitSuccessToast,
                    'Retrait validé',
                    'La commande a bien été retirée par le livreur en pharmacie.',
                );
            },
        },
    );
}
</script>`;

// Template: keep lines 451-489 (search), replace tabs and content
const templateHead = lines.slice(450, 489); // from <template> through search bar end
const templateTail = lines.slice(2186); // pagination + modals + closing

let templateMiddle = `
            <DokPharmaCommandesTabs
                :onglet="onglet"
                :stats="stats"
                :can-view-historique="canViewHistorique"
                @change="changeOnglet"
            />

            <div class="flex-1 space-y-3 pb-6">
                <DokPharmaOngletNouvelles
                    v-if="onglet === 'nouvelles'"
                    :commandes="commandes.data"
                    @open-ordonnance="openOrdonnance"
                    @envoi-success="onEnvoiSuccess"
                />
                <DokPharmaOngletEnAttente
                    v-else-if="onglet === 'en_attente'"
                    :commandes="commandes.data"
                    :expanded-cards="expandedCards"
                    @toggle-card="toggleCard"
                    @open-ordonnance="openOrdonnance"
                />
                <DokPharmaOngletAPreparer
                    v-else-if="onglet === 'a_preparer'"
                    :commandes="commandes.data"
                    :expanded-cards="expandedCards"
                    @toggle-card="toggleCard"
                    @open-ordonnance="openOrdonnance"
                    @valider-achat="askValiderAchat"
                />
                <DokPharmaOngletLivrees
                    v-else-if="onglet === 'livrees'"
                    :commandes="commandes.data"
                    :expanded-cards="expandedCards"
                    @toggle-card="toggleCard"
                    @open-ordonnance="openOrdonnance"
                />
            </div>
`;

// templateHead includes `<template>` and layout start - fix to not duplicate
const templateStart = templateHead.slice(1); // skip `<template>` line, we'll add it

const out =
    newScript +
    '\\n\\n<template>\\n' +
    templateStart.join('\\n') +
    templateMiddle +
    templateTail.join('\\n');

fs.writeFileSync(indexPath, out.replace(/\\n/g, '\n'), 'utf8');
console.log('Index slimmed to', out.split('\\n').length, 'lines');
