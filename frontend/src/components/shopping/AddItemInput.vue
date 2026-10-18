<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useProductStore } from '@/stores/products'
import { productLabel } from '@/utils/product'
import type { ProductResource } from '@/types/catalog'

/**
 * Type to add: pick a matching product, or keep what you typed as a note ("Bread") to link to a
 * product later. Arrow keys move, Enter picks.
 */
const props = withDefaults(defineProps<{ placeholder?: string; allowNote?: boolean }>(), { placeholder: 'Add an item…', allowNote: true })
const emit = defineEmits<{ product: [product: ProductResource]; note: [text: string] }>()

const productStore = useProductStore()
const text = ref('')
const results = ref<ProductResource[]>([])
const highlighted = ref(0)
const isOpen = ref(false)

const options = computed(() => [
  ...results.value.map((product) => ({ kind: 'product' as const, product })),
  ...(props.allowNote && text.value.trim() ? [{ kind: 'note' as const, product: null }] : []),
])

watch(
  text,
  useDebounceFn(async (value: string) => {
    results.value = value.trim().length >= 2 ? (await productStore.searchProducts(value)).slice(0, 6) : []
    highlighted.value = 0
    isOpen.value = !!value.trim()
  }, 200)
)

const pick = (index = highlighted.value) => {
  const option = options.value[index]
  if (!option) return

  option.kind === 'product' ? emit('product', option.product!) : emit('note', text.value.trim())
  text.value = ''
  results.value = []
  isOpen.value = false
}

const move = (step: number) => {
  if (options.value.length) highlighted.value = (highlighted.value + step + options.value.length) % options.value.length
}
</script>

<template>
  <div class="relative">
    <FontAwesomeIcon icon="plus" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" />
    <input
      v-model="text"
      type="text"
      class="form-input pl-9"
      :placeholder="placeholder"
      aria-label="Add an item"
      autocomplete="off"
      @keydown.down.prevent="move(1)"
      @keydown.up.prevent="move(-1)"
      @keydown.enter.prevent="pick()"
      @keydown.esc="isOpen = false"
      @blur="isOpen = false"
    />
    <ul v-if="isOpen && options.length" class="card absolute inset-x-0 top-full z-20 mt-1 overflow-hidden py-1 shadow-lg" role="listbox">
      <li v-for="(option, index) in options" :key="option.product?.id ?? 'note'" role="option" :aria-selected="index === highlighted">
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm"
          :class="index === highlighted ? 'bg-brand-50 text-brand-800' : 'text-slate-700'"
          @mousedown.prevent="pick(index)"
          @mouseenter="highlighted = index"
        >
          <template v-if="option.kind === 'product'">
            <FontAwesomeIcon icon="basket-shopping" class="text-slate-400" />
            <span class="flex-1">{{ productLabel(option.product!) }}</span>
          </template>
          <template v-else>
            <FontAwesomeIcon icon="pen" class="text-slate-400" />
            <span class="flex-1">Add “{{ text.trim() }}” as a note</span>
          </template>
        </button>
      </li>
    </ul>
  </div>
</template>
