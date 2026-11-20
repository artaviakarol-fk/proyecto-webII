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
  listCatalogCategories: vi.fn<AnyFn>(),
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
  api.listCatalogCategories.mockResolvedValue(['Abarrotes', 'Lácteos'])
  api.catalogPhoto.mockRejectedValue(new Error('sin foto'))
})

describe('TiendaView', () => {
  it('lists products with price and warns when few units are left', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    const w = mount(TiendaView)
    await flushPromises()
    expect(w.text()).toContain('Leche')
    expect(w.text()).toContain('Quedan 3')
  })

  it('filters the catalog by the category the cliente picks', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    const w = mount(TiendaView)
    await flushPromises()
    const lacteos = w.findAll('.cat').find((b) => b.text() === 'Lácteos')!
    await lacteos.trigger('click')
    await flushPromises()
    expect(api.listCatalog).toHaveBeenLastCalledWith('', 1, 'Lácteos')
  })

  it('adds one unit with + and then shows how many are in the cart', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    api.reserve.mockResolvedValue({ data: {} })
    const w = mount(TiendaView)
    await flushPromises()
    api.listReservations.mockResolvedValue({
      data: [{ id: 'r1', producto_id: 'p1', nombre: 'Leche', cantidad: 1, precio_unitario: 1200, subtotal: 1200, expira_en: '2099-01-01T00:00:00Z' }],
      total: 1200,
    })
    await w.find('.addbtn').trigger('click')
    await flushPromises()
    expect(api.reserve).toHaveBeenCalledWith('p1', 1)
    expect(w.find('.addbtn').exists()).toBe(false)
    expect(w.find('.stepper').text()).toContain('1')
  })

  it('holds one more unit with + and one less with −, replacing the reserva', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    api.listReservations.mockResolvedValue({
      data: [{ id: 'r1', producto_id: 'p1', nombre: 'Leche', cantidad: 2, precio_unitario: 1200, subtotal: 2400, expira_en: '2099-01-01T00:00:00Z' }],
      total: 2400,
    })
    api.reserve.mockResolvedValue({ data: {} })
    const w = mount(TiendaView)
    await flushPromises()
    expect(w.find('.stepper').text()).toContain('2')
    const [minus, plus] = w.findAll('.stepper button')
    await plus!.trigger('click')
    await flushPromises()
    expect(api.reserve).toHaveBeenLastCalledWith('p1', 3)
    await minus!.trigger('click')
    await flushPromises()
    expect(api.reserve).toHaveBeenLastCalledWith('p1', 1)
  })

  it('takes the product out of the cart when − leaves it at zero', async () => {
    api.listCatalog.mockResolvedValue(page([product]))
    api.listReservations.mockResolvedValue({
      data: [{ id: 'r1', producto_id: 'p1', nombre: 'Leche', cantidad: 1, precio_unitario: 1200, subtotal: 1200, expira_en: '2099-01-01T00:00:00Z' }],
      total: 1200,
    })
    api.cancelReservation.mockResolvedValue(undefined)
    const w = mount(TiendaView)
    await flushPromises()
    await w.find('.stepper button').trigger('click')
    await flushPromises()
    expect(api.cancelReservation).toHaveBeenCalledWith('r1')
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
    await w.find('.addbtn').trigger('click')
    await flushPromises()
    expect(w.text()).toContain('Solo quedan 1 unidades')
  })
})
