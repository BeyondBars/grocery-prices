import { useAppStore } from '@/stores/app'
import { useForm } from 'vee-validate'
import { useRouter, useRoute } from 'vue-router'
import * as yup from 'yup'
import { useToast } from 'vue-toast-notification'
import { isAxiosError } from 'axios'
import type { TwoFactorMethod } from '@/types/user'

/**
 * Form logic shared by the email and SMS 2FA setup screens.
 */
export function useTwoFactorSetup(method: TwoFactorMethod) {
  const toast = useToast()
  const router = useRouter()
  const route = useRoute()
  const appStore = useAppStore()

  const { handleSubmit, setErrors, setFieldValue, isSubmitting } = useForm({
    validationSchema: yup.object({
      code: yup.string().trim().required('Enter the code we sent you.'),
    }),
    initialValues: { code: '' },
  })

  const verify = handleSubmit(async ({ code }) => {
    try {
      await appStore.twoFactorVerify({ code, method })
      toast.success('2-step verification is now enabled.')
      router.replace((route.query.redirect as string) || { name: 'dashboard' })
    } catch (error) {
      if (isAxiosError(error) && error.response?.data?.errors) {
        setErrors(error.response.data.errors)
        return
      }
      throw error
    }
  })

  /** Switch to the other method (email <-> sms). */
  const changeMethod = async () => {
    const other: TwoFactorMethod = method === 'email' ? 'sms' : 'email'

    await appStore.updateTwoFactor({ two_factor_required: true, two_factor_method_preferred: other })
    router.replace({ name: `two-factor.setup.${other}` })
  }

  return { verify, changeMethod, setFieldValue, isSubmitting }
}
