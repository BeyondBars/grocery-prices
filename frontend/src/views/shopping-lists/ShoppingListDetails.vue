<template>
  <div v-if="list">
    <PageHeader :title="list.name">
      <template #description>
        <RouterLink :to="{ name: 'shopping-lists' }" class="link-muted"><FontAwesomeIcon icon="chevron-left" class="mr-1" />Shopping lists</RouterLink>
      </template>
      <div class="flex gap-2">
        <button type="button" class="btn btn-secondary" @click="rename">Rename</button>
        <button type="button" class="btn btn-secondary text-red-600" @click="remove">Delete</button>
      </div>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
      <div class="card self-start">
        <div class="border-b border-slate-200 p-4">
          <AddItemInput @product="(product) => add({ product_id: product.id })" @note="(text) => add({ free_text: text })" />
        </div>

        <p v-if="!list.items?.length" class="px-4 py-10 text-center text-sm text-slate-500">Add products or notes to start your list.</p>

        <ul class="divide-y divide-slate-100">
          <ShoppingListItemRow v-for="item in toBuy" :key="item.id" :item="item" :comparison="listStore.comparison" />
        </ul>

        <template v-if="gotIt.length">
          <p class="border-t border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">In the trolley ({{ gotIt.length }})</p>
          <ul class="divide-y divide-slate-100">
            <ShoppingListItemRow v-for="item in gotIt" :key="item.id" :item="item" :comparison="listStore.comparison" />
          </ul>
        </template>
      </div>

      <ListComparison v-if="listStore.comparison && list.items?.length" :comparison="listStore.comparison" :store-ids="storeIds" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import PageHeader from '@/components/app/PageHeader.vue'
import AddItemInput from '@/components/shopping/AddItemInput.vue'
import ListComparison from '@/components/shopping/ListComparison.vue'
import ShoppingListItemRow from '@/components/shopping/ShoppingListItemRow.vue'
import { useCatalogStore } from '@/stores/catalog'
import { useShoppingListStore } from '@/stores/shopping-lists'
import confirm from '@/utils/alert'
import { errorMessage } from '@/utils/api-errors'
import type { ShoppingListItemPayload } from '@/types/shopping-list'

const props = defineProps<{ id: string }>()

const listStore = useShoppingListStore()
const catalog = useCatalogStore()
const router = useRouter()
const toast = useToast()

const list = computed(() => listStore.list)
const toBuy = computed(() => (list.value?.items ?? []).filter((item) => !item.is_checked))
const gotIt = computed(() => (list.value?.items ?? []).filter((item) => item.is_checked))
const storeIds = computed(() => catalog.stores.map((store) => store.id))

const add = async (payload: ShoppingListItemPayload) => {
  try {
    await listStore.addItem(Number(props.id), payload)
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

const rename = async () => {
  const { value } = await Swal.fire({
    title: 'Rename list',
    input: 'text',
    inputValue: list.value?.name,
    showCancelButton: true,
    confirmButtonText: 'Save',
    buttonsStyling: false,
    customClass: { confirmButton: 'btn btn-primary ml-2', cancelButton: 'btn btn-secondary' },
    inputValidator: (text) => (text.trim() ? null : 'Enter a name'),
  })

  if (value) await listStore.renameList(Number(props.id), value.trim())
}

const remove = async () => {
  if (!(await confirm({ title: `Delete ${list.value?.name}?`, confirmButtonText: 'Delete' }))) return

  await listStore.deleteList(Number(props.id))
  router.push({ name: 'shopping-lists' })
}

onMounted(async () => {
  listStore.list = null
  listStore.comparison = null
  await Promise.all([listStore.fetchList(props.id), catalog.stores.length ? null : catalog.fetchStores()])
})
</script>
