import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const settings: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/settings/Settings.vue'),
  },
])

export default settings
