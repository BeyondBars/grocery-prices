<template>
  <div class="text-center">
    <p class="text-sm font-semibold text-brand-600">404</p>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Page not found</h1>
    <p class="mt-2 text-sm text-slate-600">The page you're looking for doesn't exist.</p>
    <RouterLink :to="{ name: 'dashboard' }" class="btn btn-primary mt-6">Go home</RouterLink>
  </div>
</template>

