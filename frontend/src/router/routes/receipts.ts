import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const receipts: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/receipts',
    name: 'receipts',
    component: () => import('@/views/receipts/Receipts.vue'),
  },
  {
    path: '/receipts/:id',
    name: 'receipts.details',
    props: true,
    component: () => import('@/views/receipts/ReceiptDetails.vue'),
  },
])

export default receipts
