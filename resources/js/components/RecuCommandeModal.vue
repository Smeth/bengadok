<script setup lang="ts">
import { computed } from 'vue';
import { X, User, Building2, Pill, Banknote, Truck } from 'lucide-vue-next';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import type { CommandeDetail } from '@/types';

const props = defineProps<{
    open: boolean;
    commande: CommandeDetail | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const sousTotal = computed(() => {
    const cmd = props.commande;
    if (!cmd?.produits) return 0;
    return cmd.produits.reduce((s, p) => s + p.pivot.quantite * Number(p.pivot.prix_unitaire), 0);
});

const livraison = computed(() => Number(props.commande?.montant_livraison?.designation ?? 0));
const totalPaye = computed(() => sousTotal.value + livraison.value);

function formatDate(d: string) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function getClientName() {
    const c = props.commande?.client;
    if (!c) return '-';
    return [c.prenom, c.nom].filter(Boolean).join(' ') || '-';
}

function imprimer() {
    if (!props.commande) return;
    // Ouvre la page reçu et déclenche l'impression
    window.open(`/commandes/${props.commande.id}/recu?print=1`, '_blank', 'noopener,noreferrer');
}

function telecharger() {
    if (!props.commande) return;
    // Télécharge le PDF généré côté serveur
    window.open(`/commandes/${props.commande.id}/recu?download=1`, '_blank', 'noopener,noreferrer');
}

function fermer() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-w-[560px] max-h-[90vh] overflow-hidden p-0 border-0 bg-transparent shadow-none print:block"
            :class="{ 'flex items-center justify-center': open }"
        >
            <!-- Fond gradient + contenu (visible à l'écran) -->
            <div
                class="relative max-h-[90vh] overflow-y-auto rounded-xl"
                style="background: linear-gradient(60deg, rgb(57, 149, 210) 35.89%, rgb(91, 182, 110) 92.85%)"
            >
                <!-- Barre d'actions (hors zone d'impression) -->
                <div class="sticky top-0 z-20 flex items-center justify-between gap-3 px-4 py-3 print:hidden">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="flex h-9 items-center justify-center rounded-[10px] bg-[#0d6efd] px-4 text-[14px] font-bold text-white transition-colors hover:bg-blue-700"
                            @click="imprimer"
                        >
                            Imprimer reçu client
                        </button>
                        <button
                            type="button"
                            class="flex h-9 items-center justify-center rounded-[10px] border border-[#0d6efd] bg-white px-4 text-[14px] font-bold text-[#0d6efd] transition-colors hover:bg-blue-50"
                            @click="telecharger"
                        >
                            Télécharger
                        </button>
                    </div>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-lg bg-white/20 text-white transition-colors hover:bg-white/30 print:hidden"
                        aria-label="Fermer"
                        @click="fermer"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Carte reçu (contenu imprimable) -->
                <div
                    v-if="commande"
                    ref="recuContent"
                    class="mx-auto mb-4 w-full max-w-[520px] overflow-hidden rounded-[14px] border-2 border-[#3995d2] bg-white px-5 py-5 print:border print:shadow-none"
                >
                    <!-- Logo + Titre -->
                    <div class="mb-4 flex flex-col items-center">
                        <div class="mb-2 flex size-12 items-center justify-center rounded-full bg-[#3995D2]">
                            <svg class="size-7" viewBox="0 0 32 32" fill="none">
                                <circle cx="16" cy="16" r="14" fill="white" />
                                <path
                                    d="M16 8C12.7 8 10 10.7 10 14C10 17.3 12.7 20 16 20C19.3 20 22 17.3 22 14C22 10.7 19.3 8 16 8ZM16 18C13.8 18 12 16.2 12 14C12 11.8 13.8 10 16 10C18.2 10 20 11.8 20 14C20 16.2 18.2 18 16 18Z"
                                    fill="#3995D2"
                                />
                            </svg>
                        </div>
                        <h1 class="text-center text-[16px] font-bold uppercase tracking-wide text-[#666]">
                            Reçu Commande Client
                        </h1>
                    </div>

                    <!-- En-tête : N° commande, Date, Statut -->
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 pb-4">
                        <div>
                            <p class="text-[13px] text-[rgba(0,0,0,0.74)]">
                                Numéro Commande : <span class="font-bold text-[14px]">{{ commande.numero }}</span>
                            </p>
                            <p class="mt-1 text-[13px] text-[rgba(0,0,0,0.74)]">
                                Date : <span class="font-bold">{{ formatDate(commande.date) }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-[10px] border border-[#016630] bg-[#016630] px-3 py-1">
                            <Truck class="size-4 text-white" />
                            <span class="text-[12px] font-bold text-white">Livrée</span>
                        </div>
                    </div>

                    <!-- Informations du client -->
                    <div class="mb-4">
                        <div class="mb-2 flex items-center gap-2">
                            <div class="flex size-6 items-center justify-center rounded-full bg-[rgba(92,89,89,0.25)]">
                                <User class="size-3 text-[#5c5959]" />
                            </div>
                            <h2 class="text-[14px] font-bold text-[rgba(92,89,89,0.6)]">Informations du client</h2>
                        </div>
                        <div class="space-y-0.5 pl-8 text-[13px] text-[rgba(0,0,0,0.74)]">
                            <p><span class="font-light text-black">Nom :</span> {{ getClientName() }}</p>
                            <p><span class="font-light text-black">Tel :</span> {{ commande.client?.tel ?? '-' }}</p>
                            <p><span class="font-light text-black">Adresse :</span> {{ commande.client?.adresse ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="my-4 border-t border-gray-200" />

                    <!-- Pharmacie -->
                    <div class="mb-4">
                        <div class="mb-2 flex items-center gap-2">
                            <div class="flex size-6 items-center justify-center rounded-full bg-[rgba(92,89,89,0.25)]">
                                <Building2 class="size-3 text-[#5c5959]" />
                            </div>
                            <h2 class="text-[14px] font-bold text-[rgba(92,89,89,0.6)]">Pharmacie</h2>
                        </div>
                        <div class="space-y-0.5 pl-8 text-[13px]">
                            <p class="font-medium text-black">{{ commande.pharmacie?.designation ?? '-' }}</p>
                            <p class="text-[rgba(0,0,0,0.74)]">{{ commande.pharmacie?.adresse ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="my-4 border-t border-gray-200" />

                    <!-- Détails des médicaments -->
                    <div class="mb-4">
                        <div class="mb-2 flex items-center gap-2">
                            <div class="flex size-6 items-center justify-center rounded-full bg-[rgba(92,89,89,0.25)]">
                                <Pill class="size-3 text-[#5c5959]" />
                            </div>
                            <h2 class="text-[14px] font-bold text-[rgba(92,89,89,0.6)]">Détails des Médicaments</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[12px]">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left">
                                        <th class="pb-1 font-light text-black">Médicaments</th>
                                        <th class="pb-1 font-light text-black">Qté</th>
                                        <th class="pb-1 font-light text-black">Prix unit.</th>
                                        <th class="pb-1 font-light text-black text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="p in (commande.produits ?? [])"
                                        :key="p.id"
                                        class="border-b border-gray-100"
                                    >
                                        <td class="py-1 font-bold">{{ p.designation }} {{ p.dosage ?? '' }}</td>
                                        <td class="py-1 font-light">{{ p.pivot.quantite }}</td>
                                        <td class="py-1 font-light">{{ Number(p.pivot.prix_unitaire).toLocaleString('fr-FR') }} FCFA</td>
                                        <td class="py-1 text-right font-light">
                                            {{ (p.pivot.quantite * Number(p.pivot.prix_unitaire)).toLocaleString('fr-FR') }} FCFA
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="my-4 border-t border-gray-200" />

                    <!-- Informations paiement -->
                    <div class="mb-4">
                        <div class="mb-2 flex items-center gap-2">
                            <div class="flex size-6 items-center justify-center rounded-full bg-[rgba(92,89,89,0.25)]">
                                <Banknote class="size-3 text-[#5c5959]" />
                            </div>
                            <h2 class="text-[14px] font-bold text-[rgba(92,89,89,0.6)]">Informations paiement</h2>
                        </div>
                        <div class="space-y-1 pl-8">
                            <div class="flex items-center justify-between text-[13px]">
                                <span class="font-light text-black">Sous-Total</span>
                                <span class="font-bold">{{ sousTotal.toLocaleString('fr-FR') }} FCFA</span>
                            </div>
                            <div v-if="livraison > 0" class="flex items-center justify-between text-[13px]">
                                <span class="font-light text-black">Livraison</span>
                                <span class="font-bold">{{ livraison.toLocaleString('fr-FR') }} FCFA</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 text-[16px]">
                                <span class="font-bold text-black">Total payé</span>
                                <span class="font-bold text-black">{{ totalPaye.toLocaleString('fr-FR') }} FCFA</span>
                            </div>
                            <div v-if="commande.mode_paiement" class="mt-2">
                                <span class="rounded-lg border border-[#016630] bg-white px-3 py-1.5 text-[13px] font-bold text-[#016630]">
                                    {{ commande.mode_paiement.designation }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer contact -->
                    <div class="rounded-lg bg-[#0d6efd] px-4 py-2.5 text-white">
                        <p class="text-[12px]">
                            Pour tous vos besoins en médicaments contactez-nous : <strong>+242 06 121 21 54</strong> — <strong>BengaDok</strong>
                        </p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

