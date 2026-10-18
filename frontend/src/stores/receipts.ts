import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { Paginated } from '@/types/user'
import type {
  MatchCandidate,
  MatchPayload,
  NewProductDraft,
  ReceiptHeaderPayload,
  ReceiptItemPayload,
  ReceiptItemResource,
  ReceiptResource,
  ReceiptStatus,
} from '@/types/receipt'

/** Statuses that change by themselves while the queue works on the receipt. */
export const BUSY_STATUSES: ReceiptStatus[] = ['uploaded', 'processing']

export const useReceiptStore = defineStore('receipts', {
  state: () => ({
    receipts: [] as ReceiptResource[],
    page: 1,
    lastPage: 1,
    receipt: null as ReceiptResource | null,
  }),

  getters: {
    hasBusyReceipts: (state) => state.receipts.some((receipt) => BUSY_STATUSES.includes(receipt.status)),
  },

  actions: {
    async fetchReceipts(page?: number) {
      const { data } = await http.get<Paginated<ReceiptResource>>('receipts', { params: { page: page ?? this.page } })

      this.receipts = data.data
      this.page = data.meta.current_page
      this.lastPage = data.meta.last_page
    },

    /** Upload files; returns the names of files that were already uploaded before. */
    async upload(files: File[]) {
      const form = new FormData()
      files.forEach((file) => form.append('files[]', file))

      const { data } = await http.post<{ data: ReceiptResource[]; duplicates: string[] }>('receipts', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      await this.fetchReceipts(1)

      return data.duplicates
    },

    async fetchReceipt(id: number | string) {
      const { data } = await http.get<{ data: ReceiptResource }>(`receipts/${id}`)
      this.receipt = data.data
    },

    async updateReceipt(id: number, payload: ReceiptHeaderPayload) {
      const { data } = await http.put<{ data: ReceiptResource }>(`receipts/${id}`, payload)
      this.receipt = data.data
    },

    async updateItem(id: number, payload: ReceiptItemPayload) {
      const { data } = await http.put<{ data: ReceiptItemResource }>(`receipt-items/${id}`, payload)

      if (this.receipt?.items) {
        this.receipt.items = this.receipt.items.map((item) => (item.id === id ? data.data : item))
      }
    },

    async fetchCandidates(itemId: number) {
      const { data } = await http.get<{ data: MatchCandidate[]; new_product: NewProductDraft }>(`receipt-items/${itemId}/candidates`)
      return data
    },

    /** Record the user's decision for a line. Reloads the receipt: its status and other lines may change. */
    async matchItem(itemId: number, payload: MatchPayload) {
      await http.post(`receipt-items/${itemId}/match`, payload)

      if (this.receipt) await this.fetchReceipt(this.receipt.id)
    },

    async reprocess(id: number, ocr = false) {
      const { data } = await http.post<{ data: ReceiptResource }>(`receipts/${id}/reprocess`, { ocr })
      this.receipt = { ...data.data, items: [] }
    },

    async deleteReceipt(id: number) {
      await http.delete(`receipts/${id}`)
      this.receipts = this.receipts.filter((receipt) => receipt.id !== id)
    },

    /** The original upload as an object URL (the file needs the session cookie, so no plain <img src>). */
    async fileUrl(id: number) {
      const { data } = await http.get<Blob>(`receipts/${id}/file`, { responseType: 'blob' })
      return URL.createObjectURL(data)
    },
  },
})
