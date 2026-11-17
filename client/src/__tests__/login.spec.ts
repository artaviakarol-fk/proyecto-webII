import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import LoginView from '@/views/LoginView.vue'

// A loose mock signature: the tests read call arguments of different shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyFn = (...args: any[]) => any

const api = vi.hoisted(() => ({
  login: vi.fn<AnyFn>(),
  currentUser: vi.fn<AnyFn>(),
  logout: vi.fn<AnyFn>(),
}))
const push = vi.hoisted(() => vi.fn<AnyFn>())
vi.mock('@/services/api', () => api)
vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  useRoute: () => ({ query: {} }),
  RouterLink: { template: '<a><slot /></a>' },
}))
vi.mock('@/router', () => ({
  homeFor: (s: { can: (a: string) => boolean }) => (s.can('comprar') ? { name: 'tienda' } : { name: 'caja' }),
}))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  api.login.mockResolvedValue({ token: 't' })
  api.logout.mockResolvedValue(undefined)
})

async function submit(w: ReturnType<typeof mount>, personal = false) {
  if (personal) await w.findAll('.ltabs button')[1]!.trigger('click')
  await w.find('input[type="email"]').setValue('alguien@correo.test')
  await w.find('input[type="password"]').setValue('password')
  await w.find('form').trigger('submit')
  await flushPromises()
}

describe('LoginView', () => {
  it('sends the cliente to the shop', async () => {
    api.currentUser.mockResolvedValue({ id: '1', nombre: 'Ana', abilities: ['comprar'] })
    await submit(mount(LoginView))
    expect(push).toHaveBeenCalledWith({ name: 'tienda' })
  })

  it('sends the staff to the till from the staff tab', async () => {
    api.currentUser.mockResolvedValue({ id: '2', nombre: 'Caja', abilities: ['caja'] })
    await submit(mount(LoginView), true)
    expect(push).toHaveBeenCalledWith({ name: 'caja' })
  })

  it('refuses a staff account on the cliente tab and closes the session', async () => {
    api.currentUser.mockResolvedValue({ id: '2', nombre: 'Caja', abilities: ['caja'] })
    const w = mount(LoginView)
    await submit(w)
    expect(w.text()).toContain('Esa cuenta es de personal')
    expect(api.logout).toHaveBeenCalled()
    expect(push).not.toHaveBeenCalled()
  })

  it('refuses a cliente account on the staff tab', async () => {
    api.currentUser.mockResolvedValue({ id: '1', nombre: 'Ana', abilities: ['comprar'] })
    const w = mount(LoginView)
    await submit(w, true)
    expect(w.text()).toContain('Esa cuenta es de cliente')
  })

  it('shows the error when the credentials are wrong', async () => {
    api.login.mockRejectedValue(new Error('Credenciales incorrectas'))
    const w = mount(LoginView)
    await submit(w)
    expect(w.text()).toContain('Credenciales incorrectas')
  })
})
