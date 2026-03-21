<script setup lang="ts">
withDefaults(
    defineProps<{
        /** Sidebar pharmacie Figma 359:4 : icône au-dessus du texte */
        layout?: 'horizontal' | 'vertical';
        /** Mode barre réduite : uniquement l’icône */
        iconOnly?: boolean;
    }>(),
    {
        layout: 'horizontal',
        iconOnly: false,
    },
);
</script>

<template>
    <!-- Logo BengaDok design Figma : cercle + pilule bleue + texte Benga / Dok -->
    <div
        class="group-data-[collapsible=icon]:gap-0"
        :class="
            layout === 'vertical'
                ? 'flex flex-col items-center gap-2'
                : 'flex items-center gap-2'
        "
    >
        <div
            class="flex shrink-0 items-center justify-center rounded-full border border-gray-100 bg-white shadow-sm group-data-[collapsible=icon]:size-8"
            :class="layout === 'vertical' ? 'size-12' : 'size-10'"
        >
            <svg
                class="group-data-[collapsible=icon]:size-6"
                :class="layout === 'vertical' ? 'size-10' : 'size-8'"
                viewBox="0 0 32 32"
                fill="none"
            >
                <circle cx="16" cy="16" r="14" fill="#3995D2" />
                <path
                    d="M16 8C12.7 8 10 10.7 10 14C10 17.3 12.7 20 16 20C19.3 20 22 17.3 22 14C22 10.7 19.3 8 16 8ZM16 18C13.8 18 12 16.2 12 14C12 11.8 13.8 10 16 10C18.2 10 20 11.8 20 14C20 16.2 18.2 18 16 18Z"
                    fill="white"
                />
                <path
                    d="M16 12C14.9 12 14 12.9 14 14C14 15.1 14.9 16 16 16C17.1 16 18 15.1 18 14C18 12.9 17.1 12 16 12Z"
                    fill="white"
                />
            </svg>
        </div>
        <div
            v-if="!iconOnly"
            class="sidebar-logo-text flex items-center gap-0.5 group-data-[collapsible=icon]:hidden"
            :class="layout === 'vertical' ? 'justify-center' : ''"
        >
            <span
                :class="layout === 'vertical' ? 'text-[17px]' : 'text-[18px]'"
                class="font-bold text-[#3995D2]"
            >
                Benga
            </span>
            <span
                :class="layout === 'vertical' ? 'text-[17px]' : 'text-[18px]'"
                class="font-bold text-[#5BB66E]"
            >
                Dok
            </span>
        </div>
    </div>
</template>
