<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import * as yup from 'yup'
import { isAxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toast-notification'
import { useAppStore } from '@/stores/app'
import { useLogout } from '@/composables/use-logout'
import TwoFactorCodeInput from '@/components/TwoFactorCodeInput.vue'

const appStore = useAppStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { logout } = useLogout()

// The API sends a code automatically on login, so we only send one here on request.
const method = computed(() => appStore.user?.two_factor_method_enabled ?? 'email')
const destination = computed(() => (method.value === 'sms' ? 'your mobile' : 'your email address'))
const resending = ref(false)

const { handleSubmit, setErrors, isSubmitting } = useForm({
  validationSchema: yup.object({
    code: yup.string().trim().required('Enter the code we sent you.'),
  }),
  initialValues: { code: '' },
})

const onSubmit = handleSubmit(async ({ code }) => {
  try {
    await appStore.twoFactorVerify({ code, login: true })
    router.replace((route.query.redirect as string) || { name: 'dashboard' })
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 429) {
      setErrors({ code: 'Too many attempts. Please wait a minute and try again.' })
      return
    }
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      return
    }
    throw error
  }
})

async function resend() {
  resending.value = true

  try {
    await appStore.sendTwoFactorCode(method.value)
    toast.success(`A new code has been sent to ${destination.value}.`)
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 429) {
      toast.error('Please wait a minute before requesting another code.')
      return
    }
    throw error
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <AuthCard title="2-step verification">
    <form novalidate @submit="onSubmit">
      <p class="mb-4 text-sm text-slate-600">Enter the 6-digit code we sent to {{ destination }}.</p>

      <TwoFactorCodeInput />

      <p class="text-sm text-slate-600">
        Code hasn't arrived?
        <button type="button" class="link" :disabled="resending" @click="resend">Resend code</button>
      </p>

      <div class="mt-6 flex items-center justify-between gap-4">
        <button type="button" class="link-muted" @click="logout">Sign out</button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">Verify code</button>
      </div>
    </form>
  </AuthCard>
</template>
