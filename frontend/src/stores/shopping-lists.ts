import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { ListComparison, ShoppingListItemPayload, ShoppingListItemResource, ShoppingListResource } from '@/types/shopping-list'

export const useShoppingListStore = defineStore('shopping-lists', {
  state: () => ({
    lists: [] as ShoppingListResource[],
    list: null as ShoppingListResource | null,
    comparison: null as ListComparison | null,
  }),

  actions: {
    async fetchLists() {
      const { data } = await http.get<{ data: ShoppingListResource[] }>('shopping-lists')
      this.lists = data.data
    },

    async createList(name: string) {
      const { data } = await http.post<{ data: ShoppingListResource }>('shopping-lists', { name })
      this.lists.unshift(data.data)
      return data.data
    },

    async renameList(id: number, name: string) {
      const { data } = await http.put<{ data: ShoppingListResource }>(`shopping-lists/${id}`, { name })
      if (this.list?.id === id) this.list.name = data.data.name
    },

    async deleteList(id: number) {
      await http.delete(`shopping-lists/${id}`)
      this.lists = this.lists.filter((list) => list.id !== id)
    },

    async fetchList(id: number | string) {
      const [{ data: list }] = await Promise.all([
        http.get<{ data: ShoppingListResource }>(`shopping-lists/${id}`),
        this.fetchComparison(id),
      ])
      this.list = list.data
    },

    async fetchComparison(id: number | string) {
      const { data } = await http.get<{ data: ListComparison }>(`shopping-lists/${id}/comparison`)
      this.comparison = data.data
    },

    /** Add to a list (not necessarily the open one); adding a product twice adds to its quantity. */
    async addItem(listId: number, payload: ShoppingListItemPayload) {
      const { data } = await http.post<{ data: ShoppingListItemResource }>(`shopping-lists/${listId}/items`, payload)

      if (this.list?.id === listId) {
        const items = this.list.items ?? []
        this.list.items = items.some((item) => item.id === data.data.id)
          ? items.map((item) => (item.id === data.data.id ? data.data : item))
          : [...items, data.data]
        await this.fetchComparison(listId)
      }

      return data.data
    },

    async updateItem(itemId: number, payload: ShoppingListItemPayload) {
      if (!this.list) return

      const { data } = await http.put<{ data: ShoppingListItemResource }>(`shopping-lists/${this.list.id}/items/${itemId}`, payload)
      this.list.items = (this.list.items ?? []).map((item) => (item.id === itemId ? data.data : item))

      // Ticking an item off doesn't change prices.
      if (!('is_checked' in payload && Object.keys(payload).length === 1)) await this.fetchComparison(this.list.id)
    },

    async removeItem(itemId: number) {
      if (!this.list) return

      await http.delete(`shopping-lists/${this.list.id}/items/${itemId}`)
      this.list.items = (this.list.items ?? []).filter((item) => item.id !== itemId)
      await this.fetchComparison(this.list.id)
    },
  },
})
