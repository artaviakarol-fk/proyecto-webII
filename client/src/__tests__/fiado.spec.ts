import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/services/http'
import FiadoView from '@/views/FiadoView.vue'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({
  listCustomers: vi.fn<AnyFn>(),
  receiveCreditPayment: vi.fn<AnyFn>(),
  grantCreditIncrease: vi.fn<AnyFn>(),
}))
vi.mock('@/services/api', () => api)

const maria = {
  id: 'c1', nombre: 'María', telefono: null, limite_fiado: 90000, ampliacion_vigente: 0,
  limite_efectivo: 90000, saldo_actual: 30000, disponible: 60000, activo: true,
}

beforeEach(() => {
  vi.clearAllMocks()
  api.listCustomers.mockResolvedValue([maria])
})

async function openMaria() {
  const w = mount(FiadoView)
  await flushPromises()
  await w.find('tbody button').trigger('click')
  return w
}

describe('FiadoView', () => {
  it('lists the customers with what they owe', async () => {
    const w = mount(FiadoView)
    await flushPromises()
    expect(w.text()).toContain('María')
    expect(w.text()).toContain('30')
  })

  it('shows the empty state', async () => {
    api.listCustomers.mockResolvedValue([])
    const w = mount(FiadoView)
    await flushPromises()
    expect(w.text()).toContain('No hay clientes')
  })

  it('registers an abono and reloads the balance', async () => {
    api.receiveCreditPayment.mockResolvedValue({})
    const w = await openMaria()
    await w.find('form.block input[type="number"]').setValue(10000)
    await w.findAll('form.block')[0]!.trigger('submit')
    await flushPromises()
    expect(api.receiveCreditPayment).toHaveBeenCalledWith('c1', 10000)
    expect(w.text()).toContain('Abono de')
    expect(api.listCustomers).toHaveBeenCalledTimes(2)
  })

  it('fills the whole balance with the "Cancelar todo" button', async () => {
    const w = await openMaria()
    await w.findAll('form.block')[0]!.findAll('button')[0]!.trigger('click')
    expect((w.find('form.block input[type="number"]').element as HTMLInputElement).value).toBe('30000')
  })

  it('shows what the API says when the limit cannot be extended', async () => {
    api.grantCreditIncrease.mockRejectedValue(new ApiError(409, 'No puede pasar de ₡120 000.'))
    const w = await openMaria()
    const second = w.findAll('form.block')[1]!
    await second.find('input[type="number"]').setValue(50000)
    await second.find('input[maxlength]').setValue('Cliente de confianza')
    await second.trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('No puede pasar')
  })
})
