<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { isAxiosError } from 'axios'
import * as yup from 'yup'
import { useForm, useField } from 'vee-validate'
import { useRouter } from 'vue-router'
import { useLogout } from '@/composables/use-logout'
import { useAppStore } from '@/stores/app'
import type { TwoFactorMethod } from '@/types/user'

const router = useRouter()
const { logout } = useLogout()
const appStore = useAppStore()

const securityOptions: { value: TwoFactorMethod; label: string }[] = [
  { value: 'email', label: 'Email' },
  { value: 'sms', label: 'SMS' },
]

const { handleSubmit, setErrors, isSubmitting, errors } = useForm({
  validationSchema: yup.object({
    two_factor_required: yup.boolean().required(),
    two_factor_method_preferred: yup
      .string()
      .nullable()
      .when('two_factor_required', {
        is: true,
        then: (schema) => schema.required('Choose how you want to receive codes.'),
      }),
  }),
  initialValues: {
    two_factor_required: appStore.user?.two_factor_required ?? true,
    two_factor_method_preferred: appStore.user?.two_factor_method_preferred ?? 'email',
  },
})

const { value: twoFactorRequired } = useField<boolean>('two_factor_required')
const { value: twoFactorMethodPreferred } = useField<TwoFactorMethod | null>('two_factor_method_preferred')

const onSubmit = handleSubmit(async (values) => {
  const payload = {
    two_factor_required: values.two_factor_required,
    two_factor_method_preferred: values.two_factor_required ? values.two_factor_method_preferred : null,
  }

  try {
    const user = await appStore.updateTwoFactor(payload)

    // Opted in: verify a code for the chosen method. Opted out: carry on to the app.
    if (user.two_factor_required && user.two_factor_method_preferred) {
      router.replace({ name: `two-factor.setup.${user.two_factor_method_preferred}`, query: { change: '1' } })
    } else {
      router.replace({ name: 'dashboard' })
    }
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      return
    }
    throw error
  }
})
</script>

<template>
  <AuthCard title="Set up 2-step verification">
    <form novalidate @submit="onSubmit">
      <p class="mb-4 text-sm text-slate-600">
        Using a second authentication method helps keep your account secure.
      </p>

      <label class="mb-4 flex items-center gap-2 text-sm font-medium text-slate-700" for="two_factor_required">
        <input id="two_factor_required" v-model="twoFactorRequired" class="form-check" type="checkbox" />
        Use 2-step verification when signing in
      </label>

      <fieldset v-if="twoFactorRequired">
        <legend class="form-label">Send my codes by</legend>
        <div class="flex gap-6">
          <label
            v-for="option in securityOptions"
            :key="option.value"
            class="flex items-center gap-2 text-sm text-slate-700"
            :for="`method-${option.value}`"
          >
            <input
              :id="`method-${option.value}`"
              v-model="twoFactorMethodPreferred"
              class="form-check"
              type="radio"
              :value="option.value"
            />
            {{ option.label }}
          </label>
        </div>
        <span v-if="errors.two_factor_method_preferred" class="form-error">
          {{ errors.two_factor_method_preferred }}
        </span>
      </fieldset>

      <div class="mt-6 flex items-center justify-between gap-4">
        <button type="button" class="link-muted" @click="logout">Sign out</button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">Next</button>
      </div>
    </form>
  </AuthCard>
</template>
