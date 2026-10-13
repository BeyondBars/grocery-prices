<template>
  <PageHeader title="Users" />

  <div id="scrolling-component" class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Mobile</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Last login</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="user in usersStore.users"
            :key="user.id"
            class="cursor-pointer hover:bg-slate-50"
            @click="go(user.id)"
          >
            <td class="px-4 py-3 font-medium text-slate-900">{{ user.name }}</td>
            <td class="px-4 py-3 text-slate-600">{{ user.email }}</td>
            <td class="px-4 py-3 text-slate-600">{{ user.mobile }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusBadge[user.status]?.class">
                {{ statusBadge[user.status]?.label ?? user.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ user.last_login_at ? $date(user.last_login_at) : '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import { onMounted, onUnmounted, nextTick, ref } from 'vue'
import { useUserStore } from '@/stores/users'
import { useRouter } from 'vue-router'
const router = useRouter()
const isLoading = ref(false)
const usersStore = useUserStore()
let scrollContainer: HTMLElement | null = null
let contentEl: HTMLElement | null = null

const statusBadge: Record<string, { label: string; class: string }> = {
  enabled: { label: 'Active', class: 'bg-emerald-100 text-emerald-700' },
  pending: { label: 'Pending', class: 'bg-amber-100 text-amber-700' },
  disabled: { label: 'Archived', class: 'bg-slate-200 text-slate-600' },
}

const go = (id: number) => {
  router.push({ name: 'users.details', params: { id } }) // adjust route name & param as needed
}
const loadUsers = async () => {
  if (isLoading.value || !usersStore.hasMore) return
  isLoading.value = true
  await usersStore.fetchUsers()
  isLoading.value = false
}

// Check if content fills the page — if not, load more until it does
const ensureScrollFills = () => {
  nextTick(() => {
    if (!scrollContainer || !contentEl || isLoading.value || !usersStore.hasMore) return

    const contentIsSmallerThanPage =
      contentEl.scrollHeight <= scrollContainer.clientHeight

    if (contentIsSmallerThanPage) {
      loadUsers().then(() => {
        // Retry fill until scrollable
        setTimeout(ensureScrollFills, 100)
      })
    }
  })
}

// Trigger infinite scroll when user scrolls near bottom
const handleScroll = () => {
  if (!scrollContainer || isLoading.value || !usersStore.hasMore) return

  const nearBottom =
    scrollContainer.scrollTop + scrollContainer.clientHeight >=
    scrollContainer.scrollHeight - 100

  if (nearBottom) {
    loadUsers()
  }
}

onMounted(() => {
  // Start from the first page each time the list is opened.
  usersStore.resetUsers()

  scrollContainer = document.querySelector('#page-content-wrapper')
  contentEl = document.querySelector('#scrolling-component')

  if (scrollContainer) {
    scrollContainer.addEventListener('scroll', handleScroll)
    loadUsers().then(() => ensureScrollFills())
  }
})

onUnmounted(() => {
  scrollContainer?.removeEventListener('scroll', handleScroll)
})
</script>