<template>
  <div v-if="product">
    <PageHeader :title="productLabel(product)">
      <template #description>
        <RouterLink :to="{ name: 'products' }" class="link-muted"><FontAwesomeIcon icon="chevron-left" class="mr-1" />Products</RouterLink>
        <span v-if="product.category"> · {{ product.category.name }}</span>
      </template>
    </PageHeader>

    <p v-if="summary && !summary.stores.length" class="card px-4 py-10 text-center text-sm text-slate-500">
      No prices yet. They appear here once a receipt with this product has been reviewed.
    </p>

    <div v-else-if="summary" class="space-y-6">
      <!-- Headline: where it's cheapest -->
      <div class="card p-5">
        <p class="text-sm text-slate-500">Cheapest</p>
        <p class="mt-1 text-3xl font-bold text-slate-900">
          {{ summary.cheapest!.store.name }} <span class="tabular-nums">— {{ formatPrice(summary.cheapest!.price_cents, summary.unit) }}</span>
        </p>
        <p v-if="summary.saving && summary.saving.cents > 0" class="mt-2 text-sm font-medium text-emerald-700">
          You could save {{ formatCents(summary.saving.cents) }} compared with {{ summary.saving.compared_with.name }}
        </p>
        <p v-else-if="summary.stores.length === 1" class="mt-2 text-sm text-slate-500">Only bought at one store so far.</p>
      </div>

      <!-- Latest price at each store, cheapest first -->
      <div class="card overflow-hidden">
        <h2 class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Latest price at each store</h2>
        <ul class="divide-y divide-slate-100">
          <li v-for="price in summary.stores" :key="price.store.id" class="flex items-center gap-3 px-4 py-3" :class="{ 'opacity-60': price.is_stale }">
            <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: storeColor(price.store.id, storeIds) }" aria-hidden="true" />
            <span class="w-20 shrink-0 text-lg font-semibold sm:w-24 text-slate-900 tabular-nums">{{ formatPrice(price.price_cents, summary.unit) }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-slate-900">{{ price.store.name }}</span>
                <span v-if="price.store.id === summary.cheapest?.store.id" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-emerald-800">← Cheapest</span>
                <span v-if="price.is_promotion" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                  Special<template v-if="price.regular_price_cents && price.regular_price_cents > price.price_cents">, usually {{ formatPrice(price.regular_price_cents, summary.unit) }}</template>
                </span>
              </div>
              <div class="text-xs text-slate-500">
                {{ price.is_stale ? 'Last seen' : 'Seen' }} {{ $date(price.observed_on, { format: 'D MMM YYYY' }) }}
                <template v-if="price.is_stale"> · may have changed</template>
              </div>
              <div v-if="change(price)" class="mt-0.5 text-xs font-semibold sm:hidden" :class="change(price)!.class">
                <FontAwesomeIcon :icon="change(price)!.icon" /> {{ change(price)!.label }}
              </div>
            </div>
            <span v-if="change(price)" class="hidden shrink-0 text-xs font-semibold tabular-nums sm:inline" :class="change(price)!.class">
              <FontAwesomeIcon :icon="change(price)!.icon" /> {{ change(price)!.label }}
            </span>
          </li>
        </ul>
      </div>

      <!-- History stats -->
      <div v-if="summary.stats" class="grid gap-4 sm:grid-cols-3">
        <div class="card p-4">
          <p class="text-sm text-slate-500">Lowest recorded</p>
          <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ formatPrice(summary.stats.lowest.price_cents, summary.unit) }}</p>
          <p class="text-xs text-slate-500">{{ summary.stats.lowest.store.name }}, {{ $date(summary.stats.lowest.observed_on, { format: 'D MMM YYYY' }) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-sm text-slate-500">Highest recorded</p>
          <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ formatPrice(summary.stats.highest.price_cents, summary.unit) }}</p>
          <p class="text-xs text-slate-500">{{ summary.stats.highest.store.name }}, {{ $date(summary.stats.highest.observed_on, { format: 'D MMM YYYY' }) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-sm text-slate-500">Average price</p>
          <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ formatPrice(summary.stats.average_cents, summary.unit) }}</p>
          <p class="text-xs text-slate-500">Across stores now · {{ summary.stats.count }} prices recorded</p>
        </div>
      </div>

      <!-- Price over time -->
      <div class="card p-4">
        <h2 class="mb-3 font-semibold text-slate-900">Price history</h2>
        <PriceHistoryChart :series="history" :unit="summary.unit" :store-ids="storeIds" />

        <details class="mt-4 text-sm">
          <summary class="cursor-pointer text-slate-600">Show all prices as a table</summary>
          <table class="mt-2 w-full text-left">
            <thead class="text-xs text-slate-500">
              <tr><th class="py-1 font-medium">Date</th><th class="py-1 font-medium">Store</th><th class="py-1 text-right font-medium">Price</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in tableRows" :key="`${row.store}-${row.date}-${row.index}`">
                <td class="py-1.5">{{ $date(row.date, { format: 'D MMM YYYY' }) }}</td>
                <td class="py-1.5">{{ row.store }}</td>
                <td class="py-1.5 text-right tabular-nums">{{ formatPrice(row.price_cents, summary.unit) }}<span v-if="row.is_promotion" class="text-amber-700"> special</span></td>
              </tr>
            </tbody>
          </table>
        </details>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PageHeader from '@/components/app/PageHeader.vue'
import PriceHistoryChart from '@/components/pricing/PriceHistoryChart.vue'
import { useCatalogStore } from '@/stores/catalog'
import { useProductStore } from '@/stores/products'
import { formatCents, formatPrice } from '@/utils/money'
import { productLabel } from '@/utils/product'
import { storeColor } from '@/utils/store-colors'
import type { ProductResource } from '@/types/catalog'
import type { PriceHistorySeries, PriceSummary, StorePrice } from '@/types/pricing'

const props = defineProps<{ id: string }>()

const productStore = useProductStore()
const catalog = useCatalogStore()
const product = ref<ProductResource | null>(null)
const summary = ref<PriceSummary | null>(null)
const history = ref<PriceHistorySeries[]>([])

const storeIds = computed(() => catalog.stores.map((store) => store.id))

const tableRows = computed(() =>
  history.value
    .flatMap((series) => series.points.map((point, index) => ({ ...point, index, store: series.store.name })))
    .sort((a, b) => b.date.localeCompare(a.date))
)

/** Change since the store's previous price: down is good news, so it's green. */
const change = (price: StorePrice) => {
  if (price.previous_price_cents === null || price.previous_price_cents === price.price_cents) return null

  const difference = price.price_cents - price.previous_price_cents

  return difference < 0
    ? { icon: 'arrow-down', class: 'text-emerald-700', label: `${formatCents(-difference)} cheaper` }
    : { icon: 'arrow-up', class: 'text-red-700', label: `${formatCents(difference)} dearer` }
}

onMounted(async () => {
  const [loaded, prices, series] = await Promise.all([
    productStore.fetchProduct(props.id),
    productStore.fetchPrices(props.id),
    productStore.fetchPriceHistory(props.id),
    catalog.stores.length ? null : catalog.fetchStores(),
  ])

  product.value = loaded
  summary.value = prices
  history.value = series
})
</script>
