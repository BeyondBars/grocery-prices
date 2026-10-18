<script setup lang="ts">
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import AppModal from '@/components/app/AppModal.vue'
import { useProductStore } from '@/stores/products'
import confirm from '@/utils/alert'
import { errorMessage } from '@/utils/api-errors'
import { productLabel } from '@/utils/product'
import type { ProductResource } from '@/types/catalog'

/** Merge a duplicate into the product to keep: its receipts and prices move across. */
const props = defineProps<{ product: ProductResource }>()
const emit = defineEmits<{ close: [] }>()

const productStore = useProductStore()
const toast = useToast()
const search = ref(props.product.name)
const results = ref<ProductResource[]>([])

const find = useDebounceFn(async (value: string) => {
  results.value = (value.trim() ? await productStore.searchProducts(value) : []).filter((product) => product.id !== props.product.id)
}, 250)

watch(search, find, { immediate: true })

const merge = async (into: ProductResource) => {
  const confirmed = await confirm({
    title: 'Merge these products?',
    // Plain text: product names are typed by users.
    text: `All receipts and prices for "${productLabel(props.product)}" move to "${productLabel(into)}". This can't be undone.`,
    confirmButtonText: 'Merge',
  })
  if (!confirmed) return

  try {
    await productStore.mergeProduct(props.product.id, into.id)
    toast.success('Products merged.')
    emit('close')
  } catch (error) {
    toast.error(errorMessage(error))
  }
}
</script>

<template>
  <AppModal title="Merge duplicate" :description="`Keep which product instead of ${productLabel(product)}?`" @close="emit('close')">
    <div class="relative mb-3">
      <FontAwesomeIcon icon="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" />
      <input v-model="search" type="search" class="form-input py-2 pl-9" placeholder="Search products…" aria-label="Search products" />
    </div>
    <p v-if="!results.length" class="py-4 text-center text-sm text-slate-500">No other products match.</p>
    <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
      <li v-for="result in results" :key="result.id">
        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-slate-50" @click="merge(result)">
          <span class="font-medium text-slate-900">{{ productLabel(result) }}</span>
          <FontAwesomeIcon icon="chevron-right" class="text-slate-300" />
        </button>
      </li>
    </ul>
  </AppModal>
</template>
