import { ref, onMounted, onUnmounted, nextTick, type Ref } from 'vue'

export function useInfiniteScroll({
  loadFn,
  hasMore,
  scrollTargetSelector = '#page-content-wrapper',
  contentSelector = '#scrolling-component',
  offset = 100,
  autoFill = true
}: {
  loadFn: () => Promise<void>
  hasMore: Ref<boolean>
  scrollTargetSelector?: string
  contentSelector?: string
  offset?: number
  autoFill?: boolean
}) {
  const isLoading = ref(false)
  let scrollContainer: HTMLElement | null = null
  let contentElement: HTMLElement | null = null

  const loadMore = async () => {
    if (!hasMore.value || isLoading.value) return
    isLoading.value = true
    await loadFn()
    isLoading.value = false

    if (autoFill) {
      // Try again if not enough to scroll
      setTimeout(ensureScrollFills, 100)
    }
  }

  const ensureScrollFills = () => {
    nextTick(() => {
      if (!scrollContainer || !contentElement || !hasMore.value || isLoading.value) return

      const contentIsShort =
        contentElement.scrollHeight <= scrollContainer.clientHeight

      if (contentIsShort) {
        loadMore()
      }
    })
  }

  const handleScroll = () => {
    if (!scrollContainer || isLoading.value || !hasMore.value) return

    const nearBottom =
      scrollContainer.scrollTop + scrollContainer.clientHeight >=
      scrollContainer.scrollHeight - offset

    if (nearBottom) {
      loadMore()
    }
  }

  onMounted(() => {
    scrollContainer = document.querySelector(scrollTargetSelector)
    contentElement = document.querySelector(contentSelector)
    if (scrollContainer) {
      scrollContainer.addEventListener('scroll', handleScroll)

      // 🧠 Delay 1 frame to allow page to render content
      nextTick(() => {
        setTimeout(() => {
          loadMore() // First call
        }, 100)
      })
    }
  })

  onUnmounted(() => {
    scrollContainer?.removeEventListener('scroll', handleScroll)
  })

  return { isLoading }
}
