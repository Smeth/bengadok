<script setup lang="ts">
import { ShoppingCart } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    numero?: string;
}>();

const emit = defineEmits<{
    cancel: [];
    confirm: [];
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0, 0, 0, 0.5)"
            @click.self="emit('cancel')"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-center gap-3 border-b border-[#FCD34D] bg-[#FFFBEB] px-6 py-4"
                >
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/10"
                    >
                        <ShoppingCart class="size-5 text-[#F59E0B]" />
                    </div>
                    <div>
                        <p class="text-[15px] font-extrabold text-gray-900">
                            Confirmer le retrait de la commande
                        </p>
                        <p v-if="numero" class="text-[12px] text-gray-500">
                            Commande {{ numero }}
                        </p>
                    </div>
                </div>
                <div class="px-6 py-5">
                    <p class="text-[14px] text-gray-700">
                        Vous confirmez que la commande a bien été retirée par le
                        livreur en pharmacie.
                    </p>
                    <p
                        class="mt-2 rounded-lg border border-[#FCD34D] bg-[#FFFBEB] px-3 py-2 text-[13px] font-semibold text-[#92400E]"
                    >
                        ⚠ Cette action est irréversible. La commande passera en
                        statut « Retirée ».
                    </p>
                </div>
                <div
                    class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4"
                >
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-[13px] font-bold text-gray-700 transition-colors hover:bg-gray-50"
                        @click="emit('cancel')"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-[#F59E0B] px-5 py-2.5 text-[13px] font-bold text-white shadow transition-colors hover:bg-[#D97706]"
                        @click="emit('confirm')"
                    >
                        <ShoppingCart class="size-4" />
                        Confirmer le retrait
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
