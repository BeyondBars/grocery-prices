import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { Paginated } from '@/types/user'
import type { ProductPayload, ProductResource } from '@/types/catalog'

export const useProductStore = defineStore('products', {
  state: () => ({
    products: [] as ProductResource[],
    search: '',
    page: 1,
    lastPage: 1,
    total: 0,
    isLoading: false,
  }),

  actions: {
    async fetchProducts(page = 1) {
      this.isLoading = true

      try {
        const { data } = await http.get<Paginated<ProductResource>>('products', {
          params: { page, 'filter[search]': this.search || undefined },
        })

        this.products = data.data
        this.page = data.meta.current_page
        this.lastPage = data.meta.last_page
        this.total = data.meta.total
      } finally {
        this.isLoading = false
      }
    },

    async saveProduct(payload: ProductPayload, id?: number) {
      const { data } = id
        ? await http.put<{ data: ProductResource }>(`products/${id}`, payload)
        : await http.post<{ data: ProductResource }>('products', payload)

      await this.fetchProducts(this.page)

      return data.data
    },

    async searchProducts(search: string) {
      const { data } = await http.get<Paginated<ProductResource>>('products', { params: { 'filter[search]': search } })
      return data.data
    },

    async mergeProduct(id: number, intoId: number) {
      await http.post(`products/${id}/merge`, { into_product_id: intoId }, { throwForbiddens: true })
      await this.fetchProducts(this.page)
    },

    async deleteProduct(id: number) {
      await http.delete(`products/${id}`, { throwForbiddens: true })
      await this.fetchProducts(this.page)
    },
  },
})
