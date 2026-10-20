<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { formatCents, formatPrice } from '@/utils/money'
import type { PriceChange } from '@/types/dashboard'

/** Price changes in one direction. Cheaper is good news, so it's green; dearer is red. */
defineProps<{ title: string; changes: PriceChange[]; direction: 'down' | 'up'; empty: string }>()
</script>

<template>
  <div class="card overflow-hidden">
    <h2 class="flex items-center gap-2 border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">
      <FontAwesomeIcon :icon="direction === 'down' ? 'arrow-down' : 'arrow-up'" :class="direction === 'down' ? 'text-emerald-600' : 'text-red-600'" />
      {{ title }}
    </h2>
    <p v-if="!changes.length" class="px-4 py-6 text-sm text-slate-500">{{ empty }}</p>
    <ul class="divide-y divide-slate-100">
      <li v-for="change in changes" :key="`${change.product.id}-${change.store.id}`">
        <RouterLink :to="{ name: 'products.prices', params: { id: change.product.id } }" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50">
          <div class="min-w-0 flex-1">
            <div class="truncate font-medium text-slate-900">{{ change.product.name }} <span v-if="change.product.size" class="text-slate-500">{{ change.product.size }}</span></div>
            <div class="text-xs text-slate-500">{{ change.store.name }} · {{ $date(change.observed_on, { format: 'D MMM' }) }}</div>
          </div>
          <div class="shrink-0 text-right tabular-nums">
            <div class="text-sm text-slate-900">
              <span class="text-slate-400">{{ formatPrice(change.from_cents, change.unit) }} →</span> {{ formatPrice(change.to_cents, change.unit) }}
            </div>
            <div class="text-xs font-semibold" :class="direction === 'down' ? 'text-emerald-700' : 'text-red-700'">
              {{ direction === 'down' ? '−' : '+' }}{{ formatCents(Math.abs(change.change_cents)) }}
            </div>
          </div>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
