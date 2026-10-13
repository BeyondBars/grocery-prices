import { useAppStore } from '@/stores/app'
import { storeToRefs } from 'pinia'
import { createMongoAbility } from '@casl/ability'
import { computed, type Ref } from 'vue'
import type { UserResource } from '@/types/user'

/**
 * The logged in user and ability helpers. Throws when nobody is logged in, so check
 * `useIsLoggedIn()` first where that's possible.
 */
export function useLoggedInUser() {
  const appStore = useAppStore()
  const { user } = storeToRefs(appStore)

  if (user.value === null) {
    throw new Error('No logged in user.')
  }

  const ability = computed(() =>
    createMongoAbility((user.value?.abilities ?? []).map(({ name }) => ({ action: name, subject: 'all' })))
  )

  return {
    loggedInUser: user as Ref<UserResource>,
    can: (action: string) => ability.value.can(action, 'all'),
    cannot: (action: string) => ability.value.cannot(action, 'all'),
    getLoggedInUser: appStore.getUser,
  }
}
