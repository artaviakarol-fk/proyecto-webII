import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import * as api from '@/services/api'
import type { Reservation } from '@/services/types'

/** What the cliente holds before paying: the reservas that still have not expired. */
export const useReservationsStore = defineStore('reservations', () => {
  const items = ref<Reservation[]>([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)
  // Whether the side cart is open; the header button and the shop open it.
  const drawerOpen = ref(false)

  const count = computed(() => items.value.reduce((units, item) => units + item.cantidad, 0))

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const result = await api.listReservations()
      items.value = result.data
      total.value = result.total
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'No se pudieron cargar sus reservas'
    } finally {
      loading.value = false
    }
  }

  /** Holds units of a product; the API answers 409 with how many are left when someone else was faster. */
  async function reserve(productId: string, quantity: number): Promise<Reservation> {
    const reservation = await api.reserve(productId, quantity)
    await load()
    return reservation
  }

  async function release(reservationId: string): Promise<void> {
    await api.cancelReservation(reservationId)
    await load()
  }

  function clear(): void {
    items.value = []
    total.value = 0
    error.value = null
    drawerOpen.value = false
  }

  return { items, total, loading, error, drawerOpen, count, load, reserve, release, clear }
})
