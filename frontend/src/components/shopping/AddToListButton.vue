<script setup lang="ts">
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { useShoppingListStore } from '@/stores/shopping-lists'
import { errorMessage } from '@/utils/api-errors'

/** "Add to list" for a product: pick one of the user's lists (a first list is created if needed). */
const props = defineProps<{ productId: number }>()

const listStore = useShoppingListStore()
const toast = useToast()
const isOpen = ref(false)

const open = async () => {
  await listStore.fetchLists()
  isOpen.value = !isOpen.value
}

const addTo = async (listId?: number) => {
  isOpen.value = false

  try {
    const list = listId ? listStore.lists.find((l) => l.id === listId)! : await listStore.createList('Shopping list')
    await listStore.addItem(list.id, { product_id: props.productId })
    toast.success(`Added to ${list.name}.`)
  } catch (error) {
    toast.error(errorMessage(error))
  }
}
</script>

<template>
  <div class="relative">
    <button type="button" class="btn btn-primary" :aria-expanded="isOpen" @click="open"><FontAwesomeIcon icon="list-check" /> Add to list</button>
    <ul v-if="isOpen" class="card absolute right-0 z-20 mt-1 w-56 overflow-hidden py-1 shadow-lg">
      <li v-for="list in listStore.lists" :key="list.id">
        <button type="button" class="block w-full truncate px-3 py-2 text-left text-sm hover:bg-slate-50" @click="addTo(list.id)">{{ list.name }}</button>
      </li>
      <li v-if="!listStore.lists.length">
        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50" @click="addTo()">New shopping list</button>
      </li>
    </ul>
  </div>
</template>
