import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const shoppingLists: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/shopping-lists',
    name: 'shopping-lists',
    component: () => import('@/views/shopping-lists/ShoppingLists.vue'),
  },
  {
    path: '/shopping-lists/:id',
    name: 'shopping-lists.details',
    props: true,
    component: () => import('@/views/shopping-lists/ShoppingListDetails.vue'),
  },
])

export default shoppingLists
