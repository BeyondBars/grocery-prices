<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useToast } from 'vue-toast-notification'
import AppModal from '@/components/app/AppModal.vue'
import { useCatalogStore } from '@/stores/catalog'
import { useProductStore } from '@/stores/products'
import { errorMessage, validationErrors } from '@/utils/api-errors'
import type { BrandResource, ProductResource } from '@/types/catalog'

/** Add or edit a product. A brand typed that doesn't exist yet is created on save. */
const props = defineProps<{ product?: ProductResource | null }>()
const emit = defineEmits<{ close: []; saved: [product: ProductResource] }>()

const catalog = useCatalogStore()
const productStore = useProductStore()
const toast = useToast()

const form = ref({
  name: props.product?.name ?? '',
  brand: props.product?.brand?.name ?? '',
  category_id: props.product?.category?.id ?? null,
  variant: props.product?.variant ?? '',
  size: props.product?.size ?? '',
  pack_count: props.product?.pack_count ?? 1,
  barcode: props.product?.barcode ?? '',
})
const brandOptions = ref<BrandResource[]>([])
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

const searchBrands = useDebounceFn(async (search: string) => {
  brandOptions.value = search ? await catalog.searchBrands(search) : []
}, 250)

watch(() => form.value.brand, searchBrands)

/** The id of the typed brand, creating it when it's new. */
const resolveBrandId = async (): Promise<number | null> => {
  const name = form.value.brand.trim()
  if (!name) return null

  const matches = await catalog.searchBrands(name)
  const existing = matches.find((brand) => brand.name.toLowerCase() === name.toLowerCase())

  return existing ? existing.id : (await catalog.createBrand(name)).id
}

const save = async () => {
  isSaving.value = true
  errors.value = {}

  try {
    const product = await productStore.saveProduct(
      {
        name: form.value.name,
        brand_id: await resolveBrandId(),
        category_id: form.value.category_id,
        variant: form.value.variant || null,
        size: form.value.size || null,
        pack_count: form.value.pack_count || 1,
        barcode: form.value.barcode || null,
      },
      props.product?.id
    )
    toast.success(props.product ? 'Product updated.' : 'Product added.')
    emit('saved', product)
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    isSaving.value = false
  }
}

onMounted(() => catalog.categories.length || catalog.fetchCategories())
</script>

<template>
  <AppModal :title="product ? 'Edit product' : 'Add product'" description="Size and variant keep similar products apart, e.g. 2L vs 1L, or Full Cream vs Light." @close="emit('close')">
    <form id="product-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
      <div class="sm:col-span-2">
        <label class="form-label" for="product-name">Name</label>
        <input id="product-name" v-model="form.name" class="form-input" :class="{ 'is-invalid': errors.name }" placeholder="Full Cream Milk" required />
        <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
      </div>
      <div>
        <label class="form-label" for="product-brand">Brand</label>
        <input id="product-brand" v-model="form.brand" class="form-input" list="product-brand-options" placeholder="Paul's" autocomplete="off" />
        <datalist id="product-brand-options">
          <option v-for="brand in brandOptions" :key="brand.id" :value="brand.name" />
        </datalist>
        <span v-if="errors.brand_id" class="form-error">{{ errors.brand_id }}</span>
      </div>
      <div>
        <label class="form-label" for="product-variant">Variant</label>
        <input id="product-variant" v-model="form.variant" class="form-input" :class="{ 'is-invalid': errors.variant }" placeholder="Full Cream" />
        <span v-if="errors.variant" class="form-error">{{ errors.variant }}</span>
      </div>
      <div>
        <label class="form-label" for="product-size">Size</label>
        <input id="product-size" v-model="form.size" class="form-input" :class="{ 'is-invalid': errors.size }" placeholder="2L, 700g, 1kg" />
        <span v-if="errors.size" class="form-error">{{ errors.size }}</span>
      </div>
      <div>
        <label class="form-label" for="product-pack">Pack of</label>
        <input id="product-pack" v-model.number="form.pack_count" type="number" min="1" class="form-input" :class="{ 'is-invalid': errors.pack_count }" />
        <span v-if="errors.pack_count" class="form-error">{{ errors.pack_count }}</span>
      </div>
      <div>
        <label class="form-label" for="product-category">Category</label>
        <select id="product-category" v-model="form.category_id" class="form-input">
          <option :value="null">None</option>
          <option v-for="option in catalog.categoryOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
        </select>
      </div>
      <div>
        <label class="form-label" for="product-barcode">Barcode</label>
        <input id="product-barcode" v-model="form.barcode" class="form-input" :class="{ 'is-invalid': errors.barcode }" inputmode="numeric" placeholder="Optional" />
        <span v-if="errors.barcode" class="form-error">{{ errors.barcode }}</span>
      </div>
    </form>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="emit('close')">Cancel</button>
      <button type="submit" form="product-form" class="btn btn-primary" :disabled="isSaving">{{ isSaving ? 'Saving…' : 'Save' }}</button>
    </template>
  </AppModal>
</template>
