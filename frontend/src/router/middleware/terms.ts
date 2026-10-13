import { useIsLoggedIn } from '@/composables/use-is-logged-in'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import type { RouteLocationNormalized } from 'vue-router'

/**
 * Users who haven't accepted the terms (e.g. created via social login) must do so first.
 */
export default (to: RouteLocationNormalized, from: RouteLocationNormalized) => {
  const { isLoggedIn } = useIsLoggedIn()

  if (!isLoggedIn.value) {
    return true
  }

  const { loggedInUser } = useLoggedInUser()

  if (loggedInUser.value.terms_accepted === false && to.name !== 'accept-terms') {
    return {
      name: 'accept-terms',
      replace: true,
      query: from.query.redirect ? { redirect: from.query.redirect } : {},
    }
  }
}
