import { useIsLoggedIn } from '@/composables/use-is-logged-in'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import type { RouteLocationNormalized } from 'vue-router'

const SETUP_ROUTES = ['two-factor.setup', 'two-factor.setup.email', 'two-factor.setup.sms']

/**
 * Make sure the user has made (and finished) their 2FA choice.
 */
export default (to: RouteLocationNormalized) => {
  const { isLoggedIn } = useIsLoggedIn()

  if (!isLoggedIn.value) {
    return true
  }

  const { loggedInUser } = useLoggedInUser()
  const user = loggedInUser.value

  // Hasn't opted in or out yet, or opted in without choosing a method.
  if (user.two_factor_required === null || (user.two_factor_required && user.two_factor_method_preferred === null)) {
    return to.name === 'two-factor.setup' ? true : { name: 'two-factor.setup', replace: true }
  }

  // Chose a (new) method but hasn't verified a code for it yet.
  if (user.two_factor_required && user.two_factor_method_preferred !== user.two_factor_method_enabled) {
    const name = `two-factor.setup.${user.two_factor_method_preferred}`

    // Allow going back to the method picker.
    return to.name === name || to.name === 'two-factor.setup' ? true : { name, replace: true }
  }

  // Setup is complete: setup pages are only reachable from account settings (with ?change=1).
  if (SETUP_ROUTES.includes(String(to.name)) && to.query.change === undefined) {
    return { name: 'dashboard', replace: true }
  }
}
