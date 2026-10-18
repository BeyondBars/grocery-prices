<template>
  <PageHeader title="Receipts" description="Upload your grocery receipts. We read the items and prices so you don't have to type them." />

  <ReceiptDropzone class="mb-6" :busy="isUploading" @files="upload" @rejected="(message) => toast.warning(message)" />

  <div class="card overflow-hidden">
    <p v-if="isLoaded && !receiptStore.receipts.length" class="px-4 py-10 text-center text-sm text-slate-500">No receipts yet.</p>

    <ul class="divide-y divide-slate-100">
      <li v-for="receipt in receiptStore.receipts" :key="receipt.id">
        <RouterLink :to="{ name: 'receipts.details', params: { id: receipt.id } }" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
          <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
            <FontAwesomeIcon :icon="receipt.file_mime === 'application/pdf' ? 'file-pdf' : 'receipt'" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="truncate font-medium text-slate-900">{{ receipt.store?.name ?? (isBusy(receipt) ? 'Reading receipt…' : 'Unknown store') }}</div>
            <div class="text-xs text-slate-500">
              {{ receipt.purchased_at ? $date(receipt.purchased_at, { format: 'ddd D MMM YYYY' }) : `Uploaded ${$date(receipt.created_at, { format: 'D MMM' })}` }}
              <template v-if="receipt.items_count"> · {{ receipt.items_count }} items</template>
            </div>
          </div>
          <div class="flex shrink-0 flex-col items-end gap-1">
            <span class="font-semibold text-slate-900 tabular-nums">{{ formatCents(receipt.total_cents) }}</span>
            <ReceiptStatusBadge :status="receipt.status" />
          </div>
        </RouterLink>
      </li>
    </ul>

    <div v-if="receiptStore.lastPage > 1" class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
      <button type="button" class="btn btn-secondary" :disabled="receiptStore.page <= 1" @click="receiptStore.fetchReceipts(receiptStore.page - 1)">Previous</button>
      <span>{{ receiptStore.page }} / {{ receiptStore.lastPage }}</span>
      <button type="button" class="btn btn-secondary" :disabled="receiptStore.page >= receiptStore.lastPage" @click="receiptStore.fetchReceipts(receiptStore.page + 1)">Next</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import PageHeader from '@/components/app/PageHeader.vue'
import ReceiptDropzone from '@/components/receipts/ReceiptDropzone.vue'
import ReceiptStatusBadge from '@/components/receipts/ReceiptStatusBadge.vue'
import { usePolling } from '@/composables/use-polling'
import { BUSY_STATUSES, useReceiptStore } from '@/stores/receipts'
import { errorMessage, validationErrors } from '@/utils/api-errors'
import { formatCents } from '@/utils/money'
import type { ReceiptResource } from '@/types/receipt'

const receiptStore = useReceiptStore()
const toast = useToast()
const isUploading = ref(false)
const isLoaded = ref(false)

const isBusy = (receipt: ReceiptResource) => BUSY_STATUSES.includes(receipt.status)

usePolling(() => receiptStore.hasBusyReceipts, () => receiptStore.fetchReceipts())

const upload = async (files: File[]) => {
  isUploading.value = true

  try {
    const duplicates = await receiptStore.upload(files)
    const uploaded = files.length - duplicates.length

    if (uploaded) toast.success(`${uploaded} receipt${uploaded === 1 ? '' : 's'} uploaded. We're reading ${uploaded === 1 ? 'it' : 'them'} now.`)
    if (duplicates.length) toast.info(`Already uploaded: ${duplicates.join(', ')}`)
  } catch (error) {
    toast.error(Object.values(validationErrors(error))[0] ?? errorMessage(error))
  } finally {
    isUploading.value = false
  }
}

onMounted(async () => {
  await receiptStore.fetchReceipts(1)
  isLoaded.value = true
})
</script>
