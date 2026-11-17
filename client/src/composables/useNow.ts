import { onBeforeUnmount, onMounted, ref } from 'vue'

/** A clock that ticks every second, for the countdowns; it stops by itself when the component goes away. */
export function useNow(intervalMs = 1000) {
  const now = ref(Date.now())
  let timer: ReturnType<typeof setInterval> | null = null

  onMounted(() => {
    timer = setInterval(() => {
      now.value = Date.now()
    }, intervalMs)
  })
  onBeforeUnmount(() => {
    if (timer) clearInterval(timer)
  })

  return now
}
