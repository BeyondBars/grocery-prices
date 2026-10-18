<script setup lang="ts">
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

/** Drop or pick receipt photos/PDFs. Emits the valid files and reports the rest. */
const props = defineProps<{ busy?: boolean }>()
const emit = defineEmits<{ files: [files: File[]]; rejected: [message: string] }>()

const MAX_FILES = 10
const MAX_BYTES = 10 * 1024 * 1024
const TYPES = ['image/jpeg', 'image/png', 'application/pdf']

const input = ref<HTMLInputElement | null>(null)
const isOver = ref(false)

const take = (fileList: FileList | null) => {
  const files = Array.from(fileList ?? [])
  const valid = files.filter((file) => TYPES.includes(file.type) && file.size <= MAX_BYTES)

  if (valid.length < files.length) emit('rejected', 'Some files were skipped: receipts must be JPG, PNG or PDF and under 10 MB.')
  if (valid.length > MAX_FILES) emit('rejected', `Only the first ${MAX_FILES} receipts were uploaded. Add the rest next.`)
  if (valid.length) emit('files', valid.slice(0, MAX_FILES))
  if (input.value) input.value.value = ''
}

const onDrop = (event: DragEvent) => {
  isOver.value = false
  if (!props.busy) take(event.dataTransfer?.files ?? null)
}
</script>

<template>
  <label
    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition"
    :class="[isOver ? 'border-brand-500 bg-brand-50' : 'border-slate-300 bg-white hover:border-brand-500', busy && 'pointer-events-none opacity-60']"
    @dragover.prevent="isOver = true"
    @dragleave="isOver = false"
    @drop.prevent="onDrop"
  >
    <span class="flex size-12 items-center justify-center rounded-full bg-brand-50 text-xl text-brand-600">
      <FontAwesomeIcon :icon="busy ? 'spinner' : 'cloud-arrow-up'" :spin="busy" />
    </span>
    <span class="font-semibold text-slate-900">{{ busy ? 'Uploading…' : 'Upload receipts' }}</span>
    <span class="text-sm text-slate-500">Drop photos or PDFs here, or tap to choose. Up to 10 at a time.</span>
    <input ref="input" type="file" class="sr-only" multiple accept="image/jpeg,image/png,application/pdf" :disabled="busy" @change="take(($event.target as HTMLInputElement).files)" />
  </label>
</template>
