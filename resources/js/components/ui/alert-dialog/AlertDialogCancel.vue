<script setup lang="ts">
import type { AlertDialogCancelProps } from "reka-ui"
import { AlertDialogCancel as RekaAlertDialogCancel } from "reka-ui"
import { Button } from "@/components/ui/button"

const props = defineProps<AlertDialogCancelProps>()
</script>

<template>
    <RekaAlertDialogCancel as-child>
        <Button variant="outline">
            <slot />
        </Button>
    </RekaAlertDialogCancel>
</template>
