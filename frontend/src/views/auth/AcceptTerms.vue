<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useLogout } from '@/composables/use-logout'

const termsAndConditionsUrl = import.meta.env.VITE_TERMS_URL || '#'

const appStore = useAppStore()
const route = useRoute()
const router = useRouter()
const { logout } = useLogout()

const { handleSubmit, isSubmitting } = useForm({
  validationSchema: yup.object({
    agree_terms: yup.boolean().oneOf([true], 'You must accept the terms and conditions.'),
  }),
  initialValues: { agree_terms: false },
})

const { value: agreeTerms, errorMessage } = useField<boolean>('agree_terms')

const onSubmit = handleSubmit(async (payload) => {
  await appStore.acceptTerms(payload)
  router.replace((route.query.redirect as string) || { name: 'dashboard' })
})
</script>

<template>
  <AuthCard title="Terms and conditions">
    <form novalidate @submit="onSubmit">
      <p class="mb-4 text-sm text-slate-600">Please review and accept our terms to continue.</p>

      <label class="flex items-start gap-2 text-sm text-slate-700" for="agree_terms">
        <input id="agree_terms" v-model="agreeTerms" class="form-check mt-0.5" type="checkbox" />
        <span>
          I agree to the
          <a :href="termsAndConditionsUrl" target="_blank" rel="noopener" class="link">terms and conditions</a>
        </span>
      </label>
      <span v-if="errorMessage" class="form-error">{{ errorMessage }}</span>

      <div class="mt-6 flex items-center justify-between gap-4">
        <button type="button" class="link-muted" @click="logout">Sign out</button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">Continue</button>
      </div>
    </form>
  </AuthCard>
</template>
