import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import * as api from '@/services/api'
import CajaView from '@/views/CajaView.vue'

vi.mock('@/services/api')

const milk = {
  id: 'p1',
  codigo: 'L1',
  nombre: 'Leche entera',
  precio_venta: 1200,
  stock: 10,
  agotado: false,
  es_licor: false,
}

beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(api.listProducts).mockResolvedValue([milk] as never)
  vi.mocked(api.listCustomers).mockResolvedValue([])
  vi.mocked(api.createSale).mockResolvedValue({ id: 's1', total: 1200 } as never)
  vi.mocked(api.cancelSale).mockResolvedValue({} as never)
})

/** Opens Caja, adds a milk to the sale and picks the way of paying. */
async function sellWith(method: string) {
  const w = mount(CajaView)
  await flushPromises()
  await w
    .findAll('button')
    .find((b) => b.text() === 'Agregar')
    ?.trigger('click')
  await lastSelect(w)?.setValue(method)
  return w
}

/** The way-of-paying select is the last one on the screen (the fiado customer select comes before it). */
const lastSelect = (w: ReturnType<typeof mount>) => w.findAll('select').slice(-1)[0]!

const button = (w: ReturnType<typeof mount>, text: string) =>
  w.findAll('button').find((b) => b.text() === text)

describe('collecting at the register', () => {
  it('offers card on the datáfono and SINPE received, with no gateway', async () => {
    const w = await sellWith('efectivo')
    const options = lastSelect(w).findAll('option')

    expect(options.map((o) => (o.element as HTMLOptionElement).value)).toEqual([
      'efectivo',
      'fiado',
      'tarjeta',
      'sinpe_movil',
    ])
  })

  it('does not let the cashier type card data', async () => {
    const w = await sellWith('tarjeta')

    expect(w.text()).toContain('Datáfono (simulado)')
    expect(w.find('input[autocomplete="cc-number"]').exists()).toBe(false)
    expect(w.text()).not.toContain('CVV')
  })

  it('cannot charge a card until the datáfono approves it', async () => {
    const w = await sellWith('tarjeta')

    expect(button(w, 'Cobrar')?.attributes('disabled')).toBeDefined()

    await button(w, 'Rechazar en el datáfono')?.trigger('click')
    expect(w.text()).toContain('Tarjeta rechazada')
    expect(button(w, 'Cobrar')?.attributes('disabled')).toBeDefined()
    expect(api.chargeAtRegister).not.toHaveBeenCalled()
  })

  it('charges the card with the authorization code of the datáfono', async () => {
    vi.mocked(api.chargeAtRegister).mockResolvedValue({} as never)
    const w = await sellWith('tarjeta')

    await button(w, 'Aprobar en el datáfono')?.trigger('click')
    const code = /AUT-\d{6}/.exec(w.text())?.[0]
    expect(code).toBeDefined()

    await button(w, 'Cobrar')?.trigger('click')
    await flushPromises()

    expect(api.chargeAtRegister).toHaveBeenCalledWith('s1', {
      metodo_pago: 'tarjeta',
      referencia: code,
    })
    expect(w.text()).toContain('Venta cobrada con tarjeta')
    expect(w.text()).toContain('Agregue productos')
  })

  it('needs the voucher and the confirmation before charging SINPE', async () => {
    const w = await sellWith('sinpe_movil')
    expect(button(w, 'Cobrar')?.attributes('disabled')).toBeDefined()

    await w.find('input[maxlength="60"]').setValue('2026112300123')
    expect(button(w, 'Cobrar')?.attributes('disabled')).toBeDefined()

    await w.find('input[type="checkbox"]').setValue(true)
    expect(button(w, 'Cobrar')?.attributes('disabled')).toBeUndefined()
  })

  it('charges SINPE with the voucher number', async () => {
    vi.mocked(api.chargeAtRegister).mockResolvedValue({} as never)
    const w = await sellWith('sinpe_movil')

    await w.find('input[maxlength="60"]').setValue('  2026112300123 ')
    await w.find('input[type="checkbox"]').setValue(true)
    await button(w, 'Cobrar')?.trigger('click')
    await flushPromises()

    expect(api.chargeAtRegister).toHaveBeenCalledWith('s1', {
      metodo_pago: 'sinpe_movil',
      referencia: '2026112300123',
    })
    expect(w.text()).toContain('Venta cobrada por SINPE Móvil')
  })

  it('cancels the open sale when the charge fails, so it does not hold the stock', async () => {
    vi.mocked(api.chargeAtRegister).mockRejectedValue(new Error('No se pudo cobrar'))
    const w = await sellWith('tarjeta')

    await button(w, 'Aprobar en el datáfono')?.trigger('click')
    await button(w, 'Cobrar')?.trigger('click')
    await flushPromises()

    expect(api.cancelSale).toHaveBeenCalledWith('s1')
    expect(w.text()).toContain('No se pudo cobrar')
  })

  it('forgets the datáfono answer when the way of paying changes', async () => {
    const w = await sellWith('tarjeta')
    await button(w, 'Aprobar en el datáfono')?.trigger('click')

    await lastSelect(w)?.setValue('efectivo')
    await lastSelect(w)?.setValue('tarjeta')

    expect(w.text()).toContain('Esperando la tarjeta')
    expect(button(w, 'Cobrar')?.attributes('disabled')).toBeDefined()
  })

  it('still charges cash with no reference', async () => {
    vi.mocked(api.chargeCash).mockResolvedValue({} as never)
    const w = await sellWith('efectivo')

    await button(w, 'Cobrar')?.trigger('click')
    await flushPromises()

    expect(api.chargeCash).toHaveBeenCalledWith('s1')
    expect(api.chargeAtRegister).not.toHaveBeenCalled()
  })
})
