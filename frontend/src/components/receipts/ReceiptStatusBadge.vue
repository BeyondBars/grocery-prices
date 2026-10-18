<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import type { ReceiptStatus } from '@/types/receipt'

defineProps<{ status: ReceiptStatus }>()

const badges: Record<ReceiptStatus, { label: string; class: string; busy?: boolean }> = {
  uploaded: { label: 'Processing', class: 'bg-sky-100 text-sky-700', busy: true },
  processing: { label: 'Processing', class: 'bg-sky-100 text-sky-700', busy: true },
  needs_review: { label: 'Needs review', class: 'bg-amber-100 text-amber-800' },
  completed: { label: 'Completed', class: 'bg-emerald-100 text-emerald-700' },
  failed: { label: 'Failed', class: 'bg-red-100 text-red-700' },
}
</script>

<template>
  <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap" :class="badges[status].class">
    <FontAwesomeIcon v-if="badges[status].busy" icon="spinner" spin />
    {{ badges[status].label }}
  </span>
</template>
