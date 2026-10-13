<template>
  <AuthCard title="Account setup">
    <form novalidate @submit="onSubmit">
      <div class="grid gap-x-4 sm:grid-cols-2">
        <HTBFormField name="first_name" label="First name" type="text" />
        <HTBFormField name="last_name" label="Last name" type="text" />
      </div>
      <HTBPhoneInput name="mobile" label="Mobile" type="text" />
      <HTBFormField name="password" label="Password" type="password" />
      <HTBFormField name="password_confirmation" label="Confirm password" type="password" />
      <PasswordStrengthBar class="mb-4" :password="password" label="Password strength" />

      <HTBFormCheckbox name="agree_terms" type="checkbox">
        I agree to the
        <a class="link" href="#" target="_blank" rel="noopener">privacy policy</a>
        and
        <a class="link" :href="termsAndConditionsUrl" target="_blank" rel="noopener">terms and conditions</a> of use.
      </HTBFormCheckbox>

      <div class="mt-6 flex items-center justify-between gap-4">
        <RouterLink :to="{ name: 'login' }" class="link-muted">Back to sign in</RouterLink>
        <button type="submit" class="btn btn-primary" :disabled="!verified || isSubmitting">Create account</button>
      </div>
    </form>
  </AuthCard>
</template>
<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toast-notification'
import { useAppStore } from '@/stores/app'
import HTBFormField from '@/components/HTBFormField.vue'
import HTBPhoneInput from '@/components/HTBPhoneInput.vue'
import HTBFormCheckbox from '@/components/HTBFormCheckbox.vue'
import PasswordStrengthBar from '@/components/auth/PasswordStrengthBar.vue'

/**
 * /verify/:signature?email=...&expires=... - registration step 3. The signed link is validated on
 * load, then the account is created and the API logs the new user straight in.
 */
const props = defineProps<{ signature: string }>()

const toast = useToast()
const router = useRouter()
const route = useRoute()
const appStore = useAppStore()

const termsAndConditionsUrl = import.meta.env.VITE_TERMS_URL || '#'
const email = String(route.query.email ?? '')
const verified = ref(false)

const { handleSubmit, setErrors, isSubmitting } = useForm({
  validationSchema: yup.object({
    first_name: yup.string().required('The first name is required.'),
    last_name: yup.string().required('The last name is required.'),
    mobile: yup.string().nullable(),
    password: yup.string().required('The password is required.'),
    password_confirmation: yup
      .string()
      .required('Please confirm the password.')
      .oneOf([yup.ref('password')], 'The passwords do not match.'),
    agree_terms: yup.boolean().oneOf([true], 'You must accept the terms and conditions.'),
  }),
  initialValues: {
    first_name: '',
    last_name: '',
    mobile: '',
    password: '',
    password_confirmation: '',
    agree_terms: false,
  },
})

const { value: password } = useField<string>('password')

const verifyOnLoad = async () => {
  try {
    await appStore.verifySignature({
      signature: props.signature,
      expires: String(route.query.expires ?? ''),
      email,
    })
    verified.value = true
  } catch (error) {
    const message = isAxiosError(error) ? error.response?.data?.message : undefined
    toast.error(message || 'This sign up link is invalid or has expired.')
    router.replace({ name: 'login' })
  }
}
verifyOnLoad()

const onSubmit = handleSubmit(async (payload) => {
  try {
    await appStore.accountSetup({ ...payload, mobile: payload.mobile || null, email })
    toast.success('Account created successfully')

    // The user is now logged in; the router middleware takes care of any remaining setup steps.
    router.replace((route.query.redirect as string) || { name: 'dashboard' })
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      return
    }
    if (isAxiosError(error) && error.response?.data?.message) {
      toast.error(error.response.data.message)
      return
    }
    throw error
  }
})
</script>

