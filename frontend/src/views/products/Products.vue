<template>
  <PageHeader title="Products" description="Every product tracked across receipts. Receipt lines are matched to these.">
    <button type="button" class="btn btn-primary" @click="editing = null">
      <FontAwesomeIcon icon="plus" /> Add product
    </button>
  </PageHeader>

  <div class="card overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="relative sm:w-80">
        <FontAwesomeIcon icon="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" />
        <input v-model="productStore.search" type="search" class="form-input py-2 pl-9" placeholder="Search name, brand or barcode…" aria-label="Search products" />
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input v-model="productStore.onlyPriced" type="checkbox" class="form-check" />
        Only products I've bought
      </label>
    </div>

    <p v-if="!productStore.isLoading && !productStore.products.length" class="px-4 py-10 text-center text-sm text-slate-500">
      {{ productStore.search ? 'No products match your search.' : 'No products yet.' }}
    </p>

    <ul class="divide-y divide-slate-100">
      <li v-for="product in productStore.products" :key="product.id" class="flex items-center gap-3 px-4 py-3">
        <RouterLink :to="{ name: 'products.prices', params: { id: product.id } }" class="min-w-0 flex-1">
          <div class="font-medium text-slate-900 hover:text-brand-700">
            <span v-if="product.brand" class="text-slate-500">{{ product.brand.name }}&nbsp;</span>{{ product.name }}
          </div>
          <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            <span v-if="product.size" class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-700">
              {{ product.pack_count > 1 ? `${product.pack_count} × ` : '' }}{{ product.size }}
            </span>
            <span v-if="product.variant">{{ product.variant }}</span>
            <span v-if="product.category">{{ product.category.name }}</span>
            <span v-if="product.barcode" class="font-mono">{{ product.barcode }}</span>
          </div>
        </RouterLink>
        <RouterLink v-if="product.cheapest_price" :to="{ name: 'products.prices', params: { id: product.id } }" class="shrink-0 text-right">
          <div class="font-semibold text-slate-900 tabular-nums">{{ formatPrice(product.cheapest_price.price_cents, product.cheapest_price.unit) }}</div>
          <div class="text-xs text-slate-500">
            at {{ product.cheapest_price.store.name }}<template v-if="product.cheapest_price.stores_count > 1"> · {{ product.cheapest_price.stores_count }} stores</template>
          </div>
        </RouterLink>
        <div class="flex shrink-0 gap-1">
          <button v-if="product.can.update" type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" :aria-label="`Edit ${product.name}`" @click="editing = product">
            <FontAwesomeIcon icon="pen" />
          </button>
          <button v-if="product.can.merge" type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" :aria-label="`Merge ${product.name} into another product`" title="Merge duplicate" @click="merging = product">
            <FontAwesomeIcon icon="code-merge" />
          </button>
          <button v-if="product.can.delete" type="button" class="rounded-md p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" :aria-label="`Delete ${product.name}`" @click="remove(product)">
            <FontAwesomeIcon icon="trash" />
          </button>
        </div>
      </li>
    </ul>

    <div v-if="productStore.lastPage > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
      <span>{{ productStore.total }} products</span>
      <div class="flex items-center gap-2">
        <button type="button" class="btn btn-secondary" :disabled="productStore.page <= 1" @click="productStore.fetchProducts(productStore.page - 1)">Previous</button>
        <span>{{ productStore.page }} / {{ productStore.lastPage }}</span>
        <button type="button" class="btn btn-secondary" :disabled="productStore.page >= productStore.lastPage" @click="productStore.fetchProducts(productStore.page + 1)">Next</button>
      </div>
    </div>
  </div>

  <ProductFormModal v-if="editing !== undefined" :product="editing" @close="editing = undefined" @saved="editing = undefined" />
  <MergeProductModal v-if="merging" :product="merging" @close="merging = null" />
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import PageHeader from '@/components/app/PageHeader.vue'
import MergeProductModal from '@/components/catalog/MergeProductModal.vue'
import ProductFormModal from '@/components/catalog/ProductFormModal.vue'
import { useProductStore } from '@/stores/products'
import confirm from '@/utils/alert'
import { errorMessage } from '@/utils/api-errors'
import { formatPrice } from '@/utils/money'
import type { ProductResource } from '@/types/catalog'

const productStore = useProductStore()
const toast = useToast()
/** undefined = form closed, null = adding, a product = editing it. */
const editing = ref<ProductResource | null | undefined>(undefined)
const merging = ref<ProductResource | null>(null)

watch(
  () => [productStore.search, productStore.onlyPriced],
  useDebounceFn(() => productStore.fetchProducts(), 300)
)

const remove = async (product: ProductResource) => {
  if (!(await confirm({ title: `Delete ${product.name}?`, text: 'Only products without receipts can be deleted.' }))) return

  try {
    await productStore.deleteProduct(product.id)
    toast.success('Product deleted.')
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

onMounted(() => productStore.fetchProducts())
</script>
