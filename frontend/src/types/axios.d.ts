import 'axios'

declare module 'axios' {
  export interface AxiosRequestConfig {
    /** The request that fetches the CSRF cookie. */
    csrfPreflight?: boolean
    /** The request that fetches the logged in user (router guards handle its errors). */
    userPreflight?: boolean
    /** Let the caller handle a 403 instead of redirecting to the dashboard. */
    throwForbiddens?: boolean
    /** Internal: request already retried after a CSRF refresh. */
    __retried?: boolean
  }
}

export {}
