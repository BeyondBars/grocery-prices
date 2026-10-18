<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import AppModal from '@/components/app/AppModal.vue'
import { useCatalogStore } from '@/stores/catalog'
import { useProductStore } from '@/stores/products'
import { useReceiptStore } from '@/stores/receipts'
import { errorMessage, validationErrors } from '@/utils/api-errors'
import { productLabel } from '@/utils/product'
import type { ProductResource } from '@/types/catalog'
import type { MatchCandidate, MatchPayload, ReceiptItemResource } from '@/types/receipt'

/**
 * Choose the product a receipt line is: one of the likely matches, any product found by
 * searching, a new product (pre-filled from the line), or "not a product".
 */
const props = defineProps<{ item: ReceiptItemResource }>()
const emit = defineEmits<{ close: [] }>()

const receiptStore = useReceiptStore()
const productStore = useProductStore()
const catalog = useCatalogStore()
const toast = useToast()

const candidates = ref<MatchCandidate[]>([])
const search = ref('')
const searchResults = ref<ProductResource[]>([])
const isCreating = ref(false)
const isSaving = ref(false)
const isLoading = ref(true)
const errors = ref<Record<string, string>>({})
const draft = ref({ name: '', brand: '', variant: '', size: '', pack_count: 1, category_id: null as number | null, barcode: '' })

const choices = computed<{ product: ProductResource; confidence?: number; reason?: string }[]>(() =>
  search.value.trim() ? searchResults.value.map((product) => ({ product })) : candidates.value
)

watch(
  search,
  useDebounceFn(async (value: string) => {
    searchResults.value = value.trim() ? await productStore.searchProducts(value) : []
  }, 250)
)

const decide = async (payload: MatchPayload) => {
  isSaving.value = true
  errors.value = {}

  try {
    await receiptStore.matchItem(props.item.id, payload)
    emit('close')
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    isSaving.value = false
  }
}

const createProduct = async () =>
  decide({
    new_product: {
      name: draft.value.name,
      brand_id: await catalog.brandIdFor(draft.value.brand),
      category_id: draft.value.category_id,
      variant: draft.value.variant || null,
      size: draft.value.size || null,
      pack_count: draft.value.pack_count || 1,
      barcode: draft.value.barcode || null,
    },
  })

const confidenceClass = (confidence: number) =>
  confidence >= 95 ? 'bg-emerald-100 text-emerald-700' : confidence >= 70 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'

onMounted(async () => {
  try {
    const { data, new_product } = await receiptStore.fetchCandidates(props.item.id)
    candidates.value = data
    draft.value = { ...draft.value, ...new_product, brand: new_product.brand ?? '', variant: new_product.variant ?? '', size: new_product.size ?? '', barcode: new_product.barcode ?? '' }
    isCreating.value = data.length === 0
  } finally {
    isLoading.value = false
  }

  if (!catalog.categories.length) catalog.fetchCategories()
})
</script>

<template>
  <AppModal title="Which product is this?" :description="item.original_name" size="xl" @close="emit('close')">
    <div v-if="!isCreating" class="space-y-3">
      <div class="relative">
        <FontAwesomeIcon icon="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" />
        <input v-model="search" type="search" class="form-input py-2 pl-9" placeholder="Search all products…" aria-label="Search products" />
      </div>

      <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ search.trim() ? 'Search results' : 'Likely matches' }}</p>
      <p v-if="isLoading" class="py-4 text-center text-sm text-slate-400"><FontAwesomeIcon icon="spinner" spin /></p>
      <p v-else-if="!choices.length" class="py-4 text-center text-sm text-slate-500">
        {{ search.trim() ? 'No products match your search.' : 'No similar products yet.' }}
      </p>

      <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
        <li v-for="choice in choices" :key="choice.product.id">
          <button type="button" class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-slate-50 disabled:opacity-50" :disabled="isSaving" @click="decide({ product_id: choice.product.id })">
            <div class="min-w-0 flex-1">
              <div class="font-medium text-slate-900">{{ productLabel(choice.product) }}</div>
              <div class="text-xs text-slate-500">
                <span v-if="choice.product.variant">{{ choice.product.variant }}</span>
                <span v-if="choice.reason"> · {{ choice.reason }}</span>
              </div>
            </div>
            <span v-if="choice.confidence !== undefined" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums" :class="confidenceClass(choice.confidence)">
              {{ choice.confidence }}%
            </span>
            <FontAwesomeIcon icon="chevron-right" class="shrink-0 text-slate-300" />
          </button>
        </li>
      </ul>
    </div>

    <form v-else id="new-product-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="createProduct">
      <p class="text-sm text-slate-500 sm:col-span-2">Filled in from the receipt. Check the size and variant: they keep similar products apart.</p>
      <div class="sm:col-span-2">
        <label class="form-label" for="new-name">Name</label>
        <input id="new-name" v-model="draft.name" class="form-input" :class="{ 'is-invalid': errors['new_product.name'] }" required />
        <span v-if="errors['new_product.name']" class="form-error">{{ errors['new_product.name'] }}</span>
      </div>
      <div>
        <label class="form-label" for="new-brand">Brand</label>
        <input id="new-brand" v-model="draft.brand" class="form-input" placeholder="Optional" />
      </div>
      <div>
        <label class="form-label" for="new-variant">Variant</label>
        <input id="new-variant" v-model="draft.variant" class="form-input" placeholder="e.g. Full Cream, Zero Sugar" />
      </div>
      <div>
        <label class="form-label" for="new-size">Size</label>
        <input id="new-size" v-model="draft.size" class="form-input" :class="{ 'is-invalid': errors['new_product.size'] }" placeholder="2L, 700g, 1kg" />
        <span v-if="errors['new_product.size']" class="form-error">{{ errors['new_product.size'] }}</span>
      </div>
      <div>
        <label class="form-label" for="new-pack">Pack of</label>
        <input id="new-pack" v-model.number="draft.pack_count" type="number" min="1" class="form-input" />
      </div>
      <div>
        <label class="form-label" for="new-category">Category</label>
        <select id="new-category" v-model="draft.category_id" class="form-input">
          <option :value="null">None</option>
          <option v-for="option in catalog.categoryOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
        </select>
      </div>
      <div>
        <label class="form-label" for="new-barcode">Barcode</label>
        <input id="new-barcode" v-model="draft.barcode" class="form-input" :class="{ 'is-invalid': errors['new_product.barcode'] }" inputmode="numeric" placeholder="Optional" />
        <span v-if="errors['new_product.barcode']" class="form-error">{{ errors['new_product.barcode'] }}</span>
      </div>
    </form>

    <template #footer>
      <button type="button" class="btn btn-secondary mr-auto" :disabled="isSaving" @click="decide({ ignore: true })">Not a product</button>
      <template v-if="isCreating">
        <button v-if="candidates.length" type="button" class="btn btn-secondary" @click="isCreating = false">Back</button>
        <button type="submit" form="new-product-form" class="btn btn-primary" :disabled="isSaving">{{ isSaving ? 'Saving…' : 'Create product' }}</button>
      </template>
      <button v-else type="button" class="btn btn-primary" @click="isCreating = true"><FontAwesomeIcon icon="plus" /> New product</button>
    </template>
  </AppModal>
</template>
