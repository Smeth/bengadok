<script setup lang="ts">
import type { AlertDialogDescriptionProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { AlertDialogDescription as RekaAlertDialogDescription, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<AlertDialogDescriptionProps & { class?: HTMLAttributes["class"] }>()
const delegatedProps = reactiveOmit(props, "class")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
    <RekaAlertDialogDescription
        data-slot="alert-dialog-description"
        v-bind="forwardedProps"
        :class="cn('text-muted-foreground text-sm', props.class)"
    >
        <slot />
    </RekaAlertDialogDescription>
</template>
