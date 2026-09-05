const fs = require('fs');

const path = 'resources/js/pages/Commandes/Index.vue';
const lines = fs.readFileSync(path, 'utf8').split(/\r?\n/);

const drawerBlock = `
const detailDrawerRef = ref<InstanceType<typeof CommandeDetailDrawer> | null>(null);
const relancerCommande = ref<CommandeDetail | null>(null);
const recuCommande = ref<CommandeDetail | null>(null);
const showEnregistrementModal = ref(false);
const showRecuModal = ref(false);
const showRelancerModal = ref(false);

function openDetail(id: number) {
    detailDrawerRef.value?.openDetail(id);
}

function onOpenRecu(commande: CommandeDetail) {
    recuCommande.value = commande;
    showRecuModal.value = true;
}

function onOpenRelancer(commande: CommandeDetail) {
    relancerCommande.value = commande;
    errorsRelancer.value = {};
    ensureReferentiels();
    showRelancerModal.value = true;
}

watch(
    () => props.openDetailCommandeId,
    (id) => {
        if (id) {
            openDetail(id);
        }
    },
    { immediate: true },
);
`.trim();

const head = lines.slice(0, 168);
const motifs = lines.slice(219, 247);
const part2 = lines.slice(248, 254);
const bulk = lines.slice(307, 393);
const filters = lines.slice(396, 441);
const scriptTail = lines.slice(860, 1031);

const scriptLines = [
    ...head,
    '',
    ...motifs,
    '',
    ...part2,
    drawerBlock,
    '',
    ...bulk,
    '',
    ...filters,
    '',
    ...scriptTail,
];

// Template: lines 1033-end, replace status filters and drawer
let templateLines = lines.slice(1033);
const templateStr = templateLines.join('\n');

const statusFiltersOld = `                <!-- Filtres statut -->
                <div :class="moduleFilterPanelClass">
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-all"
                            :class="
                                !filters.status
                                    ? 'bg-[#459cd1] text-white ring-2 ring-[#459cd1]/30'
                                    : 'border border-[#459cd1] bg-white text-[#459cd1] hover:bg-[#459cd1]/5'
                            "
                            @click="filtrer('status', '')"
                        >
                            Toutes
                        </button>
                        <button
                            v-for="s in statuts"
                            :key="s.key"
                            type="button"
                            class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-all whitespace-nowrap hover:opacity-90"
                            :style="commandeStatutFilterStyle(
                                s,
                                filters.status === s.key,
                            )"
                            @click="
                                filtrer(
                                    'status',
                                    filters.status === s.key ? '' : s.key,
                                )
                            "
                        >
                            {{ s.label }} ({{ stats[s.statsKey] ?? 0 }})
                        </button>
                    </div>
                </div>`;

const statusFiltersNew = `                <CommandeStatusFilters
                    :statuts="statuts"
                    :stats="stats ?? {}"
                    :active-status="filters.status"
                    @filter="(status) => filtrer('status', status)"
                />`;

let newTemplate = templateStr.replace(statusFiltersOld, statusFiltersNew);

// Remove drawer + valider + annuler modals (keep bulk annuler)
const drawerStart = newTemplate.indexOf('        <!-- Modal Détails');
const bulkStart = newTemplate.indexOf('        <!-- Modal Annulation groupée -->');
if (drawerStart === -1 || bulkStart === -1) {
    throw new Error('Template markers not found');
}

const drawerReplacement = `        <CommandeDetailDrawer
            ref="detailDrawerRef"
            :can-manage-commandes="canManageCommandes"
            :can-create-commande="canCreateCommande"
            :livreurs="livreurs"
            :montants-livraison="montantsLivraison"
            :modes-paiement="modesPaiement"
            :parapharma-produit-types="parapharmaProduitTypes"
            :motif-options="motifOptions"
            :motifs-relance="motifsRelance"
            :motif-label-by-slug="motifLabelBySlug"
            :ensure-referentiels="ensureReferentiels"
            @open-recu="onOpenRecu"
            @open-relancer="onOpenRelancer"
        />

`;

newTemplate =
    newTemplate.slice(0, drawerStart) +
    drawerReplacement +
    newTemplate.slice(bulkStart);

// Fix Recu and Relancer modals commande prop
newTemplate = newTemplate.replace(
    ':commande="detailCommande ?? undefined"',
    ':commande="relancerCommande ?? undefined"',
);
newTemplate = newTemplate.replace(
    ':commande="detailCommande"',
    ':commande="recuCommande"',
);

const out = [...scriptLines, '</script>', '', newTemplate].join('\n');
fs.writeFileSync(path, out, 'utf8');
console.log('Done:', lines.length, '->', out.split('\n').length, 'lines');
