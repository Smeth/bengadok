<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { Check } from 'lucide-vue-next'
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { cn } from '@/lib/utils'

type CheckedState = boolean | 'indeterminate'

/**
 * reka-ui attend modelValue ; shadcn/vue utilise souvent checked + indeterminate.
 * Ce pont évite les cases « fantômes » hors contrôle dans toute l'app.
 */
const props = defineProps<
    Omit<CheckboxRootProps, 'modelValue'> & {
        class?: HTMLAttributes['class']
        modelValue?: CheckedState | null
        checked?: CheckedState
        indeterminate?: boolean
    }
>()

const emits = defineEmits<
    CheckboxRootEmits & {
        'update:checked': [value: CheckedState]
    }
>()

const rootProps = reactiveOmit(props, 'class', 'checked', 'indeterminate', 'modelValue')

const bridgedValue = computed<CheckedState>(() => {
    if (props.modelValue !== undefined && props.modelValue !== null) {
        return props.modelValue
    }
    if (props.indeterminate && props.checked !== true) {
        return 'indeterminate'
    }
    return props.checked ?? false
})

function onUpdate(value: CheckedState): void {
    emits('update:modelValue', value)
    emits('update:checked', value)
}
</script>

<template>
  <CheckboxRoot
    v-slot="slotProps"
    data-slot="checkbox"
    v-bind="rootProps"
    :model-value="bridgedValue"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
         props.class)"
    @update:model-value="onUpdate"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="grid place-content-center text-current transition-none"
    >
      <slot v-bind="slotProps">
        <Check class="size-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
