import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/services/http'
import BodegaView from '@/views/BodegaView.vue'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({
  listProducts: vi.fn<AnyFn>(),
  listExpired: vi.fn<AnyFn>(),
  registerReceipt: vi.fn<AnyFn>(),
  markExpired: vi.fn<AnyFn>(),
}))
vi.mock('@/services/api', () => api)

const milk = {
  id: 'p1', codigo: 'LCH', nombre: 'Leche', categoria: null, precio_venta: 1200, costo_actual: 800,
  stock: 10, es_perecedero: true, es_licor: false, foto: null, agotado: false,
}
const empty = { ...milk, id: 'p2', codigo: 'PAN', nombre: 'Pan', stock: 0, agotado: true, es_perecedero: false }

beforeEach(() => {
  vi.clearAllMocks()
  api.listProducts.mockResolvedValue([milk, empty])
  api.listExpired.mockResolvedValue([])
})

describe('BodegaView', () => {
  it('lists the products with stock and status', async () => {
    const w = mount(BodegaView)
    await flushPromises()
    expect(w.text()).toContain('Leche')
    expect(w.text()).toContain('Agotado')
  })

  it('shows the empty state', async () => {
    api.listProducts.mockResolvedValue([])
    const w = mount(BodegaView)
    await flushPromises()
    expect(w.text()).toContain('No hay productos')
  })

  it('shows the error of the list', async () => {
    api.listProducts.mockRejectedValue(new Error('Sin conexión'))
    const w = mount(BodegaView)
    await flushPromises()
    expect(w.text()).toContain('Sin conexión')
  })

  it('registers a delivery and reloads the stock', async () => {
    api.registerReceipt.mockResolvedValue({})
    const w = mount(BodegaView)
    await flushPromises()
    const form = w.findAll('form')[1]!
    await form.find('input[maxlength="120"]').setValue('Distribuidora Sol')
    await form.find('select').setValue('p1')
    await form.find('input[aria-label="Cantidad 1"]').setValue(5)
    await form.find('input[aria-label="Costo 1"]').setValue(800)
    await form.trigger('submit')
    await flushPromises()
    expect(api.registerReceipt).toHaveBeenCalledWith(
      expect.objectContaining({ proveedor: 'Distribuidora Sol', lineas: [{ producto_id: 'p1', cantidad: 5, costo_unitario: 800 }] }),
    )
    expect(w.text()).toContain('Entrada registrada')
    expect(api.listProducts).toHaveBeenCalledTimes(2)
  })

  it('marks expired units of a product and shows the sellable stock', async () => {
    api.markExpired.mockResolvedValue({ stock_vendible: 7 })
    const w = mount(BodegaView)
    await flushPromises()
    await w.find('tbody button').trigger('click')
    const form = w.findAll('form').find((f) => f.text().includes('Vencidos:'))!
    await form.find('input[type="number"]').setValue(3)
    await form.find('input[maxlength="200"]').setValue('Fecha de vencimiento')
    await form.trigger('submit')
    await flushPromises()
    expect(api.markExpired).toHaveBeenCalledWith('p1', 3, 'Fecha de vencimiento')
    expect(w.text()).toContain('Stock vendible de Leche: 7')
  })

  it('shows what the API says when the vencidos are refused', async () => {
    api.markExpired.mockRejectedValue(new ApiError(409, 'No hay tantas unidades.'))
    const w = mount(BodegaView)
    await flushPromises()
    await w.find('tbody button').trigger('click')
    const form = w.findAll('form').find((f) => f.text().includes('Vencidos:'))!
    await form.find('input[type="number"]').setValue(99)
    await form.find('input[maxlength="200"]').setValue('x')
    await form.trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('No hay tantas unidades.')
  })

  it('shows the expired units and discounts them from what can be sold', async () => {
    api.listExpired.mockResolvedValue([
      { id: 'e1', producto_id: 'p1', codigo: 'LCH', producto: 'Leche', cantidad: 3, motivo: 'Fecha cumplida', registrada_por: 'Bodega', registrada_en: '2026-11-17T10:00:00Z' },
    ])
    const w = mount(BodegaView)
    await flushPromises()
    expect(w.text()).toContain('Vencidos en el estante')
    expect(w.text()).toContain('Fecha cumplida')
    const row = w.findAll('tbody tr').find((r) => r.text().includes('LCH') && r.text().includes('Perecedero'))!
    const cells = row.findAll('td').map((c) => c.text())
    expect(cells[2]).toBe('10')
    expect(cells[3]).toBe('3')
    expect(cells[4]).toBe('7')
  })
})
