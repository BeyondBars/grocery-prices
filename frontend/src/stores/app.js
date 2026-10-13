import { defineStore } from 'pinia'
import { http } from '@/utils/http'

/**
 * Session/auth state for the SPA. `user` is null when not logged in.
 */
export const useAppStore = defineStore('app', {
  state: () => ({
    sidesheet: {
      active: false,
      visible: false,
    },
    csrf: false,
    /** @type {import('@/types/user').UserResource | null} */
    user: null,
    authenticationAttempted: false,
  }),

  actions: {
    async csrfPreflight() {
      await http.get('sanctum/csrf-cookie', { csrfPreflight: true })
      this.csrf = true
    },

    /** @returns {Promise<import('@/types/user').UserResource>} */
    async login(payload) {
      const response = await http.post('auth', payload)
      this.user = response.data.data
      this.authenticationAttempted = true
      return this.user
    },

    async logout() {
      try {
        await http.delete('auth')
      } finally {
        this.user = null
        // The session (and its CSRF token) is invalidated on logout.
        this.csrf = false
      }
    },

    /** @returns {Promise<import('@/types/user').UserResource>} */
    async getUser() {
      this.authenticationAttempted = true
      const response = await http.get('user', { userPreflight: true })
      this.user = response.data.data
      return this.user
    },

    // Registration
    async verify(payload) {
      return http.post('verification', payload)
    },

    async verifySignature({ expires, email, signature }) {
      return http.get('verify', { params: { expires, email, signature } })
    },

    /** @returns {Promise<import('@/types/user').UserResource>} */
    async accountSetup(payload) {
      await http.post('account-setup', payload)
      // The API logs the new user straight in.
      return this.getUser()
    },

    async acceptTerms(payload) {
      const response = await http.put('accept-terms', payload)
      this.user = response.data.data
    },

    // Email verification for existing users
    async emailVerification(payload) {
      await http.post('auth/verification', payload)
    },

    async resendEmailVerification() {
      await http.post('auth/verification/resend')
    },

    // Two factor
    async emailTwoFactorCode() {
      await http.post('two-factor/email')
    },

    async smsTwoFactorCode() {
      await http.post('two-factor/sms')
    },

    async sendTwoFactorCode(method) {
      return method === 'sms' ? this.smsTwoFactorCode() : this.emailTwoFactorCode()
    },

    /** @returns {Promise<import('@/types/user').UserResource>} */
    async twoFactorVerify(payload) {
      const response = await http.post('two-factor/verify', payload)
      this.user = response.data.data
      return this.user
    },

    /** @returns {Promise<import('@/types/user').UserResource>} */
    async updateTwoFactor(payload) {
      const response = await http.put('two-factor', payload)
      this.user = response.data.data
      return this.user
    },

    // Passwords
    async emailForgotPassword(payload) {
      await http.post('password/forgot', payload)
    },

    async resetPassword(payload) {
      await http.post('password/reset', payload)
    },
  },
})
