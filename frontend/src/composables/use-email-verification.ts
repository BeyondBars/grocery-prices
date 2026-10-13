import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { useAppStore } from '@/stores/app'
import { useIsLoggedIn } from './use-is-logged-in'

export function useEmailVerification() {
  const verifying = ref(false)
  const success = ref(false)
  const failed = ref(false)
  const errorMessage = ref<string>()
  const appStore = useAppStore()
  const { isLoggedIn } = useIsLoggedIn()

  const verify = async (payload: { signature: string; expires: string; identifier: string }) => {
    verifying.value = true

    try {
      await appStore.emailVerification(payload)

      // Refresh the logged in user (if any) so guards see the verified email.
      if (isLoggedIn.value) {
        await appStore.getUser()
      }

      success.value = true
    } catch (error) {
      failed.value = true

      if (isAxiosError(error) && error.response) {
        errorMessage.value = error.response.data.message
      }
    } finally {
      verifying.value = false
    }
  }

  return { verify, verifying, failed, success, errorMessage }
}
