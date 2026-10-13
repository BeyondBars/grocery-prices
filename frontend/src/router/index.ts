import { createRouter, createWebHistory } from 'vue-router'
import routes from './routes'
import { routes as createRoutes } from '@/utils/route'
import authenticated from './guards/authenticated'
import authorized from './guards/authorized'
import middleware from './middleware'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    ...routes,
    ...createRoutes({ template: 'error', restricted: false }, [
      {
        path: '/:invalid(.*)*',
        name: 'error.not-found',
        component: () => import('@/views/ErrorNotFound.vue'),
      },
    ]),
  ],
})

// Guards run in registration order:
// 1. resolve the logged in user and keep guests out of restricted pages,
// 2. account-state middleware (2FA challenge, email verification, terms, 2FA setup),
// 3. ability/role checks for the requested page.
authenticated(router)
middleware(router)
authorized(router)

export default router
