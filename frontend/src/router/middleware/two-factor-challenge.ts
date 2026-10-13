import { useIsLoggedIn } from '@/composables/use-is-logged-in'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import type { RouteLocationNormalized } from 'vue-router'

/**
 * Users with 2FA enabled must enter a code after logging in before anything else.
 */
export default (to: RouteLocationNormalized) => {
  const { isLoggedIn } = useIsLoggedIn()

  if (!isLoggedIn.value) {
    return true
  }

  const { loggedInUser } = useLoggedInUser()
  const user = loggedInUser.value

  const challengePending =
    user.two_factor_required === true && user.two_factor_method_enabled !== null && user.two_factor_verified === false

  if (!challengePending) {
    return to.name === 'two-factor' ? { name: 'dashboard', replace: true } : true
  }

  if (to.name === 'two-factor') {
    return true
  }

  return { name: 'two-factor', replace: true, query: { redirect: to.fullPath } }
}
