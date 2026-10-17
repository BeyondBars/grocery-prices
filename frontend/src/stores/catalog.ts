import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { Paginated } from '@/types/user'
import type { BrandResource, CategoryResource, StorePayload, StoreResource } from '@/types/catalog'

/** Small reference lists used across the app: stores, categories and brands. */
export const useCatalogStore = defineStore('catalog', {
  state: () => ({
    stores: [] as StoreResource[],
    categories: [] as CategoryResource[],
  }),

  getters: {
    /** Categories as "Parent › Child" options, sorted by that label. */
    categoryOptions(state) {
      const byId = new Map(state.categories.map((category) => [category.id, category]))

      return state.categories
        .map((category) => {
          const parent = category.parent_id ? byId.get(category.parent_id) : null
          return { id: category.id, label: parent ? `${parent.name} › ${category.name}` : category.name }
        })
        .sort((a, b) => a.label.localeCompare(b.label))
    },
  },

  actions: {
    async fetchStores() {
      const { data } = await http.get<{ data: StoreResource[] }>('stores')
      this.stores = data.data
    },

    async saveStore(payload: StorePayload, id?: number) {
      const { data } = id
        ? await http.put<{ data: StoreResource }>(`stores/${id}`, payload)
        : await http.post<{ data: StoreResource }>('stores', payload)

      await this.fetchStores()

      return data.data
    },

    async deleteStore(id: number) {
      await http.delete(`stores/${id}`, { throwForbiddens: true })
      this.stores = this.stores.filter((store) => store.id !== id)
    },

    async fetchCategories() {
      const { data } = await http.get<{ data: CategoryResource[] }>('categories')
      this.categories = data.data
    },

    async searchBrands(search: string) {
      const { data } = await http.get<Paginated<BrandResource>>('brands', { params: { 'filter[search]': search } })
      return data.data
    },

    async createBrand(name: string) {
      const { data } = await http.post<{ data: BrandResource }>('brands', { name })
      return data.data
    },
  },
})
