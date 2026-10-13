import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const register: RouteRecordRaw[] = createRoutes({ template: 'auth', restricted: false, guest: true }, [
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/register/AccountSetup.vue'),
  },
  {
    path: '/verify/:signature',
    name: 'verify.signature',
    props: true,
    component: () => import('@/views/register/AccountSetupAfterVerification.vue'),
  },
])

export default register
