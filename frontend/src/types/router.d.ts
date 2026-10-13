import type { RouteLocationNormalized } from 'vue-router'
import type { TemplateKeys } from '@/templates'

declare module 'vue-router' {
  interface RouteMeta {
    /** Layout used by App.vue. */
    template?: TemplateKeys
    /** Set false to skip the account-state middleware. */
    middleware?: boolean
    /** Requires a logged in user (default true). */
    restricted?: boolean
    /** Only for guests; logged in users are redirected to the dashboard. */
    guest?: boolean
    /** Role names allowed to access the route. */
    roles?: string[]
    /** Ability (or abilities, any of) required to access the route. */
    authorize?:
      | string
      | string[]
      | ((context: {
          can: (ability: string) => boolean
          to: RouteLocationNormalized
          from: RouteLocationNormalized
        }) => boolean)
  }
}

export {}
