import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/services/http'
import VentasView from '@/views/VentasView.vue'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({
  listSales: vi.fn<AnyFn>(),
  getSale: vi.fn<AnyFn>(),
  cancelSale: vi.fn<AnyFn>(),
}))
vi.mock('@/services/api', () => api)

const summary = {
  id: 's1', estado: 'cobrada', metodo_pago: 'efectivo', cajero: { id: 'u1', nombre: 'Carla' },
  cantidad_lineas: 1, total: 2400, creada_en: '2026-11-17T10:00:00Z', cobrada_en: '2026-11-17T10:01:00Z', anulada_en: null,
}
const detail = {
  ...summary,
  cedula_comprador: null,
  lineas: [{ producto_id: 'p1', codigo: 'LCH', nombre: 'Leche', cantidad: 2, precio_unitario: 1200, subtotal: 2400 }],
  advertencias: [],
}
const page = (data: unknown[]) => ({ data, meta: { pagina: 1, por_pagina: 15, total: data.length, ultima_pagina: 1 } })

beforeEach(() => vi.clearAllMocks())

describe('VentasView', () => {
  it('lists the sales with their status and total', async () => {
    api.listSales.mockResolvedValue(page([summary]))
    const w = mount(VentasView)
    await flushPromises()
    expect(w.text()).toContain('Carla')
    expect(w.text()).toContain('Cobrada')
  })

  it('shows the empty state', async () => {
    api.listSales.mockResolvedValue(page([]))
    const w = mount(VentasView)
    await flushPromises()
    expect(w.text()).toContain('No hay ventas')
  })

  it('shows the error of the list', async () => {
    api.listSales.mockRejectedValue(new Error('Sin conexión'))
    const w = mount(VentasView)
    await flushPromises()
    expect(w.text()).toContain('Sin conexión')
  })

  it('asks for confirmation before cancelling and cancels only after "Sí, anular"', async () => {
    api.listSales.mockResolvedValue(page([summary]))
    api.getSale.mockResolvedValue(detail)
    api.cancelSale.mockResolvedValue({ ...detail, estado: 'anulada', anulada_en: '2026-11-17T11:00:00Z' })
    const w = mount(VentasView)
    await flushPromises()

    await w.find('tbody button').trigger('click')
    await flushPromises()
    expect(w.text()).toContain('2 × Leche')

    await w.findAll('button').find((b) => b.text() === 'Anular venta')!.trigger('click')
    expect(api.cancelSale).not.toHaveBeenCalled()
    expect(w.text()).toContain('¿Anular esta venta?')

    await w.findAll('button').find((b) => b.text() === 'Sí, anular')!.trigger('click')
    await flushPromises()
    expect(api.cancelSale).toHaveBeenCalledWith('s1')
    expect(w.text()).toContain('Venta anulada')
  })

  it('shows the reason when the API refuses the cancellation', async () => {
    api.listSales.mockResolvedValue(page([summary]))
    api.getSale.mockResolvedValue(detail)
    api.cancelSale.mockRejectedValue(new ApiError(409, 'La venta ya fue anulada.'))
    const w = mount(VentasView)
    await flushPromises()
    await w.find('tbody button').trigger('click')
    await flushPromises()
    await w.findAll('button').find((b) => b.text() === 'Anular venta')!.trigger('click')
    await w.findAll('button').find((b) => b.text() === 'Sí, anular')!.trigger('click')
    await flushPromises()
    expect(w.text()).toContain('La venta ya fue anulada.')
  })
})
