<template>
  <div>
    <p class="text-sm text-slate-600">
      An email has been sent to your email address with instructions for creating an account.
    </p>
    <div class="mt-6 flex justify-end">
      <RouterLink :to="{ name: 'login' }" class="btn btn-primary">Back to sign in</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
  import { watchEffect } from 'vue'
</script>
