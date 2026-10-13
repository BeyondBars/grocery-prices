import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const dashboard: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/dashboard/Dashboard.vue'),
  },
])

export default dashboard
