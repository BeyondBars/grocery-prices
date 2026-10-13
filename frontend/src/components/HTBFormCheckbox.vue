<script>
import { useField } from 'vee-validate'
import { computed } from 'vue'

export default {
  name: 'HTBFormField',
  props: {
    name: { type: String, required: true },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' }, // Default is 'text'
    formGroup: { type: Boolean, default: true },
    className: { type: String, default: '' },
    placeholder: { type: String, default: '' }
  },
  setup(props) {
    // Get field data from VeeValidate
    const { value, errorMessage, handleBlur, handleChange } = useField(props.name)

    // Compute classes dynamically
    const fieldClasses = computed(() => ({
      'is-invalid': errorMessage.value, // Red border if invalid
      'is-valid': !errorMessage.value && value.value, // Green border if valid
      [props.className]: props.className // Custom class support
    }))

    return {
      fieldClasses,
      value,
      errorMessage,
      handleBlur,
      handleChange
    }
  }
}
</script>

<template>
  <div class="mb-4">
    <label class="flex items-start gap-2 text-sm text-slate-700">
      <input v-model="value" class="form-check mt-0.5" type="checkbox" @blur="handleBlur" />
      <span><slot>{{ label }}</slot></span>
    </label>
    <span v-if="errorMessage" class="form-error">{{ errorMessage }}</span>
  </div>
</template>
