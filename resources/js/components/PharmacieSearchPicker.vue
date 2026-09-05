<script setup lang="ts">
import { Check, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export type PharmacieOption = {
    id: number;
    designation: string;
    adresse?: string | null;
    telephone?: string | null;
    zone?: { id?: number; designation?: string } | null;
    zone_id?: number | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: number | string | '';
        pharmacies: PharmacieOption[];
        error?: string;
    }>(),
    {
        error: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | string | ''];
}>();

const search = ref('');
const zoneFilter = ref<number | ''>('');

const zones = computed(() => {
    const map = new Map<number, string>();
    for (const p of props.pharmacies) {
        const zid = p.zone_id ?? p.zone?.id;
        const zname = p.zone?.designation;
        if (zid != null && zname) {
            map.set(zid, zname);
        }
    }
    return [...map.entries()]
        .map(([id, designation]) => ({ id, designation }))
        .sort((a, b) => a.designation.localeCompare(b.designation, 'fr'));
});

const filteredPharmacies = computed(() => {
    let list = [...props.pharmacies];

    if (zoneFilter.value !== '') {
        const zid = Number(zoneFilter.value);
        list = list.filter(
            (p) => (p.zone_id ?? p.zone?.id) === zid,
        );
    }

    const q = search.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            (p) =>
                p.designation.toLowerCase().includes(q) ||
                (p.adresse ?? '').toLowerCase().includes(q) ||
                (p.telephone ?? '').toLowerCase().includes(q) ||
                (p.zone?.designation ?? '').toLowerCase().includes(q),
        );
    }

    return list.sort((a, b) =>
        a.designation.localeCompare(b.designation, 'fr'),
    );
});

const selectedPharmacie = computed(() =>
    props.pharmacies.find((p) => String(p.id) === String(props.modelValue)),
);

function selectPharmacie(id: number) {
    emit('update:modelValue', id);
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="selectedPharmacie"
            class="flex items-start gap-3 rounded-[10px] border border-[rgba(91,182,110,0.45)] bg-[rgba(91,182,110,0.12)] px-3 py-2.5"
        >
            <Check
                class="mt-0.5 size-4 shrink-0 text-[#2d8a47]"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-[#374151]">
                    {{ selectedPharmacie.designation }}
                </p>
                <p class="truncate text-xs text-[#64748b]">
                    <template v-if="selectedPharmacie.zone?.designation">
                        {{ selectedPharmacie.zone.designation }}
                        <span v-if="selectedPharmacie.adresse"> • </span>
                    </template>
                    {{ selectedPharmacie.adresse }}
                    <template v-if="selectedPharmacie.telephone">
                        • {{ selectedPharmacie.telephone }}
                    </template>
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <div
                class="flex min-w-0 flex-1 items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white pl-3 focus-within:border-[#459cd1] focus-within:ring-1 focus-within:ring-[#459cd1]"
                :class="{ 'border-[#dc3545]': error }"
            >
                <Search
                    class="mr-2 size-4 shrink-0 text-[rgba(102,102,102,0.6)]"
                    aria-hidden="true"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher une pharmacie…"
                    autocomplete="off"
                    class="min-w-0 flex-1 bg-transparent py-2.5 text-sm outline-none placeholder:text-[rgba(102,102,102,0.6)]"
                />
            </div>
            <div
                v-if="zones.length > 1"
                class="relative shrink-0 sm:w-48"
            >
                <select
                    v-model="zoneFilter"
                    class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-8 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                >
                    <option value="">Toutes les zones</option>
                    <option
                        v-for="z in zones"
                        :key="z.id"
                        :value="z.id"
                    >
                        {{ z.designation }}
                    </option>
                </select>
            </div>
        </div>

        <div
            class="grid max-h-52 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2"
            role="listbox"
            aria-label="Liste des pharmacies"
        >
            <p
                v-if="!filteredPharmacies.length"
                class="col-span-full py-8 text-center text-sm text-[rgba(92,89,89,0.5)]"
            >
                Aucune pharmacie ne correspond à votre recherche.
            </p>
            <button
                v-for="p in filteredPharmacies"
                :key="p.id"
                type="button"
                role="option"
                :aria-selected="String(modelValue) === String(p.id)"
                class="flex min-h-[88px] items-center justify-between gap-2 rounded-[10px] border p-3 text-left transition-all"
                :class="
                    String(modelValue) === String(p.id)
                        ? 'border-[rgba(92,89,89,0.25)] bg-[rgba(91,182,110,0.18)] ring-1 ring-[rgba(91,182,110,0.35)]'
                        : 'border-[rgba(92,89,89,0.25)] hover:bg-[rgba(91,182,110,0.08)]'
                "
                @click="selectPharmacie(p.id)"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] font-bold text-[#374151]">
                        {{ p.designation }}
                    </p>
                    <p class="truncate text-[11px] text-[#94a3b8]">
                        <template v-if="p.zone?.designation">
                            {{ p.zone.designation }}
                            <span v-if="p.adresse"> • </span>
                        </template>
                        {{ p.adresse }}
                        <template v-if="p.telephone">
                            • {{ p.telephone }}
                        </template>
                    </p>
                </div>
                <Check
                    v-if="String(modelValue) === String(p.id)"
                    class="size-4 shrink-0 text-[#2d8a47]"
                    aria-hidden="true"
                />
            </button>
        </div>
    </div>
</template>
