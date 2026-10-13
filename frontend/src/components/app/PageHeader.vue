<script setup lang="ts">
/** Title row at the top of each in-app page. Optional actions go in the default slot. */
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">{{ title }}</h1>
      <p v-if="description || $slots.description" class="mt-1 text-sm text-slate-500">
        <slot name="description">{{ description }}</slot>
      </p>
    </div>
    <slot />
  </div>
</template>
