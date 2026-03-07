<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Logo BengaDok : cercle + croix teal + combiné bleu foncé -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient id="logo-circle-bg" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#3d9bad" />
                <stop offset="100%" stop-color="#34B0C7" />
            </linearGradient>
        </defs>
        <circle cx="20" cy="20" r="19" fill="url(#logo-circle-bg)" />
        <g transform="rotate(-22 20 20)" fill="none" stroke="#2C4685" stroke-width="2.5" stroke-linecap="round">
            <path d="M15 15 Q15 25 20 28" />
            <path d="M25 15 Q25 25 20 28" />
        </g>
        <rect x="17" y="10" width="6" height="20" rx="1" fill="#34B0C7" />
        <rect x="10" y="17" width="20" height="6" rx="1" fill="#34B0C7" />
    </svg>
</template>
