<script setup lang="ts">
import { Copy } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    showGlobalErrorToast,
    showGlobalSuccessToast,
} from '@/lib/globalToast';
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

const copying = ref(false);

async function copyCredentials() {
    if (copying.value) {
        return;
    }
    const text = `Identifiant : ${props.username}\nMot de passe : ${props.password}`;
    copying.value = true;
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
        }
        showGlobalSuccessToast('Identifiants copiés dans le presse-papiers.');
    } catch {
        showGlobalErrorToast(
            'Impossible de copier automatiquement. Sélectionnez le texte ci-dessus.',
        );
    } finally {
        copying.value = false;
    }
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
                    :disabled="copying"
                    @click="copyCredentials"
                >
                    <Copy class="mr-2 size-4" />
                    {{ copying ? 'Copie…' : 'Copier les identifiants' }}
                </Button>
                <Button type="button" @click="emit('update:open', false)">
                    Fermer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
