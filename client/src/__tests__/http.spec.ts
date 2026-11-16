import { afterEach, describe, expect, it, vi } from 'vitest'

import { ApiError, request, tokenStorage } from '@/services/http'

afterEach(() => {
  vi.unstubAllGlobals()
  tokenStorage.clear()
})

function stubFetch(status: number, body: unknown) {
  const fn = vi.fn<typeof fetch>().mockResolvedValue(({ ok: status < 400, status, json: async () => body }) as Response)
  vi.stubGlobal('fetch', fn)
  return fn
}

describe('request', () => {
  it('sends the bearer token and returns the payload', async () => {
    tokenStorage.set('abc')
    const fetchMock = stubFetch(200, { data: 1 })
    await expect(request('GET', '/usuario')).resolves.toEqual({ data: 1 })
    const init = fetchMock.mock.calls[0]![1] as RequestInit
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer abc')
  })

  it('raises ApiError with the problem detail', async () => {
    stubFetch(422, { title: 'Invalid', detail: 'Crédito insuficiente' })
    await expect(request('POST', '/ventas', { body: {} })).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
      message: 'Crédito insuficiente',
    })
  })

  it('reports a network failure', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockRejectedValue(new TypeError('fail')))
    const error = await request('GET', '/x').catch((e: unknown) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(0)
  })
})
