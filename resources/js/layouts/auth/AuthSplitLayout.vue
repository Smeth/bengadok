<script setup lang="ts">
import LoginLogo from '@/components/LoginLogo.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh">
        <!-- Partie gauche : dégradé bleu → vert, message marketing, logo (masqué sur mobile) -->
        <div
            class="hidden flex-1 flex-col justify-between px-8 pb-10 pt-12 lg:flex lg:pl-14 lg:pr-12"
            style="background: linear-gradient(to right, #459cd1 0%, #67cb88 100%);"
        >
            <div>
                <p
                    class="max-w-md text-3xl font-bold leading-tight text-[#333333] md:text-4xl lg:text-[2.5rem]"
                    style="text-shadow: 0 1px 2px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);"
                >
                    Votre pharmacie, connectée à plus de patients chaque jour.
                </p>
            </div>

            <div class="flex justify-center">
                <LoginLogo />
            </div>
        </div>

        <!-- Partie droite : zone pour la carte formulaire -->
        <div
            class="flex w-full flex-col items-center justify-center px-4 py-8 md:w-[42%] md:min-w-[380px] md:px-8 md:py-12 lg:min-w-[420px]"
            style="background: linear-gradient(to right, #459cd1 0%, #67cb88 100%);"
        >
            <div
                class="w-full max-w-md rounded-2xl border-0 bg-white p-6 shadow-xl md:-ml-6 md:rounded-[22px] md:p-8"
                style="box-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 8px 32px rgba(0,0,0,0.06);"
            >
                <h1 class="mb-6 text-2xl font-bold text-[#333333] md:text-3xl">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mb-6 text-sm text-slate-500"
                >
                    {{ description }}
                </p>
                <slot />
            </div>
        </div>
    </div>
</template>
