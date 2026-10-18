<script setup lang="ts">
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { formatCents } from '@/utils/money'
import { storeColor } from '@/utils/store-colors'
import type { ListComparison } from '@/types/shopping-list'

/** Where the list is cheapest: one store, or split across stores. */
const props = defineProps<{ comparison: ListComparison; storeIds: number[] }>()

const hasPrices = computed(() => props.comparison.stores.length > 0)
const splitIsWorthIt = computed(() => (props.comparison.split?.stores.length ?? 0) > 1 && (props.comparison.split?.saving_cents ?? 1) > 0)
</script>

<template>
  <div class="space-y-4">
    <div v-if="!hasPrices" class="card p-4 text-sm text-slate-500">
      No prices for these items yet. Prices come from your reviewed receipts.
    </div>

    <template v-else>
      <!-- Cheapest single store -->
      <div class="card p-4">
        <template v-if="comparison.cheapest">
          <p class="text-sm text-slate-500">Cheapest overall</p>
          <p class="mt-1 text-2xl font-bold text-slate-900">
            {{ comparison.cheapest.store.name }} <span class="tabular-nums">— {{ formatCents(comparison.cheapest.total_cents) }}</span>
          </p>
          <p v-if="comparison.saving && comparison.saving.cents > 0" class="mt-1 text-sm font-medium text-emerald-700">
            Potential saving: {{ formatCents(comparison.saving.cents) }} compared with {{ comparison.saving.compared_with.name }}
          </p>
        </template>
        <p v-else class="text-sm text-slate-600">
          No single store has a price for everything yet, so there's no fair overall winner. Each store's total below shows what it's missing.
        </p>

        <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
          <li v-for="total in comparison.stores" :key="total.store.id" class="flex items-start gap-3 py-2.5" :class="{ 'opacity-70': total.missing_count }">
            <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: storeColor(total.store.id, storeIds) }" aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-slate-900">{{ total.store.name }}</span>
                <span v-if="total.store.id === comparison.cheapest?.store.id" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Cheapest</span>
              </div>
              <p v-if="total.missing_count" class="text-xs text-amber-800" :title="total.missing.join(', ')">
                <FontAwesomeIcon icon="triangle-exclamation" /> No price for {{ total.missing_count }} item{{ total.missing_count === 1 ? '' : 's' }}: {{ total.missing.join(', ') }}
              </p>
            </div>
            <span class="shrink-0 font-semibold text-slate-900 tabular-nums">
              {{ formatCents(total.total_cents) }}<span v-if="total.missing_count" class="text-slate-400">+</span>
            </span>
          </li>
        </ul>
      </div>

      <!-- Split shop -->
      <div v-if="comparison.split && splitIsWorthIt" class="card p-4">
        <p class="text-sm text-slate-500">Split shop</p>
        <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ formatCents(comparison.split.total_cents) }}</p>
        <p v-if="comparison.split.saving_cents" class="mt-1 text-sm font-medium text-emerald-700">
          {{ formatCents(comparison.split.saving_cents) }} less than shopping only at {{ comparison.cheapest?.store.name }}
        </p>
        <div v-for="stop in comparison.split.stores" :key="stop.store.id" class="mt-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <span class="size-2.5 rounded-full" :style="{ backgroundColor: storeColor(stop.store.id, storeIds) }" aria-hidden="true" />
            {{ stop.store.name }}
            <span class="ml-auto font-normal text-slate-500 tabular-nums">{{ formatCents(stop.total_cents) }}</span>
          </div>
          <p class="mt-0.5 pl-4.5 text-sm text-slate-600">{{ stop.items.join(', ') }}</p>
        </div>
      </div>

      <p v-if="comparison.unpriced.length" class="text-sm text-slate-500">
        No prices yet for: {{ comparison.unpriced.join(', ') }}
      </p>
    </template>
  </div>
</template>
