<template>
  <PageHeader title="Shopping lists" description="Plan a shop and see which store is cheapest for the whole list." />

  <form class="mb-6 flex gap-2" @submit.prevent="create">
    <input v-model="name" class="form-input" placeholder="New list, e.g. Weekly shop" aria-label="New list name" maxlength="100" />
    <button type="submit" class="btn btn-primary shrink-0" :disabled="!name.trim()"><FontAwesomeIcon icon="plus" /> Create</button>
  </form>

  <p v-if="isLoaded && !listStore.lists.length" class="card px-4 py-10 text-center text-sm text-slate-500">No shopping lists yet.</p>

  <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <li v-for="list in listStore.lists" :key="list.id">
      <RouterLink :to="{ name: 'shopping-lists.details', params: { id: list.id } }" class="card block p-4 hover:border-brand-500">
        <div class="flex items-center gap-3">
          <span class="flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><FontAwesomeIcon icon="list-check" /></span>
          <div class="min-w-0">
            <div class="truncate font-semibold text-slate-900">{{ list.name }}</div>
            <div class="text-xs text-slate-500">
              {{ list.items_count ?? 0 }} items<template v-if="list.items_count"> · {{ list.unchecked_count }} to buy</template>
            </div>
          </div>
        </div>
      </RouterLink>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import PageHeader from '@/components/app/PageHeader.vue'
import { useShoppingListStore } from '@/stores/shopping-lists'
import { errorMessage } from '@/utils/api-errors'

const listStore = useShoppingListStore()
const router = useRouter()
const toast = useToast()
const name = ref('')
const isLoaded = ref(false)

const create = async () => {
  try {
    const list = await listStore.createList(name.value.trim())
    name.value = ''
    router.push({ name: 'shopping-lists.details', params: { id: list.id } })
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

onMounted(async () => {
  await listStore.fetchLists()
  isLoaded.value = true
})
</script>
