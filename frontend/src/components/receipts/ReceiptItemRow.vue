<script setup lang="ts">
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { useReceiptStore } from '@/stores/receipts'
import { errorMessage } from '@/utils/api-errors'
import { formatCents, toCents } from '@/utils/money'
import { productLabel } from '@/utils/product'
import MatchProductModal from './MatchProductModal.vue'
import type { ReceiptItemResource } from '@/types/receipt'

/** One receipt line and the product it is. Tap the pencil to fix a misread name, quantity or price. */
const props = defineProps<{ item: ReceiptItemResource }>()

const receiptStore = useReceiptStore()
const toast = useToast()
const isEditing = ref(false)
const isChoosing = ref(false)
const isMatched = computed(() => ['auto_matched', 'confirmed', 'new_product'].includes(props.item.match_status))

const confirmSuggestion = async () => {
  try {
    await receiptStore.matchItem(props.item.id, { product_id: props.item.suggested_product!.id })
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

const undoIgnore = async () => {
  try {
    await receiptStore.matchItem(props.item.id, { ignore: false })
  } catch (error) {
    toast.error(errorMessage(error))
  }
}
const isSaving = ref(false)
const form = ref({ name: '', quantity: '', total: '', discount: '' })

const edit = () => {
  form.value = {
    name: props.item.original_name,
    quantity: String(props.item.quantity),
    total: (props.item.total_price_cents / 100).toFixed(2),
    discount: (props.item.discount_cents / 100).toFixed(2),
  }
  isEditing.value = true
}

const save = async () => {
  const quantity = Number(form.value.quantity) || 1
  const total = toCents(form.value.total) ?? 0

  isSaving.value = true

  try {
    await receiptStore.updateItem(props.item.id, {
      original_name: form.value.name,
      quantity,
      total_price_cents: total,
      unit_price_cents: Math.round(total / quantity),
      discount_cents: toCents(form.value.discount) ?? 0,
    })
    isEditing.value = false
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    isSaving.value = false
  }
}

const quantityLabel = (item: ReceiptItemResource) =>
  item.unit === 'kg' ? `${item.quantity} kg @ ${formatCents(item.unit_price_cents)}/kg` : `${item.quantity} × ${formatCents(item.unit_price_cents)}`
</script>

<template>
  <li class="px-4 py-3">
    <form v-if="isEditing" class="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_5rem_6rem_6rem_auto] sm:items-end" @submit.prevent="save">
      <div class="col-span-2 sm:col-span-1">
        <label class="form-label text-xs" :for="`item-name-${item.id}`">Name</label>
        <input :id="`item-name-${item.id}`" v-model="form.name" class="form-input" required />
      </div>
      <div>
        <label class="form-label text-xs" :for="`item-qty-${item.id}`">{{ item.unit === 'kg' ? 'Kg' : 'Qty' }}</label>
        <input :id="`item-qty-${item.id}`" v-model="form.quantity" class="form-input tabular-nums" inputmode="decimal" />
      </div>
      <div>
        <label class="form-label text-xs" :for="`item-total-${item.id}`">Price</label>
        <input :id="`item-total-${item.id}`" v-model="form.total" class="form-input tabular-nums" inputmode="decimal" />
      </div>
      <div>
        <label class="form-label text-xs" :for="`item-discount-${item.id}`">Discount</label>
        <input :id="`item-discount-${item.id}`" v-model="form.discount" class="form-input tabular-nums" inputmode="decimal" />
      </div>
      <div class="col-span-2 flex justify-end gap-2 sm:col-span-1">
        <button type="button" class="btn btn-secondary" @click="isEditing = false">Cancel</button>
        <button type="submit" class="btn btn-primary" :disabled="isSaving">Save</button>
      </div>
    </form>

    <div v-else class="flex items-start gap-3">
      <div class="min-w-0 flex-1">
        <div class="font-medium break-words" :class="item.match_status === 'ignored' ? 'text-slate-400 line-through' : 'text-slate-900'">{{ item.original_name }}</div>
        <div class="mt-0.5 text-xs text-slate-500">
          <span v-if="item.quantity !== 1 || item.unit === 'kg'">{{ quantityLabel(item) }}</span>
          <span v-if="item.discount_cents" class="ml-2 font-semibold text-emerald-700">−{{ formatCents(item.discount_cents) }} off</span>
          <span v-if="item.sku" class="ml-2 font-mono">{{ item.sku }}</span>
        </div>

        <!-- The product this line is -->
        <div class="mt-2 text-sm">
          <button v-if="isMatched && item.product" type="button" class="inline-flex max-w-full items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-left text-emerald-800 hover:bg-emerald-100" title="Change product" @click="isChoosing = true">
            <FontAwesomeIcon icon="check" class="shrink-0" />
            <span class="truncate">{{ productLabel(item.product) }}</span>
            <span v-if="item.match_status === 'auto_matched'" class="shrink-0 text-xs text-emerald-600">auto</span>
          </button>

          <div v-else-if="item.match_status === 'suggested' && item.suggested_product" class="flex flex-wrap items-center gap-2 rounded-md bg-amber-50 px-2 py-1.5 text-amber-900">
            <span>Is this <strong>{{ productLabel(item.suggested_product) }}</strong>?</span>
            <span class="flex gap-1">
              <button type="button" class="rounded bg-amber-600 px-2 py-0.5 text-xs font-semibold text-white hover:bg-amber-700" @click="confirmSuggestion">Yes</button>
              <button type="button" class="rounded bg-white px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-300 hover:bg-amber-100" @click="isChoosing = true">No, choose…</button>
            </span>
          </div>

          <div v-else-if="item.match_status === 'ignored'" class="flex items-center gap-2 text-xs text-slate-500">
            Not a product
            <button type="button" class="link" @click="undoIgnore">Undo</button>
          </div>

          <button v-else type="button" class="inline-flex items-center gap-1.5 rounded-md bg-brand-50 px-2 py-1 font-medium text-brand-700 hover:bg-brand-100" @click="isChoosing = true">
            <FontAwesomeIcon icon="magnifying-glass" /> Choose product
          </button>
        </div>
      </div>
      <div class="shrink-0 text-right tabular-nums">
        <div class="font-semibold text-slate-900">{{ formatCents(item.total_price_cents - item.discount_cents) }}</div>
        <div v-if="item.discount_cents" class="text-xs text-slate-400 line-through">{{ formatCents(item.total_price_cents) }}</div>
      </div>
      <button type="button" class="-mr-2 shrink-0 rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" :aria-label="`Edit ${item.original_name}`" @click="edit">
        <FontAwesomeIcon icon="pen" />
      </button>
    </div>
    <MatchProductModal v-if="isChoosing" :item="item" @close="isChoosing = false" />
  </li>
</template>
