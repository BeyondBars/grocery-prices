<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import AppModal from '@/components/app/AppModal.vue'
import { useCatalogStore } from '@/stores/catalog'
import { errorMessage, validationErrors } from '@/utils/api-errors'
import type { StoreResource } from '@/types/catalog'

/** Add or edit a store. Aliases are typed comma separated: "WOOLWORTHS, WOOLIES". */
const props = defineProps<{ store?: StoreResource | null }>()
const emit = defineEmits<{ close: []; saved: [store: StoreResource] }>()

const catalog = useCatalogStore()
const toast = useToast()

const name = ref(props.store?.name ?? '')
const abn = ref(props.store?.abn ?? '')
const aliases = ref(props.store?.aliases.join(', ') ?? '')
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

const save = async () => {
  isSaving.value = true
  errors.value = {}

  try {
    const store = await catalog.saveStore(
      { name: name.value, abn: abn.value || null, aliases: aliases.value.split(',') },
      props.store?.id
    )
    toast.success(props.store ? 'Store updated.' : 'Store added.')
    emit('saved', store)
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <AppModal :title="store ? 'Edit store' : 'Add store'" @close="emit('close')">
    <form id="store-form" class="space-y-4" @submit.prevent="save">
      <div>
        <label class="form-label" for="store-name">Name</label>
        <input id="store-name" v-model="name" class="form-input" :class="{ 'is-invalid': errors.name }" placeholder="Woolworths" required />
        <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
      </div>
      <div>
        <label class="form-label" for="store-abn">ABN <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="store-abn" v-model="abn" class="form-input" :class="{ 'is-invalid': errors.abn }" inputmode="numeric" placeholder="11 digits, as printed on receipts" />
        <span v-if="errors.abn" class="form-error">{{ errors.abn }}</span>
      </div>
      <div>
        <label class="form-label" for="store-aliases">Names on receipts</label>
        <input id="store-aliases" v-model="aliases" class="form-input" :class="{ 'is-invalid': errors.aliases }" placeholder="WOOLWORTHS, WOOLIES METRO" />
        <p class="mt-1 text-xs text-slate-500">Comma separated. Used to recognise the store when a receipt is read.</p>
        <span v-if="errors.aliases" class="form-error">{{ errors.aliases }}</span>
      </div>
    </form>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="emit('close')">Cancel</button>
      <button type="submit" form="store-form" class="btn btn-primary" :disabled="isSaving">{{ isSaving ? 'Saving…' : 'Save' }}</button>
    </template>
  </AppModal>
</template>
