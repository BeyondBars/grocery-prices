<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import VerificationResend from './AccountVerificationResend.vue'

withDefaults(defineProps<{ error?: string; showResendButton?: boolean }>(), {
  error: undefined,
  showResendButton: false,
})
</script>

<template>
  <AuthCard title="Verification failed">
    <p class="text-sm text-slate-600">
      <template v-if="error">{{ error }}.</template>
      <template v-else>We were unable to verify your email. The verification link has probably expired.</template>
    </p>

    <template #footer>
      <span />
      <VerificationResend v-if="showResendButton" />
      <RouterLink v-else :to="{ name: 'login' }" class="btn btn-primary">Back to sign in</RouterLink>
    </template>
  </AuthCard>
</template>
