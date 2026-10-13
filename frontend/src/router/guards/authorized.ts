import { useIsLoggedIn } from '@/composables/use-is-logged-in'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import { useToast } from 'vue-toast-notification'
import type { Router } from 'vue-router'

/**
 * Checks a route's `meta.authorize` (ability string, list of abilities, or a callback).
 */
export default (router: Router) =>
  router.beforeEach((to, from) => {
    const { authorize, roles } = to.meta

    if (authorize === undefined && roles === undefined) {
      return true
    }

    const { isLoggedIn } = useIsLoggedIn()

    if (!isLoggedIn.value) {
      return false
    }

    const { can, loggedInUser } = useLoggedInUser()

    const roleAllowed = roles === undefined || roles.includes(loggedInUser.value.role?.name ?? '')

    const allowed =
      roleAllowed &&
      (authorize === undefined ||
        (typeof authorize === 'function' && authorize({ can, to, from })) ||
        (typeof authorize === 'string' && can(authorize)) ||
        (Array.isArray(authorize) && authorize.some((ability) => can(ability))))

    if (allowed) {
      return true
    }

    useToast().error('You are not authorised to access that page.')

    return from.name === undefined ? { name: 'dashboard' } : false
  })
