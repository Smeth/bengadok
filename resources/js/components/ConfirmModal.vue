<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';

withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        variant?: 'default' | 'destructive';
        loading?: boolean;
    }>(),
    {
        title: 'Confirmer l\'action',
        description: 'Êtes-vous sûr de vouloir continuer ?',
        confirmText: 'Confirmer',
        cancelText: 'Annuler',
        variant: 'destructive',
        loading: false,
    }
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
    cancel: [];
}>();

function onConfirm() {
    emit('confirm');
}

function onCancel() {
    emit('update:open', false);
    emit('cancel');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2" :class="variant === 'destructive' ? 'text-red-600' : ''">
                    <AlertTriangle class="size-5 shrink-0" />
                    {{ title }}
                </DialogTitle>
            </DialogHeader>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
            <DialogFooter>
                <Button variant="outline" :disabled="loading" @click="onCancel">
                    {{ cancelText }}
                </Button>
                <Button
                    :variant="variant"
                    :disabled="loading"
                    @click="onConfirm"
                >
                    <span v-if="loading" class="animate-pulse">Chargement...</span>
                    <span v-else>{{ confirmText }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
