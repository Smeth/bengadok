<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Accessibility,
    Activity,
    Baby,
    Facebook,
    HandHeart,
    HelpCircle,
    Instagram,
    MessageCircle,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';

const NICHE_OPTIONS = [
    { id: 'parent', label: 'Parent', icon: Baby },
    { id: 'personnes_agees', label: 'Personnes âgées', icon: Accessibility },
    { id: 'maladie_chronique', label: 'Maladie chronique', icon: Activity },
    { id: 'aidant_familial', label: 'Aidant familial', icon: HandHeart },
] as const;

const CANAL_OPTIONS = [
    { id: 'facebook', label: 'Facebook', icon: Facebook },
    { id: 'instagram', label: 'Instagram', icon: Instagram },
    { id: 'tiktok', label: 'TikTok', icon: null },
    { id: 'bouche_a_oreille', label: 'Bouche à oreille', icon: MessageCircle },
    { id: 'autres', label: 'Autres', icon: HelpCircle },
] as const;

type NicheId = (typeof NICHE_OPTIONS)[number]['id'];
type CanalId = (typeof CANAL_OPTIONS)[number]['id'];

const props = defineProps<{
    open: boolean;
    clientId: number;
    initialNiches: string[];
    initialCanal: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedNiches = ref<NicheId[]>([]);
const selectedCanal = ref<CanalId | null>(null);
const processing = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedNiches.value = (props.initialNiches ?? []).filter((n): n is NicheId =>
                NICHE_OPTIONS.some((o) => o.id === n),
            );
            const c = props.initialCanal;
            selectedCanal.value =
                c && CANAL_OPTIONS.some((o) => o.id === c) ? (c as CanalId) : null;
        }
    },
);

function toggleNiche(id: NicheId) {
    const i = selectedNiches.value.indexOf(id);
    if (i === -1) {
        selectedNiches.value = [...selectedNiches.value, id];
    } else {
        selectedNiches.value = selectedNiches.value.filter((x) => x !== id);
    }
}

function selectCanal(id: CanalId) {
    selectedCanal.value = selectedCanal.value === id ? null : id;
}

function close() {
    emit('update:open', false);
}

function submit() {
    processing.value = true;
    router.patch(
        `/clients/${props.clientId}/enrichissement-profil`,
        {
            niches: selectedNiches.value,
            canal_acquisition: selectedCanal.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                close();
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            :show-close-button="false"
            class="max-h-[min(90vh,760px)] gap-0 overflow-hidden rounded-[15px] border-[#ccc5c5] p-0 sm:max-w-[min(897px,calc(100vw-2rem))]"
        >
            <!-- En-tête Figma : titre bleu + fermeture -->
            <div
                class="relative flex shrink-0 items-center gap-3 border-b border-[#e8e4e4] bg-gradient-to-r from-[#3995d2]/12 via-white to-[#5bb66e]/10 px-5 py-3 pr-14 sm:px-6"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[rgba(13,110,253,0.13)] text-[#3995d2]"
                >
                    <UserPlus class="size-5" />
                </div>
                <h2 class="text-lg font-black tracking-wide text-[#3995d2] sm:text-xl">Rajouter les informations</h2>
                <button
                    type="button"
                    class="absolute right-3 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-[#dc3545] transition-colors hover:bg-red-50"
                    aria-label="Fermer"
                    @click="close"
                >
                    <X class="size-6" stroke-width="2.5" />
                </button>
            </div>

            <div class="max-h-[min(58vh,580px)] overflow-y-auto px-5 py-4 sm:px-6">
                <!-- Niches -->
                <div class="rounded-[10px] border border-[#ccc5c5] bg-white p-4 sm:p-5">
                    <h3 class="text-base font-black text-[rgba(92,89,89,0.55)] sm:text-lg">
                        Sélectionner la niche client<span class="font-mono font-normal">(s)</span>
                    </h3>
                    <p class="mt-1 text-sm text-foreground sm:text-base">
                        Sélectionnez une ou plusieurs niches correspondant au profil du client.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3 sm:gap-4">
                        <button
                            v-for="opt in NICHE_OPTIONS"
                            :key="opt.id"
                            type="button"
                            class="flex h-[63px] w-[73px] shrink-0 flex-col items-center justify-between rounded-[9px] border border-[rgba(92,89,89,0.4)] bg-white py-1.5 text-center transition-all hover:bg-slate-50/80"
                            :class="
                                selectedNiches.includes(opt.id)
                                    ? 'ring-2 ring-[#3995d2] ring-offset-1 ring-offset-background'
                                    : ''
                            "
                            @click="toggleNiche(opt.id)"
                        >
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-[rgba(92,89,89,0.35)] bg-[rgba(13,110,253,0.13)] text-[#5c5959]"
                                :class="selectedNiches.includes(opt.id) ? 'border-[#3995d2]/50' : ''"
                            >
                                <component :is="opt.icon" class="size-[22px] shrink-0" stroke-width="2" />
                            </div>
                            <span class="px-0.5 text-[8px] font-bold leading-tight text-black sm:text-[9px]">
                                {{ opt.label }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Canal -->
                <div class="mt-4 rounded-[10px] border border-[#ccc5c5] bg-white p-4 sm:mt-5 sm:p-5">
                    <h3 class="text-base font-black text-[rgba(92,89,89,0.55)] sm:text-lg">Canal d'acquisition</h3>
                    <p class="mt-1 text-sm text-foreground sm:text-base">
                        Comment ce client a-t-il découvert BengaDok ? Cette information aide à optimiser nos investissements
                        marketing.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3 sm:gap-4">
                        <button
                            v-for="opt in CANAL_OPTIONS"
                            :key="opt.id"
                            type="button"
                            class="flex h-[63px] w-[73px] shrink-0 flex-col items-center justify-between rounded-[9px] border border-[rgba(92,89,89,0.4)] bg-white py-1.5 text-center transition-all hover:bg-slate-50/80"
                            :class="
                                selectedCanal === opt.id
                                    ? 'ring-2 ring-[#3995d2] ring-offset-1 ring-offset-background'
                                    : ''
                            "
                            @click="selectCanal(opt.id)"
                        >
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-[rgba(92,89,89,0.35)] bg-[rgba(13,110,253,0.13)] text-[#5c5959]"
                                :class="selectedCanal === opt.id ? 'border-[#3995d2]/50' : ''"
                            >
                                <component
                                    v-if="opt.icon"
                                    :is="opt.icon"
                                    class="size-[22px] shrink-0"
                                    stroke-width="2"
                                />
                                <svg
                                    v-else
                                    class="size-[22px] shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"
                                    />
                                </svg>
                            </div>
                            <span class="px-0.5 text-[8px] font-bold leading-tight text-black sm:text-[9px]">
                                {{ opt.label }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pied Figma -->
            <div
                class="flex shrink-0 flex-wrap items-center justify-center gap-4 border-t border-[#e0dcdc] bg-gradient-to-b from-white to-slate-50/90 px-5 py-4 sm:px-6"
            >
                <Button
                    type="button"
                    variant="destructive"
                    class="h-11 min-w-[120px] rounded-[15px] bg-[#dc3545] px-6 text-base font-black text-white hover:bg-[#c82333]"
                    :disabled="processing"
                    @click="close"
                >
                    Annuler
                </Button>
                <Button
                    type="button"
                    class="h-11 min-w-[185px] rounded-[15px] bg-[#0d6efd] px-6 text-base font-black text-white hover:bg-[#0b5ed7]"
                    :disabled="processing"
                    @click="submit"
                >
                    Rajouter au profil
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
