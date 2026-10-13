<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { useAppStore } from '@/stores/app'
import { useLogout } from '@/composables/use-logout'
import VerificationResend from './AccountVerificationResend.vue'

const appStore = useAppStore()
const { logout } = useLogout()
</script>

<template>
  <AuthCard title="Verify your email">
    <p class="mb-3 text-sm text-slate-600">
      We've sent an email to <strong>{{ appStore.user?.email }}</strong> to verify your account. Please check your
      inbox and verify your address before continuing.
    </p>
    <p class="text-sm text-slate-500">
      If you can't find it, check your junk folder or request a new email with the button below.
    </p>

    <template #footer>
      <button type="button" class="link-muted" @click="logout">Sign out</button>
      <VerificationResend />
    </template>
  </AuthCard>
</template>
