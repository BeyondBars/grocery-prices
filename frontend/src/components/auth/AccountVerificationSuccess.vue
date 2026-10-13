<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
defineProps<{ loggedIn: boolean }>()
</script>

<template>
  <AuthCard title="Email verified">
    <p class="text-sm text-slate-600">
      Thank you for verifying your email address. This helps us keep your account secure.
    </p>

    <template #footer>
      <span />
      <RouterLink :to="{ name: loggedIn ? 'dashboard' : 'login' }" class="btn btn-primary">
        {{ loggedIn ? 'Continue' : 'Sign in' }}
      </RouterLink>
    </template>
  </AuthCard>
</template>
