<script setup lang="ts">
import type { AlertDialogActionProps } from "reka-ui"
import { AlertDialogAction as RekaAlertDialogAction } from "reka-ui"
import { Button } from "@/components/ui/button"

defineOptions({
    inheritAttrs: false,
})

defineProps<AlertDialogActionProps>()
</script>

<template>
    <RekaAlertDialogAction as-child>
        <Button v-bind="$attrs">
            <slot />
        </Button>
    </RekaAlertDialogAction>
</template>
