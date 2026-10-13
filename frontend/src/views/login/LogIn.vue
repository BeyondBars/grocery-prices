<template>
  <AuthCard title="Sign in">
    <form novalidate @submit="onSubmit">
      <HTBFormField name="email" label="Email address" type="email" />
      <HTBFormField name="password" label="Password" type="password" />

      <div class="mt-6 flex items-center justify-between gap-4">
        <RouterLink :to="{ name: 'forgot-password' }" class="link text-sm">Forgot password?</RouterLink>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">Sign in</button>
      </div>
    </form>

    <div class="my-6 flex items-center gap-3 text-xs text-slate-400">
      <span class="h-px flex-1 bg-slate-200" />or sign in with<span class="h-px flex-1 bg-slate-200" />
    </div>

    <div class="grid grid-cols-2 gap-3">
      <a :href="`${apiUrl}/auth/google`" class="btn btn-secondary">
        <FontAwesomeIcon :icon="['fab', 'google']" /> Google
      </a>
      <a :href="`${apiUrl}/auth/facebook`" class="btn btn-secondary">
        <FontAwesomeIcon :icon="['fab', 'facebook-f']" /> Facebook
      </a>
    </div>

    <p class="mt-6 text-center text-sm text-slate-600">
      Don't have an account?
      <RouterLink :to="{ name: 'register' }" class="link">Sign up</RouterLink>
    </p>
  </AuthCard>
</template>

<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { isAxiosError } from 'axios'
import { useForm } from 'vee-validate'
import * as yup from 'yup'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toast-notification'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useAppStore } from '@/stores/app'
import HTBFormField from '@/components/HTBFormField.vue'

const toast = useToast()
const appStore = useAppStore()
const route = useRoute()
const router = useRouter()

// Errors passed back from the social login callback (?error=...).
const SOCIAL_ERRORS: Record<string, string> = {
  social_failed: 'Social sign in failed. Please try again.',
  social_no_email: 'Your social account did not share an email address.',
  social_account_exists: 'An account with that email already exists. Sign in with your password instead.',
  account_disabled: 'This user account has been disabled.',
}

if (typeof route.query.error === 'string' && SOCIAL_ERRORS[route.query.error]) {
  toast.error(SOCIAL_ERRORS[route.query.error])
}

const apiUrl = import.meta.env.VITE_API_URL

const { handleSubmit, setErrors, isSubmitting } = useForm({
  validationSchema: yup.object({
    email: yup.string().email('The email must be valid.').required('The email field is required.'),
    password: yup.string().required('The password is required.'),
  }),
})

const onSubmit = handleSubmit(async (payload) => {
  try {
    await appStore.login(payload)

    // The router middleware sends the user to the 2FA challenge / setup screens when needed.
    router.replace((route.query.redirect as string) || { name: 'dashboard' })
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      return
    }
    throw error
  }
})
</script>

