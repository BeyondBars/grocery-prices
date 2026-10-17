<template>
  <PageHeader title="Stores" description="Supermarkets you shop at. Receipts and prices are grouped by store.">
    <button type="button" class="btn btn-primary" @click="openForm(null)">
      <FontAwesomeIcon icon="plus" /> Add store
    </button>
  </PageHeader>

  <div class="card overflow-hidden">
    <p v-if="!isLoading && !catalog.stores.length" class="px-4 py-10 text-center text-sm text-slate-500">No stores yet.</p>

    <ul class="divide-y divide-slate-100">
      <li v-for="store in catalog.stores" :key="store.id" class="flex items-start gap-3 px-4 py-4">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
          <FontAwesomeIcon icon="store" />
        </span>
        <div class="min-w-0 flex-1">
          <div class="font-medium text-slate-900">{{ store.name }}</div>
          <div class="mt-1 flex flex-wrap gap-1">
            <code v-for="alias in store.aliases" :key="alias" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">{{ alias }}</code>
            <span v-if="store.abn" class="text-xs text-slate-500">ABN {{ store.abn }}</span>
          </div>
        </div>
        <div class="flex shrink-0 gap-1">
          <button v-if="store.can.update" type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" :aria-label="`Edit ${store.name}`" @click="openForm(store)">
            <FontAwesomeIcon icon="pen" />
          </button>
          <button v-if="store.can.delete" type="button" class="rounded-md p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" :aria-label="`Delete ${store.name}`" @click="remove(store)">
            <FontAwesomeIcon icon="trash" />
          </button>
        </div>
      </li>
    </ul>
  </div>

  <StoreFormModal v-if="editing !== undefined" :store="editing" @close="editing = undefined" @saved="editing = undefined" />
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import PageHeader from '@/components/app/PageHeader.vue'
import StoreFormModal from '@/components/catalog/StoreFormModal.vue'
import { useCatalogStore } from '@/stores/catalog'
import confirm from '@/utils/alert'
import { errorMessage } from '@/utils/api-errors'
import type { StoreResource } from '@/types/catalog'

const catalog = useCatalogStore()
const toast = useToast()
const isLoading = ref(true)
/** undefined = form closed, null = adding, a store = editing it. */
const editing = ref<StoreResource | null | undefined>(undefined)

const openForm = (store: StoreResource | null) => (editing.value = store)

const remove = async (store: StoreResource) => {
  if (!(await confirm({ title: `Delete ${store.name}?`, text: 'Only stores without receipts can be deleted.' }))) return

  try {
    await catalog.deleteStore(store.id)
    toast.success('Store deleted.')
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

onMounted(async () => {
  await catalog.fetchStores()
  isLoading.value = false
})
</script>
