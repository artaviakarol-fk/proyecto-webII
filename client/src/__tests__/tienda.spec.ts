import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/services/http'
import TiendaView from '@/views/TiendaView.vue'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({
  listCatalog: vi.fn<AnyFn>(),
  listReservations: vi.fn<AnyFn>(),
  reserve: vi.fn<AnyFn>(),
  cancelReservation: vi.fn<AnyFn>(),
  catalogPhoto: vi.fn<AnyFn>(),
}))
vi.mock('@/services/api', () => api)
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn<AnyFn>() }) }))

const product = {
  id: 'p1', nombre: 'Leche', categoria: 'Lácteos', precio_venta: 1200,
  disponible: 3, agotado: false, es_licor: false, foto: null,
}
const page = (data: unknown[]) => ({ data, meta: { pagina: 1, por_pagina: 24, total: data.length, ultima_pagina: 1 } })

beforeEach(() => {
  setActivePinia(createPinia())
  api.listReservations.mockResolvedValue({ data: [], total: 0 })
  api.catalogPhoto.mockRejectedValue(new Error('sin foto'))
})

describe('TiendaView', () => {
  it('lists products with price and available units', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    const w = mount(TiendaView)
    await flushPromises()
    expect(w.text()).toContain('Leche')
    expect(w.text()).toContain('Disponibles: 3')
  })

  it('shows the empty state', async () => {
    api.listCatalog.mockResolvedValue(page([]))
    const w = mount(TiendaView)
    await flushPromises()
    expect(w.text()).toContain('No hay productos')
  })

  it('shows the error with a retry', async () => {
    api.listCatalog.mockRejectedValue(new Error('Falló la red'))
    const w = mount(TiendaView)
    await flushPromises()
    expect(w.text()).toContain('Falló la red')
  })

  it('shows what the API says when someone else took the units (409)', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    api.reserve.mockRejectedValue(new ApiError(409, 'Solo quedan 1 unidades de Leche.'))
    const w = mount(TiendaView)
    await flushPromises()
    await w.find('.reserve').trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('Solo quedan 1 unidades')
  })

  it('shows the reservas with their countdown', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    api.listReservations.mockResolvedValue({
      data: [{ id: 'r1', producto_id: 'p1', nombre: 'Leche', cantidad: 2, precio_unitario: 1200, subtotal: 2400,
        expira_en: new Date(Date.now() + 5 * 60_000).toISOString() }],
      total: 2400,
    })
    const w = mount(TiendaView)
    await flushPromises()
    expect(w.text()).toContain('Vence en 0')
    expect(w.text()).toContain('Total: ₡2')
  })
})
