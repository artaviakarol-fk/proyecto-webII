import { afterEach, describe, expect, it, vi } from 'vitest'

import { createCardMethod, createSinpeMethod } from '@/services/onvo'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const billing = { name: 'Ana', email: 'ana@example.com' }

afterEach(() => vi.unstubAllGlobals())

describe('onvo payment methods', () => {
  it('sends the card to ONVO with the publishable key and returns the method id', async () => {
    const fetchMock = vi.fn<AnyFn>().mockResolvedValue({ ok: true, json: async () => ({ id: 'pm_1' }) })
    vi.stubGlobal('fetch', fetchMock)

    const id = await createCardMethod(
      'onvo_test_publishable_key_x',
      { holderName: 'Ana', number: '4242424242424242', expMonth: 8, expYear: 2029, cvv: '123' },
      billing,
    )

    expect(id).toBe('pm_1')
    const [url, init] = fetchMock.mock.calls[0]!
    expect(url).toBe('https://api.onvopay.com/v1/payment-methods')
    expect(init.headers.Authorization).toBe('Bearer onvo_test_publishable_key_x')
    expect(JSON.parse(init.body).type).toBe('card')
  })

  it('creates a SINPE method', async () => {
    const fetchMock = vi.fn<AnyFn>().mockResolvedValue({ ok: true, json: async () => ({ id: 'pm_2' }) })
    vi.stubGlobal('fetch', fetchMock)

    await expect(createSinpeMethod('k', { identification: '1', phone: '88888888' }, billing)).resolves.toBe('pm_2')
    expect(JSON.parse(fetchMock.mock.calls[0]![1].body).type).toBe('mobile_number')
  })

  it('explains when ONVO rejects the data', async () => {
    vi.stubGlobal('fetch', vi.fn<AnyFn>().mockResolvedValue({ ok: false, json: async () => ({}) }))
    await expect(
      createCardMethod('k', { holderName: 'A', number: '1', expMonth: 1, expYear: 2030, cvv: '1' }, billing),
    ).rejects.toThrow('ONVO no aceptó')
  })

  it('explains when ONVO cannot be reached', async () => {
    vi.stubGlobal('fetch', vi.fn<AnyFn>().mockRejectedValue(new Error('offline')))
    await expect(createSinpeMethod('k', { identification: '1', phone: '2' }, billing)).rejects.toThrow('conectar')
  })
})
