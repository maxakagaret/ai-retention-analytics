import { onBeforeUnmount, onMounted } from 'vue'

/** Runs `task` now and every `intervalMs` while the component is mounted and the tab is visible. */
export function usePolling(task: () => Promise<unknown>, intervalMs = 5000) {
  let timer: ReturnType<typeof setInterval> | undefined

  const tick = () => {
    if (document.visibilityState === 'visible') void task()
  }

  onMounted(() => {
    void task()
    timer = setInterval(tick, intervalMs)
  })

  onBeforeUnmount(() => clearInterval(timer))
}
