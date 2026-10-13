<template>
  <PageHeader title="Settings" description="Roles are defined in code and synced with php artisan abilities:refresh." />

  <div class="card overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
        <tr>
          <th class="px-4 py-3">Role</th>
          <th class="px-4 py-3">Description</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="role in rolesStore.roles" :key="role.id">
          <td class="px-4 py-3 font-medium whitespace-nowrap text-slate-900">{{ role.title }}</td>
          <td class="px-4 py-3 text-slate-600">{{ role.description }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import { onMounted } from 'vue'
import { useRoleStore } from '@/stores/roles'

const rolesStore = useRoleStore()

onMounted(() => {
  rolesStore.fetchRoles()
})
</script>
