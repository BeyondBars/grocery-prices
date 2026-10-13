import { useIsLoggedIn } from '@/composables/use-is-logged-in'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import type { RouteLocationNormalized } from 'vue-router'

/**
 * Users must verify their email before using the app.
 */
export default (to: RouteLocationNormalized) => {
  const { isLoggedIn } = useIsLoggedIn()

  if (!isLoggedIn.value) {
    return true
  }

  const { loggedInUser } = useLoggedInUser()

  if (loggedInUser.value.email_verified === false && to.name !== 'verification') {
    return { name: 'verification', replace: true }
  }
}
