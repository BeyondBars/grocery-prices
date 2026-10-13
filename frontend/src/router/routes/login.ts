import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

// Guest-only pages (logged in users are redirected to the dashboard).
const guestRoutes = createRoutes({ template: 'auth', restricted: false, guest: true }, [
  {
    path: '/',
    name: 'login',
    component: () => import('@/views/login/LogIn.vue'),
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPassword.vue'),
  },
  {
    path: '/set-password/:token',
    name: 'set-password',
    props: true,
    component: () => import('@/views/auth/SetPassword.vue'),
  },
])

// Pages reached from an email link: work whether or not the user is logged in.
const publicRoutes = createRoutes({ template: 'auth', restricted: false, guest: false }, [
  {
    path: '/verification/:signature?',
    name: 'verification',
    props: true,
    component: () => import('@/views/auth/AccountVerification.vue'),
  },
])

// Account-state pages for logged in users (2FA, terms).
const accountStateRoutes = createRoutes({ template: 'auth', restricted: true, guest: false }, [
  {
    path: '/two-factor',
    name: 'two-factor',
    component: () => import('@/views/login/TwoFactorAuthVerification.vue'),
  },
  {
    path: '/two-factor/setup',
    name: 'two-factor.setup',
    component: () => import('@/views/auth/TwoFactorSetup.vue'),
  },
  {
    path: '/two-factor/setup/email',
    name: 'two-factor.setup.email',
    component: () => import('@/views/auth/TwoFactorSetupEmail.vue'),
  },
  {
    path: '/two-factor/setup/sms',
    name: 'two-factor.setup.sms',
    component: () => import('@/views/auth/TwoFactorSetupSms.vue'),
  },
  {
    path: '/accept-terms',
    name: 'accept-terms',
    component: () => import('@/views/auth/AcceptTerms.vue'),
  },
])

const login: RouteRecordRaw[] = [...guestRoutes, ...publicRoutes, ...accountStateRoutes]

export default login
