import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { RoleResource } from '@/types/role'

/**
 * Roles are read-only in the API (defined in code and synced with `php artisan abilities:refresh`)
 * and the list isn't paginated.
 */
export const useRoleStore = defineStore('role', {
  state: () => ({
    roles: [] as RoleResource[],
    role: null as RoleResource | null,
    isLoading: false,
  }),

  actions: {
    async fetchRoles() {
      if (this.isLoading) return

      try {
        this.isLoading = true

        const { data } = await http.get<{ data: RoleResource[] }>('roles')
        this.roles = data.data
      } catch (error) {
        console.error('Error fetching roles:', error)
      } finally {
        this.isLoading = false
      }
    },

    async fetchRole(id: number | string) {
      try {
        const response = await http.get<{ data: RoleResource }>(`roles/${id}`)
        this.role = response.data.data
      } catch (error) {
        console.error('Error fetching role:', error)
      }
    },

    resetRole() {
      this.role = null
    },
  },
})
