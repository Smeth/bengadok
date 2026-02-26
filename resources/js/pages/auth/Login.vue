<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
</script>

<template>
    <AuthSplitLayout
        title="Se connecter"
        description="Entrez votre email et mot de passe pour accéder à votre compte"
    >
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
                <Label for="email">Email / Téléphone</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="exemple@email.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Mot de passe</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm text-sky-600 hover:text-sky-700 dark:text-sky-400"
                        :tabindex="5"
                    >
                        Mot de passe oublié ?
                    </TextLink>
                </div>
                <Input
                    id="password"
                    type="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center gap-3">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <Label for="remember" class="cursor-pointer text-sm font-normal">
                    Se souvenir de moi
                </Label>
            </div>

            <Button
                type="submit"
                class="w-full bg-sky-600 py-6 text-base font-medium hover:bg-sky-700"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Se connecter
            </Button>
        </Form>
    </AuthSplitLayout>
</template>
