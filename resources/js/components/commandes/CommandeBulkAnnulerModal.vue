<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { moduleSelectClass } from '@/lib/bengadokUi';

type MotifOption = {
    key: string;
    label: string;
    desc: string;
};

defineProps<{
    open: boolean;
    selectedCount: number;
    motifOptions: MotifOption[];
    motif: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:motif': [value: string];
    confirm: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-red-600">
                    <AlertTriangle class="size-5" />
                    Annuler {{ selectedCount }} commande(s)
                </DialogTitle>
            </DialogHeader>
            <p class="mb-4 text-sm text-muted-foreground">
                Choisissez le motif d'annulation pour les commandes
                sélectionnées.
            </p>
            <div class="space-y-2">
                <Label>Motif d'annulation *</Label>
                <select
                    :value="motif"
                    :class="moduleSelectClass"
                    @change="
                        emit(
                            'update:motif',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">Sélectionner un motif</option>
                    <option
                        v-for="opt in motifOptions"
                        :key="opt.key"
                        :value="opt.key"
                    >
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">
                    Retour
                </Button>
                <Button
                    variant="destructive"
                    :disabled="!motif"
                    @click="emit('confirm')"
                >
                    Annuler les commandes
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
