<template>
  <AuthCard title="Set password">
    <form novalidate @submit="onSubmit">
      <HTBFormField name="password" label="Password" type="password" />
      <HTBFormField name="password_confirmation" label="Confirm password" type="password" />
      <PasswordStrengthBar :password="password" label="Password strength" />

      <div class="mt-6 flex justify-end">
        <button type="submit" class="btn btn-primary">Set password</button>
      </div>
    </form>
  </AuthCard>
</template>

<script lang="ts" setup>
import AuthCard from '@/components/auth/AuthCard.vue'
import { http } from '@/utils'
import HTBFormField from '@/components/HTBFormField.vue'
import PasswordStrengthBar from '@/components/auth/PasswordStrengthBar.vue'
import { useToast } from 'vue-toast-notification'
import { ref, watch, computed } from 'vue'
import * as yup from 'yup'
import { useValidation } from '@/utils/useValidation'
import { useForm, useField } from 'vee-validate'
import { useAppStore } from '@/stores/app'
import { useRouteQuery } from '@vueuse/router'
import { isAxiosError } from 'axios'
import { useRouter } from 'vue-router'

const toast = useToast()
const router = useRouter()
const props = defineProps<{
  token: string
}>()
const email = useRouteQuery<string | null>('email')

/* 3️⃣ 📜 Validation Schema */
const schema = yup.object({
  password: yup.string().required(),
  password_confirmation: yup.string().required()
})

/* 4️⃣ 📝 Form Handling */
const { handleSubmit, resetForm, setErrors } = useForm({
  validationSchema: schema,
  initialValues: {
    email: email.value,
    token: props.token,
    password: null,
    password_confirmation: null
  }
})
const { value: password } = useField<string>('password')

const { accountSetup, verifySignature } = useAppStore()
const { resetPassword } = useAppStore()

const onSubmit = handleSubmit(async (payload) => {
  try {
    await resetPassword(payload)
    toast.success('Password reset successfully')

    router.replace({ name: 'login' })
  } catch (error) {
    if (
      isAxiosError(error) &&
      error.response &&
      error.response.status === 422 &&
      error.response.data.errors.generic
    ) {
      toast.error(error.response.data.errors.generic[0])
      router.replace({ name: 'login' })
    }
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      if (error.response.status === 429) return // No need to throw for rate limit errors
    }
    throw error
  }
})
</script>
