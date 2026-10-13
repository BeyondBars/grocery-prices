import { useAppStore } from '@/stores/app'
import type { Router } from 'vue-router'

/**
 * Resolves the logged in user once per page load and keeps guests out of restricted routes.
 */
export default (router: Router) =>
  router.beforeEach(async (to) => {
    const appStore = useAppStore()

    if (appStore.user === null && !appStore.authenticationAttempted) {
      try {
        await appStore.getUser()
      } catch {
        // Not logged in - handled below.
      }
    }

    if (appStore.user === null) {
      return to.meta.restricted === false ? true : { name: 'login', replace: true, query: { redirect: to.fullPath } }
    }

    // Logged in users don't need guest pages such as login/register.
    if (to.meta.guest === true) {
      return { name: 'dashboard', replace: true }
    }

    return true
  })
