import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import * as api from '@/services/api'
import { tokenStorage } from '@/services/http'
import { useSessionStore } from '@/stores/session'

vi.mock('@/services/api')

const cashier = {
  id: '1',
  nombre: 'Cajera',
  correo: 'c@x.cr',
  rol: 'Cajero',
  abilities: ['caja'],
  negocio_id: 'n',
} as never

beforeEach(() => {
  tokenStorage.clear()
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('session store', () => {
  it('logs in and exposes abilities', async () => {
    vi.mocked(api.login).mockResolvedValue({ token: 't', tipo: 'Bearer', abilities: ['caja'] })
    vi.mocked(api.currentUser).mockResolvedValue(cashier)
    const session = useSessionStore()
    await session.login('c@x.cr', 'pw')
    expect(session.isLogged).toBe(true)
    expect(session.can('caja')).toBe(true)
    expect(session.can('administrar')).toBe(false)
    expect(tokenStorage.get()).toBe('t')
  })

  it('clears the session on logout', async () => {
    vi.mocked(api.login).mockResolvedValue({ token: 't', tipo: 'Bearer', abilities: ['caja'] })
    vi.mocked(api.currentUser).mockResolvedValue(cashier)
    vi.mocked(api.logout).mockResolvedValue(undefined)
    const session = useSessionStore()
    await session.login('c@x.cr', 'pw')
    await session.logout()
    expect(session.isLogged).toBe(false)
    expect(tokenStorage.get()).toBeNull()
  })
})
