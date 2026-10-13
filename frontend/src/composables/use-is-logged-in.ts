import { useAppStore } from '@/stores/app'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'

export function useIsLoggedIn() {
  const appStore = useAppStore()
  const { user } = storeToRefs(appStore)

  return {
    isLoggedIn: computed(() => user.value !== null),
    getLoggedInUser: appStore.getUser,
  }
}
