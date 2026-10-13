import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = []
const modules: Record<string, RouteRecordRaw[]> = import.meta.glob('./routes/**/*.ts', {
  eager: true,
})

for (const path in modules) {
  const module = modules[path]

  if (module && 'default' in module) {
    routes.push(...(module.default as RouteRecordRaw[]))
  }
}

export default routes
