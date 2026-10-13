<script setup lang="ts">
import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import { useToast } from 'vue-toast-notification'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
const router = useRouter()
const toast = useToast()
const sending = ref(false)

async function resend() {
  sending.value = true

  try {
    await appStore.resendEmailVerification()
    await Swal.fire({
      title: 'Email sent',
      text: 'A verification email has been sent. The link in it is valid for 24 hours.',
      icon: 'success',
    })
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 400) {
      // Already verified: refresh the user and continue.
      await appStore.getUser()
      router.replace({ name: 'dashboard' })
      return
    }

    if (isAxiosError(error) && error.response?.status === 429) {
      toast.error('Please wait a minute before requesting another email.')
      return
    }

    toast.error('Failed to send the verification email. Please contact support.')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <button type="button" class="btn btn-primary" :disabled="sending" v-bind="$attrs" @click="resend">
    Re-send email
  </button>
</template>
