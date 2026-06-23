<script setup lang="ts">
import { Copy } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        open: boolean;
        username: string;
        password: string;
        title?: string;
    }>(),
    {
        title: 'Identifiants créés',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

function copyCredentials() {
    navigator.clipboard.writeText(
        `Identifiant : ${props.username}\nMot de passe : ${props.password}`,
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Transmettez ces identifiants au collaborateur. Ils correspondent
                exactement au compte enregistré.
            </p>
            <div
                class="space-y-2 rounded-lg border border-sky-200 bg-sky-50 p-4 font-mono text-sm dark:border-sky-800 dark:bg-sky-950/40"
            >
                <p><span class="text-muted-foreground">Identifiant :</span> {{ username }}</p>
                <p><span class="text-muted-foreground">Mot de passe :</span> {{ password }}</p>
            </div>
            <DialogFooter class="gap-2 sm:justify-between">
                <Button
                    type="button"
                    variant="outline"
                    class="border-sky-300 text-sky-700 hover:bg-sky-100"
                    @click="copyCredentials"
                >
                    <Copy class="mr-2 size-4" />
                    Copier les identifiants
                </Button>
                <Button type="button" @click="emit('update:open', false)">
                    Fermer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
