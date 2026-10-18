import { onUnmounted, watch, type WatchSource } from 'vue'

/**
 * Calls `refresh` every `ms` while `active` is true (e.g. while receipts are processing).
 */
export function usePolling(active: WatchSource<boolean>, refresh: () => Promise<unknown>, ms = 3000) {
  let timer: ReturnType<typeof setInterval> | null = null

  const stop = () => {
    if (timer) clearInterval(timer)
    timer = null
  }

  watch(
    active,
    (isActive) => {
      stop()
      if (isActive) timer = setInterval(refresh, ms)
    },
    { immediate: true }
  )

  onUnmounted(stop)
}
