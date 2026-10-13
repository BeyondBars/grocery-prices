import axios, { isAxiosError } from 'axios'
import { useAppStore } from '@/stores/app'

/**
 * Axios instance for the Laravel API (Sanctum SPA cookie auth).
 *
 * `withCredentials` sends the session cookie; `withXSRFToken` copies the XSRF-TOKEN cookie into
 * the X-XSRF-TOKEN header so Laravel's CSRF protection passes.
 */
export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Routes where a 401 is expected and must not bounce the user to the login page.
const PUBLIC_ROUTES = ['login', 'register', 'verify.signature', 'verification', 'forgot-password', 'set-password']

export default {
  install: (app, { router }) => {
    http.interceptors.request.use(async (config) => {
      const appStore = useAppStore()
      const method = config.method?.toLowerCase()

      // Fetch the CSRF cookie once before the first state-changing request.
      if (['post', 'put', 'patch', 'delete'].includes(method) && !appStore.csrf && !config.csrfPreflight) {
        await appStore.csrfPreflight()
      }

      if (['post', 'put', 'patch'].includes(method) && config.data === undefined) {
        config.data = {}
      }

      return config
    })

    http.interceptors.response.use(
      (response) => response,
      async (error) => {
        if (!isAxiosError(error) || !error.response) {
          return Promise.reject(error)
        }

        const { status, data } = error.response
        const route = router.currentRoute.value
        const appStore = useAppStore()

        // Blob downloads: parse the JSON error body so callers can read the message.
        if (error.response.request?.responseType === 'blob' && data instanceof Blob) {
          try {
            error.response.data = JSON.parse(await data.text())
          } catch {
            // Leave the original data as is.
          }
        }

        // The "who am I" call drives the router guards itself, so don't redirect from here.
        if (error.config?.userPreflight) {
          return Promise.reject(error)
        }

        // Session expired / not logged in.
        if (status === 401) {
          appStore.user = null

          if (!PUBLIC_ROUTES.includes(route.name)) {
            router.replace({ name: 'login', query: { redirect: route.fullPath } })
          }

          return Promise.reject(error)
        }

        // CSRF token mismatch (e.g. session rotated): refresh the cookie and retry once.
        if (status === 419 && !error.config.__retried) {
          appStore.csrf = false
          await appStore.csrfPreflight()

          return http.request({ ...error.config, __retried: true })
        }

        // Logged in but the 2FA challenge hasn't been completed this session.
        if (status === 403 && data?.code === 'two_factor_required') {
          if (route.name !== 'two-factor') {
            router.replace({ name: 'two-factor', query: { redirect: route.fullPath } })
          }

          return Promise.reject(error)
        }

        if (status === 403 && !error.config?.throwForbiddens && route.name !== 'dashboard') {
          router.replace({ name: 'dashboard' })
        }

        return Promise.reject(error)
      }
    )
  },
}
