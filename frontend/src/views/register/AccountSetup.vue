<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
/* 1️⃣ 📦 Imports */
import EmailVerification from './EmailVerification.vue'
import { isAxiosError } from 'axios'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useForm, useField } from 'vee-validate'
import { useAppStore } from '@/stores/app'
import { ref, watch, computed } from 'vue'
import * as yup from 'yup'
import { useValidation } from '@/utils/useValidation'
import HTBFormField from '@/components/HTBFormField.vue'
import { useRoute, useRouter } from 'vue-router'

/* 2️⃣ 🔗 Refs & Reactive Variables */
const termsAndConditionsUrl = ref('https://example.com/terms')
const appStore = useAppStore()
const route = useRoute()
const router = useRouter()
const notificationSent = ref(false)

/* 3️⃣ 📜 Validation Schema */
const schema = yup.object({
  email: yup.string().email('The email must be valid.').required('The email field is required.')
})

/* 4️⃣ 📝 Form Handling */
const { handleSubmit, resetForm, setErrors } = useForm({
  validationSchema: schema,
  initialValues: {
    email: null, // Set initial email value
    agree_terms: false // Checkbox default to unchecked
  }
})

/* 5️⃣ 👀 Watchers & Computed */
const { value: email, errorMessage: emailError } = useField('email')

const { verify } = useAppStore()
const onSubmit = handleSubmit(async (payload) => {
  try {
    await verify(payload)
    notificationSent.value = true
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      if (error.response.status === 429) return // No need to throw for rate limit errors
    }
    throw error
  }
})
</script>

<template>
  <AuthCard title="Sign up">
    <form v-if="!notificationSent" novalidate @submit.prevent="onSubmit">
      <HTBFormField name="email" label="Email address" type="email" />

      <div class="mt-6 flex items-center justify-between gap-4">
        <RouterLink :to="{ name: 'login' }" class="link-muted">Back to sign in</RouterLink>
        <button type="submit" class="btn btn-primary">Next</button>
      </div>
    </form>
    <EmailVerification v-else :email="email" />
  </AuthCard>
</template>

<style scoped>
@media (min-width: 1024px) {
  .about {
    min-height: 100vh;
    display: flex;
    align-items: center;
  }
}
</style>
