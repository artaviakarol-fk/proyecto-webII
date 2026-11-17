import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import ReservationsDrawer from '@/components/ReservationsDrawer.vue'
import { useReservationsStore } from '@/stores/reservations'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({
  listReservations: vi.fn<AnyFn>(),
  cancelReservation: vi.fn<AnyFn>(),
}))
const push = vi.hoisted(() => vi.fn<AnyFn>())
vi.mock('@/services/api', () => api)
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

const reservation = {
  id: 'r1', producto_id: 'p1', nombre: 'Leche', cantidad: 2, precio_unitario: 1200, subtotal: 2400,
  expira_en: new Date(Date.now() + 5 * 60_000).toISOString(),
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  api.listReservations.mockResolvedValue({ data: [reservation], total: 2400 })
})

async function openDrawer() {
  const store = useReservationsStore()
  await store.load()
  store.drawerOpen = true
  const w = mount(ReservationsDrawer)
  await flushPromises()
  return { w, store }
}

describe('ReservationsDrawer', () => {
  it('stays hidden until it is opened', () => {
    const w = mount(ReservationsDrawer)
    expect(w.find('.drawer').exists()).toBe(false)
  })

  it('shows the reservas with their countdown and total', async () => {
    const { w } = await openDrawer()
    expect(w.text()).toContain('Leche × 2')
    expect(w.text()).toContain('Vence en 0')
    expect(w.text()).toContain('2')
  })

  it('shows the empty state when there is nothing reserved', async () => {
    api.listReservations.mockResolvedValue({ data: [], total: 0 })
    const { w } = await openDrawer()
    expect(w.text()).toContain('Aún no ha agregado nada.')
    expect(w.find('button.primary').attributes('disabled')).toBeDefined()
  })

  it('releases a reserva', async () => {
    api.cancelReservation.mockResolvedValue(undefined)
    const { w } = await openDrawer()
    await w.find('button[aria-label="Quitar Leche"]').trigger('click')
    expect(api.cancelReservation).toHaveBeenCalledWith('r1')
  })

  it('closes and goes to the payment page', async () => {
    const { w, store } = await openDrawer()
    await w.find('button.primary').trigger('click')
    expect(store.drawerOpen).toBe(false)
    expect(push).toHaveBeenCalledWith({ name: 'pago' })
  })
})
