import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const catalog: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/products',
    name: 'products',
    component: () => import('@/views/products/Products.vue'),
  },
  {
    path: '/products/:id',
    name: 'products.prices',
    props: true,
    component: () => import('@/views/products/ProductPrices.vue'),
  },
  {
    path: '/stores',
    name: 'stores',
    component: () => import('@/views/stores/Stores.vue'),
  },
])

export default catalog
