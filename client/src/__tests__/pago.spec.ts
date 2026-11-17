import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import PagoView from '@/views/PagoView.vue'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({ listGateways: vi.fn<AnyFn>(), listReservations: vi.fn<AnyFn>(), buy: vi.fn<AnyFn>() }))
const nav = vi.hoisted(() => ({ goTo: vi.fn<AnyFn>(), set: vi.fn<AnyFn>() }))
const push = vi.hoisted(() => vi.fn<AnyFn>())
vi.mock('@/services/api', () => api)
vi.mock('@/utils/navigation', () => ({ goTo: nav.goTo, lastSale: { set: nav.set, get: () => null } }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

const paypal = { proveedor: 'paypal', nombre: 'PayPal', metodos: ['tarjeta'], requiere_metodo_cliente: false,
  llave_publica: null, configurada: true, predeterminada: true }
const reservation = { id: 'r1', producto_id: 'p1', nombre: 'Leche', cantidad: 1, precio_unitario: 1200, subtotal: 1200,
  expira_en: '2099-01-01T00:00:00Z' }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  api.listReservations.mockResolvedValue({ data: [reservation], total: 1200 })
})

describe('PagoView', () => {
  it('shows only the gateways that are configured', async () => {
    api.listGateways.mockResolvedValue([paypal, { ...paypal, proveedor: 'onvo', nombre: 'ONVO', configurada: false, predeterminada: false }])
    const w = mount(PagoView)
    await flushPromises()
    expect(w.text()).toContain('PayPal')
    expect(w.text()).not.toContain('ONVO')
  })

  it('sends the cliente to PayPal to approve the payment', async () => {
    api.listGateways.mockResolvedValue([paypal])
    api.buy.mockResolvedValue({ venta_id: 's1', url_aprobacion: 'https://paypal.test/approve' })
    const w = mount(PagoView)
    await flushPromises()
    await w.find('form.form').trigger('submit')
    await flushPromises()
    expect(api.buy).toHaveBeenCalledWith(expect.objectContaining({ pasarela: 'paypal', metodo_pago: 'tarjeta', metodo_proveedor_id: null }))
    expect(nav.set).toHaveBeenCalledWith('s1')
    expect(nav.goTo).toHaveBeenCalledWith('https://paypal.test/approve')
  })

  it('shows the error the API gives and keeps the form', async () => {
    api.listGateways.mockResolvedValue([paypal])
    api.buy.mockRejectedValue(new Error('No tiene reservas activas'))
    const w = mount(PagoView)
    await flushPromises()
    await w.find('form.form').trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('No tiene reservas activas')
    expect(push).not.toHaveBeenCalled()
  })

  it('says so when there is nothing to pay', async () => {
    api.listGateways.mockResolvedValue([paypal])
    api.listReservations.mockResolvedValue({ data: [], total: 0 })
    const w = mount(PagoView)
    await flushPromises()
    expect(w.text()).toContain('No tiene productos en su compra')
  })
})
