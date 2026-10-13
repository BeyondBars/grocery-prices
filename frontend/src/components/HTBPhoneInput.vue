<script>
import { useField } from 'vee-validate'
import { computed, ref, watch } from 'vue'
import { IMaskComponent } from 'vue-imask'

export default {
  name: 'HTBPhoneInput',
  components: {
    IMaskComponent // Register the component here
  },
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
    const maskRef = ref(null)
    // Compute the classes dynamically based on validation state
    const fieldClasses = computed(() => ({
      'is-invalid': errorMessage.value,  // Red border on error
      'is-valid': !errorMessage.value && value.value, // Green border when valid
      [props.className]: props.className, // Allow custom classes
    }))
    const phoneMask = computed(() => {
      return '0000 000 000'
    })
    watch(value, () => {
      if (maskRef.value && maskRef.value.maskRef) {
        maskRef.value.maskRef.updateValue()
      }
    })
    return {
      fieldClasses,
      value,
      errorMessage,
      handleBlur,
      handleChange,
      phoneMask,
      maskRef
    }
  }
}
</script>

<template>
  <div class="mb-4">
    <label v-if="label" class="form-label" :for="name">{{ label }}</label>
    <IMaskComponent
      :id="name"
      ref="maskRef"
      class="form-input"
      :class="[{ 'is-invalid': errorMessage }, className]"
      :mask="phoneMask"
      :unmask="true"
      :value="value"
      :type="type"
      :placeholder="placeholder"
      @input="handleChange"
      @blur="handleBlur"
    />
    <span v-if="errorMessage" class="form-error">{{ errorMessage }}</span>
  </div>
</template>
