<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toast-notification'
import { useCatalogStore } from '@/stores/catalog'
import { useReceiptStore } from '@/stores/receipts'
import { errorMessage, validationErrors } from '@/utils/api-errors'
import { toCents } from '@/utils/money'
import type { ReceiptResource } from '@/types/receipt'

/** Store, date, receipt number and total as read from the receipt, for the user to correct. */
const props = defineProps<{ receipt: ReceiptResource }>()

const catalog = useCatalogStore()
const receiptStore = useReceiptStore()
const toast = useToast()

const form = ref(fromReceipt())
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

function fromReceipt() {
  return {
    store_id: props.receipt.store?.id ?? null,
    purchased_at: props.receipt.purchased_at?.replace(' ', 'T') ?? '',
    receipt_number: props.receipt.receipt_number ?? '',
    total: props.receipt.total_cents === null ? '' : (props.receipt.total_cents / 100).toFixed(2),
  }
}

watch(() => props.receipt, () => (form.value = fromReceipt()))

const save = async () => {
  isSaving.value = true
  errors.value = {}

  try {
    await receiptStore.updateReceipt(props.receipt.id, {
      store_id: form.value.store_id,
      purchased_at: form.value.purchased_at || null,
      receipt_number: form.value.receipt_number || null,
      total_cents: toCents(form.value.total),
    })
    toast.success('Receipt details saved.')
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    isSaving.value = false
  }
}

onMounted(() => catalog.stores.length || catalog.fetchStores())
</script>

<template>
  <form class="card grid gap-4 p-4 sm:grid-cols-2" @submit.prevent="save">
    <div>
      <label class="form-label" for="receipt-store">Store</label>
      <select id="receipt-store" v-model="form.store_id" class="form-input" :class="{ 'is-invalid': !form.store_id }">
        <option :value="null" disabled>Choose the store…</option>
        <option v-for="store in catalog.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
      </select>
      <span v-if="errors.store_id" class="form-error">{{ errors.store_id }}</span>
    </div>
    <div>
      <label class="form-label" for="receipt-date">Date and time</label>
      <input id="receipt-date" v-model="form.purchased_at" type="datetime-local" class="form-input" :class="{ 'is-invalid': errors.purchased_at || !form.purchased_at }" />
      <span v-if="errors.purchased_at" class="form-error">{{ errors.purchased_at }}</span>
    </div>
    <div>
      <label class="form-label" for="receipt-number">Receipt number</label>
      <input id="receipt-number" v-model="form.receipt_number" class="form-input" placeholder="Optional" />
    </div>
    <div>
      <label class="form-label" for="receipt-total">Total paid</label>
      <input id="receipt-total" v-model="form.total" class="form-input tabular-nums" inputmode="decimal" placeholder="0.00" />
      <span v-if="errors.total_cents" class="form-error">{{ errors.total_cents }}</span>
    </div>
    <div class="flex justify-end sm:col-span-2">
      <button type="submit" class="btn btn-primary" :disabled="isSaving">{{ isSaving ? 'Saving…' : 'Save details' }}</button>
    </div>
  </form>
</template>
