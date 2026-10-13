import { useAppStore } from '@/stores/app'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toast-notification'

export function useLogout() {
  const router = useRouter()
  const app = useAppStore()
  const toast = useToast()

  const logout = async () => {
    await app.logout()

    toast.success('You have successfully logged out.')
    router.replace({ name: 'login' })
  }

  return {
    logout,
  }
}
