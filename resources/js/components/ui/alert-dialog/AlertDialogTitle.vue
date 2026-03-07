<script setup lang="ts">
import type { AlertDialogTitleProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { AlertDialogTitle as RekaAlertDialogTitle, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<AlertDialogTitleProps & { class?: HTMLAttributes["class"] }>()
const delegatedProps = reactiveOmit(props, "class")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
    <RekaAlertDialogTitle
        data-slot="alert-dialog-title"
        v-bind="forwardedProps"
        :class="cn('text-lg font-semibold leading-none', props.class)"
    >
        <slot />
    </RekaAlertDialogTitle>
</template>
