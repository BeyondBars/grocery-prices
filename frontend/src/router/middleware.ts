import type { Router, RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
import twoFactorChallenge from './middleware/two-factor-challenge'
import verified from './middleware/verified'
import terms from './middleware/terms'
import twoFactor from './middleware/two-factor'

type Middleware = (
  to: RouteLocationNormalized,
  from: RouteLocationNormalized
) => RouteLocationRaw | boolean | undefined

/**
 * Account state middleware for logged in users, run in order of priority:
 *  1. pass the 2FA login challenge
 *  2. verify their email
 *  3. accept the terms
 *  4. finish setting up 2FA
 */
const middlewares: Middleware[] = [twoFactorChallenge, verified, terms, twoFactor]

export default (router: Router) =>
  router.beforeEach((to, from) => {
    // Routes can opt out (e.g. logout).
    if (to.meta.middleware === false) {
      return true
    }

    for (const middleware of middlewares) {
      const response = middleware(to, from)

      if (response === undefined || response === true) {
        continue
      }

      return response
    }

    return true
  })
