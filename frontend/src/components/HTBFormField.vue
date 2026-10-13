<script>
import { useField } from 'vee-validate'
import { computed } from 'vue'

export default {
  name: 'HTBFormField',
  props: {
    name: { type: String, required: true },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },  // Default type is 'text'
    formGroup: { type: Boolean, default: true },
    className: { type: String, default: '' },
    placeholder: { type: String, default: '' }
  },
  setup(props) {
    // Get field data from VeeValidate
    const { value, errorMessage, handleBlur, handleChange } = useField(props.name)

    // Compute the classes dynamically based on validation state
    const fieldClasses = computed(() => ({
      'is-invalid': errorMessage.value,  // Red border on error
      'is-valid': !errorMessage.value && value.value, // Green border when valid
      [props.className]: props.className, // Allow custom classes
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
    <label v-if="label" class="form-label" :for="name">{{ label }}</label>
    <input
      :id="name"
      :name="name"
      class="form-input"
      :class="[{ 'is-invalid': errorMessage }, className]"
      :value="value"
      :type="type"
      :placeholder="placeholder"
      @input="handleChange"
      @blur="handleBlur"
    />
    <span v-if="errorMessage" class="form-error">{{ errorMessage }}</span>
  </div>
</template>
