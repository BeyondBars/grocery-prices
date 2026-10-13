<template>
  <div>
    <p v-if="label" class="mb-1 text-xs font-medium text-slate-500">{{ label }}</p>
    <div class="h-2 overflow-hidden rounded-full bg-slate-200">
      <div
        :class="['h-full transition-all', strengthClass]"
        role="progressbar"
        :style="`width: ${strength}%`"
        :aria-valuenow="strength"
        aria-valuemin="0"
        aria-valuemax="100"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import zxcvbn from 'zxcvbn'

const props = defineProps<{
  label: string
  password?: string
}>()

const strength = computed(() => {
  const { score } = zxcvbn(props.password ?? '')
  switch (score) {
    case 0:
      return 0
    case 1:
      return 25
    case 2:
      return 50
    case 3:
      return 75
    case 4:
      return 100
    default:
      return 0
  }
})

const strengthClass = computed(() => {
  switch (strength.value) {
    case 0:
    case 25:
      return 'bg-red-500'
    case 50:
      return 'bg-amber-500'
    case 75:
    case 100:
      return 'bg-emerald-500'
    default:
      return 'bg-red-500'
  }
})
</script>
