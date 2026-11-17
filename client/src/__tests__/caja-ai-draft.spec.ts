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

const draft = {
  texto: 'anote 2 leches a doña María en fiado',
  metodo_pago: 'fiado',
  cliente_mencionado: 'doña María',
  cliente: { id: 'c1', nombre: 'María Rojas', activo: true, saldo_actual: 0, disponible: 50000 },
  lineas: [
    {
      producto_id: 'p1',
      nombre: 'Leche entera',
      mencionado: 'leches',
      cantidad: 2,
      precio_unitario: 1200,
      subtotal: 2400,
      disponible_para_vender: 10,
      alcanza: true,
    },
  ],
  no_encontrados: [{ mencionado: 'unicornio', cantidad: 1 }],
  total: 2400,
  cabe_en_fiado: true,
  avisos: ['No se encontró "unicornio" en el catálogo; agréguelo a mano.'],
  listo_para_confirmar: false,
  confirmar: { venta: { lineas: [] }, fiado: { cliente_id: 'c1' } },
}

beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(api.listProducts).mockResolvedValue([])
  vi.mocked(api.listCustomers).mockResolvedValue([
    {
      id: 'c1',
      nombre: 'María Rojas',
      activo: true,
      saldo_actual: 0,
      limite_efectivo: 50000,
      disponible: 50000,
    },
  ] as never)
})

async function dictate(text: string) {
  const w = mount(CajaView)
  await flushPromises()
  await w.find('textarea').setValue(text)
  const button = w.findAll('button').find((b) => b.text() === 'Interpretar')
  await button?.trigger('click')
  await flushPromises()
  return w
}

describe('dictated sale (AI draft)', () => {
  it('loads the draft into the sale without registering anything', async () => {
    vi.mocked(api.draftSale).mockResolvedValue(draft as never)
    vi.mocked(api.getProduct).mockResolvedValue(milk as never)

    const w = await dictate('anote 2 leches a doña María en fiado')

    expect(api.draftSale).toHaveBeenCalledWith('anote 2 leches a doña María en fiado')
    expect(w.text()).toContain('Leche entera')
    expect(w.text()).toContain('unicornio')
    expect(w.text()).toContain('no se registra sola')
    const values = w.findAll('select').map((s) => (s.element as HTMLSelectElement).value)
    expect(values).toEqual(['fiado', 'c1'])
    expect(api.createSale).not.toHaveBeenCalled()
  })

  it('shows the error and a retry button when the AI is not available', async () => {
    vi.mocked(api.draftSale).mockRejectedValue(new Error('El asistente de IA no está disponible.'))

    const w = await dictate('anote 2 leches')

    expect(w.text()).toContain('El asistente de IA no está disponible.')
    expect(w.text()).toContain('Reintentar')
    expect(w.text()).toContain('Agregue productos para iniciar la venta.')
  })

  it('does not let an empty text be sent', async () => {
    const w = mount(CajaView)
    await flushPromises()
    const button = w.findAll('button').find((b) => b.text() === 'Interpretar')
    expect(button?.attributes('disabled')).toBeDefined()
  })
})
