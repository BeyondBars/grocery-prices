import type { RouteMeta, RouteRecordRaw } from 'vue-router';

export const routes = (meta: RouteMeta, children: RouteRecordRaw[]) => {
  return children.map((route) => {
    if (route.meta === undefined) {
      route.meta = {}
    }

    route.meta = { ...meta, ...route.meta }

    if (route.children !== undefined) {
      route.children = routes(meta, route.children)
    }

    return route
  })
}