<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';
import { Eye, EyeOff, Smartphone } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const showPassword = ref(false);
</script>

<template>
    <AuthSplitLayout title="Se Connecter">
        <Head title="Connexion - BengaDok" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-emerald-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label for="email" class="text-[#333333]">Identifiant</Label>
                <Input
                    id="email"
                    type="text"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Ex: centrale_gerant_misgs233"
                    class="login-input rounded-lg border border-[#d1d5db] bg-white text-[#333333] placeholder:italic placeholder:text-gray-500"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-[#333333]">Mot de passe</Label>
                <div class="relative">
                    <Input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="login-input rounded-lg border border-[#d1d5db] bg-white pr-10 text-[#333333] placeholder:text-gray-500"
                    />
                    <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <div class="flex justify-end">
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm text-[#459cd1] hover:text-[#3a8ab8]"
                        :tabindex="5"
                    >
                        Mot de passe oublié ?
                    </TextLink>
                </div>
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                class="login-btn w-full rounded-lg py-6 text-base font-bold text-white"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Connexion
            </Button>

            <!-- Réinitialisation par SMS -->
            <div
                class="flex gap-3 rounded-lg border p-4"
                style="background-color: #e6f7ed; border-color: #7ad894;"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full text-white"
                    style="background-color: #67cb88;"
                >
                    <Smartphone class="size-5" />
                </div>
                <div class="min-w-0 text-sm text-[#333333]">
                    <p class="font-medium">Réinitialisation par SMS</p>
                    <p class="mt-0.5 text-slate-600">
                        En cas d'oubli, recevez un code de vérification par SMS sur votre numéro de téléphone ou celui de la pharmacie.
                    </p>
                </div>
            </div>
        </Form>
    </AuthSplitLayout>
</template>

<style scoped>
/* Maquette : champs blancs à fine bordure grise (sans fond jaune autofill) */
/* Texte toujours sombre pour rester lisible sur fond blanc (mode clair ou sombre) */
.login-input {
    background-color: #fff !important;
    color: #1f2937 !important;
    -webkit-text-fill-color: #1f2937 !important;
    caret-color: #1f2937 !important;
}
.login-input::placeholder {
    color: #6b7280 !important;
    -webkit-text-fill-color: #6b7280 !important;
}
.login-input:-webkit-autofill,
.login-input:-webkit-autofill:hover,
.login-input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 30px white inset !important;
    box-shadow: 0 0 0 30px white inset !important;
    -webkit-text-fill-color: #1f2937 !important;
}
/* Bouton bleu-vert (teal) comme sur la maquette */
.login-btn {
    background-color: #0d9488;
}
.login-btn:hover:not(:disabled) {
    background-color: #0f766e;
}
</style>
