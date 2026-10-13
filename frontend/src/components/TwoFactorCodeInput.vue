<script setup lang="ts">
import { useField } from 'vee-validate'

/**
 * 6-digit code input bound to the surrounding vee-validate form's "code" field.
 * Strips spaces (codes are emailed as "123 456") and works with OS one-time-code autofill.
 */
const props = withDefaults(defineProps<{ name?: string; label?: string }>(), {
  name: 'code',
  label: 'Verification code',
})

const { value, errorMessage, handleBlur } = useField<string>(() => props.name)

function onInput(event: Event) {
  value.value = (event.target as HTMLInputElement).value.replace(/\s+/g, '')
}
</script>

<template>
  <div class="mb-4">
    <label class="form-label" :for="name">{{ label }}</label>
    <input
      :id="name"
      :name="name"
      :value="value"
      class="form-input text-center text-lg tracking-[0.4em]"
      :class="{ 'is-invalid': errorMessage }"
      type="text"
      inputmode="numeric"
      autocomplete="one-time-code"
      maxlength="7"
      placeholder="123456"
      autofocus
      @input="onInput"
      @blur="handleBlur"
    />
    <span v-if="errorMessage" class="form-error">{{ errorMessage }}</span>
  </div>
</template>
