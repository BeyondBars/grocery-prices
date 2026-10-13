<script setup lang="ts">
import AuthCard from '@/components/auth/AuthCard.vue'
import { computed, ref } from 'vue'
import { isAxiosError } from 'axios'
import { useToast } from 'vue-toast-notification'
import { useAppStore } from '@/stores/app'
import { useLogout } from '@/composables/use-logout'
import { useTwoFactorSetup } from '@/composables/use-two-factor-setup'
import TwoFactorCodeInput from '@/components/TwoFactorCodeInput.vue'
import type { TwoFactorMethod } from '@/types/user'

/**
 * Shared body of the "set up email/SMS 2-step verification" screens.
 */
const props = defineProps<{ method: TwoFactorMethod }>()

const toast = useToast()
const appStore = useAppStore()
const { logout } = useLogout()
const { verify, changeMethod, isSubmitting } = useTwoFactorSetup(props.method)

const label = computed(() => (props.method === 'sms' ? 'SMS' : 'Email'))
const destination = computed(() =>
  props.method === 'sms' ? `your mobile (${appStore.user?.mobile ?? ''})` : `your email address (${appStore.user?.email ?? ''})`
)
const otherMethod = computed(() => (props.method === 'sms' ? 'email' : 'SMS'))
const sending = ref(false)

async function sendCode(showToast = true) {
  sending.value = true

  try {
    await appStore.sendTwoFactorCode(props.method)
    if (showToast) {
      toast.success(`A verification code has been sent to ${destination.value}. It expires in 10 minutes.`)
    }
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 429) {
      toast.error('Please wait a minute before requesting another code.')
      return
    }
    if (isAxiosError(error) && error.response?.data?.message) {
      toast.error(error.response.data.message)
      return
    }
    throw error
  } finally {
    sending.value = false
  }
}

// Send the first code as soon as the screen opens.
await sendCode(false)
</script>

<template>
  <AuthCard :title="`Set up ${label} 2-step verification`">
    <form novalidate @submit="verify">
      <p class="mb-4 text-sm text-slate-600">
        We've sent a code to {{ destination }}. Enter it below to finish setting up.
      </p>

      <TwoFactorCodeInput />

      <p class="text-sm text-slate-600">
        Code hasn't arrived?
        <button type="button" class="link" :disabled="sending" @click="sendCode()">Resend code</button>
        &middot;
        <button type="button" class="link" @click="changeMethod">Use {{ otherMethod }} instead</button>
      </p>

      <div class="mt-6 flex items-center justify-between gap-4">
        <button type="button" class="link-muted" @click="logout">Sign out</button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">Verify</button>
      </div>
    </form>
  </AuthCard>
</template>
