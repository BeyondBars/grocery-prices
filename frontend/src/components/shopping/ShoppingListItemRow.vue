<script setup lang="ts">
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import AppModal from '@/components/app/AppModal.vue'
import AddItemInput from './AddItemInput.vue'
import { useShoppingListStore } from '@/stores/shopping-lists'
import { errorMessage } from '@/utils/api-errors'
import { formatCents } from '@/utils/money'
import type { ProductResource } from '@/types/catalog'
import type { ListComparison, ShoppingListItemResource } from '@/types/shopping-list'

/** Tick off, change the quantity, link a note to a product, or remove. Shows where it's cheapest. */
const props = defineProps<{ item: ShoppingListItemResource; comparison: ListComparison | null }>()

const listStore = useShoppingListStore()
const toast = useToast()
const isLinking = ref(false)

const cheapest = computed(() => {
  const row = props.comparison?.items.find((comparisonItem) => comparisonItem.id === props.item.id)
  return row?.prices.find((price) => price.store_id === row.cheapest_store_id) ?? null
})

const save = async (payload: Parameters<typeof listStore.updateItem>[1]) => {
  try {
    await listStore.updateItem(props.item.id, payload)
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

const setQuantity = (quantity: number) => quantity > 0 && save({ quantity })

const link = async (product: ProductResource) => {
  isLinking.value = false
  await save({ product_id: product.id })
}
</script>

<template>
  <li class="flex items-center gap-3 px-4 py-2.5">
    <input type="checkbox" class="form-check size-5 shrink-0" :checked="item.is_checked" :aria-label="`Got ${item.label}`" @change="save({ is_checked: !item.is_checked })" />

    <div class="min-w-0 flex-1">
      <RouterLink v-if="item.product" :to="{ name: 'products.prices', params: { id: item.product.id } }" class="font-medium hover:text-brand-700" :class="item.is_checked ? 'text-slate-400 line-through' : 'text-slate-900'">
        {{ item.label }}
      </RouterLink>
      <span v-else class="font-medium" :class="item.is_checked ? 'text-slate-400 line-through' : 'text-slate-900'">{{ item.label }}</span>

      <div class="text-xs text-slate-500">
        <template v-if="cheapest">{{ formatCents(cheapest.line_total_cents) }} at {{ cheapest.store.name }}</template>
        <button v-else-if="!item.product" type="button" class="link text-left" @click="isLinking = true">Choose the product to compare prices</button>
        <template v-else>No price yet</template>
      </div>
    </div>

    <div class="flex shrink-0 items-center rounded-md border border-slate-200">
      <button type="button" class="px-2 py-1 text-slate-500 hover:text-slate-900 disabled:opacity-30" :disabled="item.quantity <= 1" :aria-label="`One less ${item.label}`" @click="setQuantity(item.quantity - 1)">−</button>
      <span class="min-w-6 text-center text-sm tabular-nums">{{ item.quantity }}</span>
      <button type="button" class="px-2 py-1 text-slate-500 hover:text-slate-900" :aria-label="`One more ${item.label}`" @click="setQuantity(item.quantity + 1)">+</button>
    </div>

    <button type="button" class="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Remove ${item.label}`" @click="listStore.removeItem(item.id)">
      <FontAwesomeIcon icon="xmark" />
    </button>

    <AppModal v-if="isLinking" title="Which product?" :description="`Link “${item.label}” to a product to compare its price.`" @close="isLinking = false">
      <AddItemInput placeholder="Search products…" :allow-note="false" @product="link" />
    </AppModal>
  </li>
</template>
