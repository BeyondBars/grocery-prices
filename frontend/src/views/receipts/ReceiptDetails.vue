<template>
  <div v-if="receipt">
    <PageHeader :title="receipt.store?.name ?? 'Receipt'">
      <template #description>
        <RouterLink :to="{ name: 'receipts' }" class="link-muted"><FontAwesomeIcon icon="chevron-left" class="mr-1" />All receipts</RouterLink>
      </template>
      <div class="flex items-center gap-2">
        <ReceiptStatusBadge :status="receipt.status" />
        <button v-if="!isBusy" type="button" class="btn btn-secondary" @click="readAgain">Read again</button>
        <button type="button" class="btn btn-secondary text-red-600" @click="remove">Delete</button>
      </div>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
      <!-- The original, to check against -->
      <div class="card order-last self-start overflow-hidden lg:sticky lg:top-4 lg:order-first">
        <iframe v-if="fileUrl && receipt.file_mime === 'application/pdf'" :src="fileUrl" class="h-[70vh] w-full" title="Receipt PDF" />
        <img v-else-if="fileUrl" :src="fileUrl" alt="Uploaded receipt" class="max-h-[80vh] w-full object-contain" />
        <p v-else class="px-4 py-16 text-center text-sm text-slate-400">Loading receipt…</p>
      </div>

      <div class="space-y-4">
        <div v-if="isBusy" class="card flex items-center gap-3 p-4 text-sm text-sky-800">
          <FontAwesomeIcon icon="spinner" spin /> Reading your receipt. This usually takes a few seconds.
        </div>

        <div v-if="receipt.status === 'failed'" class="card border-red-200 bg-red-50 p-4 text-sm text-red-800">
          <p class="font-semibold">We couldn't read this receipt.</p>
          <p class="mt-1">{{ receipt.failure_reason }}</p>
          <button type="button" class="btn btn-secondary mt-3" @click="retry">Try again</button>
        </div>

        <div v-for="warning in receipt.warnings" :key="warning" class="card border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <FontAwesomeIcon icon="triangle-exclamation" class="mr-1" /> {{ warning }}
        </div>

        <div v-if="receipt.status === 'completed'" class="card border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
          <FontAwesomeIcon icon="check" class="mr-1" /> All done. These prices are now in your price history.
        </div>
        <div v-else-if="receipt.status === 'needs_review' && toReview" class="card border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          {{ toReview }} of {{ receipt.items?.length }} lines need you: confirm the suggestion or choose the product.
        </div>
        <div v-else-if="receipt.status === 'needs_review'" class="card border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          Add the store and date to finish this receipt.
        </div>

        <template v-if="!isBusy && receipt.status !== 'failed'">
          <ReceiptHeaderForm :receipt="receipt" />

          <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
              <h2 class="font-semibold text-slate-900">Items</h2>
              <span class="text-sm text-slate-500">{{ receipt.items?.length ?? 0 }} lines</span>
            </div>
            <ul class="divide-y divide-slate-100">
              <ReceiptItemRow v-for="item in receipt.items" :key="item.id" :item="item" />
            </ul>
            <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
              <span class="text-slate-600">
                Items total
                <span v-if="receipt.discount_cents" class="text-emerald-700"> (after {{ formatCents(receipt.discount_cents) }} receipt discount)</span>
              </span>
              <span class="font-semibold tabular-nums" :class="totalsMatch ? 'text-emerald-700' : 'text-amber-700'">
                {{ formatCents(itemsTotal) }}
                <template v-if="receipt.total_cents !== null && !totalsMatch"> of {{ formatCents(receipt.total_cents) }}</template>
                <FontAwesomeIcon v-if="totalsMatch" icon="check" class="ml-1" />
              </span>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import PageHeader from '@/components/app/PageHeader.vue'
import ReceiptHeaderForm from '@/components/receipts/ReceiptHeaderForm.vue'
import ReceiptItemRow from '@/components/receipts/ReceiptItemRow.vue'
import ReceiptStatusBadge from '@/components/receipts/ReceiptStatusBadge.vue'
import { usePolling } from '@/composables/use-polling'
import { BUSY_STATUSES, useReceiptStore } from '@/stores/receipts'
import confirm from '@/utils/alert'
import { errorMessage } from '@/utils/api-errors'
import { formatCents } from '@/utils/money'

const props = defineProps<{ id: string }>()

const receiptStore = useReceiptStore()
const router = useRouter()
const toast = useToast()
const fileUrl = ref<string | null>(null)

const receipt = computed(() => receiptStore.receipt)
const isBusy = computed(() => !!receipt.value && BUSY_STATUSES.includes(receipt.value.status))
const itemsTotal = computed(
  () => (receipt.value?.items ?? []).reduce((sum, item) => sum + item.total_price_cents - item.discount_cents, 0) - (receipt.value?.discount_cents ?? 0)
)
const toReview = computed(() => (receipt.value?.items ?? []).filter((item) => ['pending', 'suggested'].includes(item.match_status)).length)
// A few cents either way is cash rounding.
const totalsMatch = computed(() => receipt.value?.total_cents === null || Math.abs(itemsTotal.value - (receipt.value?.total_cents ?? 0)) <= 5)

usePolling(isBusy, () => receiptStore.fetchReceipt(props.id))

const readAgain = async () => {
  const confirmed = await confirm({
    title: 'Read this receipt again?',
    text: 'The items are read from the receipt again, so any corrections you made to them are lost.',
    confirmButtonText: 'Read again',
  })

  if (confirmed) await retry()
}

const retry = async () => {
  try {
    await receiptStore.reprocess(Number(props.id))
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

const remove = async () => {
  if (!(await confirm({ title: 'Delete this receipt?', text: 'Its prices will no longer count in your price history.', confirmButtonText: 'Delete' }))) return

  await receiptStore.deleteReceipt(Number(props.id))
  toast.success('Receipt deleted.')
  router.push({ name: 'receipts' })
}

onMounted(async () => {
  receiptStore.receipt = null
  await receiptStore.fetchReceipt(props.id)

  if (receipt.value?.has_file) {
    fileUrl.value = await receiptStore.fileUrl(Number(props.id))
  }
})

onUnmounted(() => fileUrl.value && URL.revokeObjectURL(fileUrl.value))
</script>
