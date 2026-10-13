<template>
  <AuthCard :title="sent ? 'Password reset sent' : 'Forgot password?'">
    <p v-if="sent" class="text-sm text-slate-600">
      An email has been sent to <strong>{{ sent }}</strong> with instructions for resetting your password.
    </p>

    <form v-else novalidate @submit="onSubmit">
      <p class="mb-4 text-sm text-slate-600">
        Enter your email address and we'll send you a link to reset your password.
      </p>
      <HTBFormField name="email" label="Email" type="email" />

      <div class="mt-6 flex items-center justify-between gap-4">
        <RouterLink :to="{ name: 'login' }" class="link-muted">Back to sign in</RouterLink>
        <button type="submit" class="btn btn-primary">Send reset link</button>
      </div>
    </form>
  </AuthCard>
</template>

<script lang="ts" setup>
import AuthCard from '@/components/auth/AuthCard.vue'
import { ref } from 'vue'
import { useForm, useField } from 'vee-validate'
import { useAppStore } from '@/stores/app'
import HTBFormField from '@/components/HTBFormField.vue'
import { useFocus } from '@vueuse/core'
import { isAxiosError } from 'axios'
import * as yup from 'yup'
const sent = ref()
const input = ref()

const { emailForgotPassword } = useAppStore()

// Define validation schema
const schema = yup.object({
  email: yup.string().email('The email must be valid.').required('The email field is required.'),
})

const { handleSubmit, setErrors } = useForm({
  validationSchema: schema,
  initialValues: {
    email: ''
  }
})
const { value: email } = useField('email')

const onSubmit = handleSubmit(async () => {
  try {
    const payload = {
      email: email.value
    }
    await emailForgotPassword(payload)
    sent.value = payload.email
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    }
    throw error
  }
})
</script>
