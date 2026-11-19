import { ref } from 'vue'
import { defineStore } from 'pinia'

import { getSubscription } from '@/services/api'
import type { Subscription } from '@/services/types'

/** The rent of the negocio for the warning under the header; only the administration can read it. */
export const useSubscriptionStore = defineStore('subscription', () => {
  const current = ref<Subscription | null>(null)

  async function load(): Promise<void> {
    try {
      current.value = await getSubscription()
    } catch {
      current.value = null
    }
  }

  function clear(): void {
    current.value = null
  }

  return { current, load, clear }
})
