import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const account: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/account',
    name: 'account',
    component: () => import('@/views/account/Account.vue'),
  },
])

export default account
