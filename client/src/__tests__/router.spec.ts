import { describe, expect, it } from 'vitest'

import { resolveAccess } from '@/router'

const logged = (abilities: string[]) => ({
  isLogged: true,
  can: (a: string) => abilities.includes(a),
})
const anon = { isLogged: false, can: () => false }

describe('resolveAccess', () => {
  it('sends anonymous users to login keeping the target', () => {
    const r = resolveAccess({ fullPath: '/caja', meta: { ability: 'caja' } }, anon)
    expect(r).toEqual({ name: 'login', query: { redirect: '/caja' } })
  })

  it('lets the cashier into the till', () => {
    expect(resolveAccess({ fullPath: '/caja', meta: { ability: 'caja' } }, logged(['caja']))).toBe(true)
  })

  it('blocks the cashier from the daily closing', () => {
    const r = resolveAccess(
      { fullPath: '/cierre-diario', meta: { ability: 'administrar' } },
      logged(['caja']),
    )
    expect(r).toEqual({ name: 'forbidden' })
  })

  it('lets the administrator into the daily closing', () => {
    const admin = logged(['administrar', 'caja', 'inventario'])
    expect(resolveAccess({ fullPath: '/cierre-diario', meta: { ability: 'administrar' } }, admin)).toBe(true)
  })

  it('moves logged users away from login', () => {
    expect(resolveAccess({ fullPath: '/login', meta: { public: true } }, logged(['caja']))).toEqual({
      name: 'caja',
    })
  })
})

describe('resolveAccess for the cliente', () => {
  const cliente = logged(['comprar'])

  it('sends the cliente to the shop after login', () => {
    expect(resolveAccess({ fullPath: '/login', meta: { public: true } }, cliente)).toEqual({ name: 'tienda' })
  })

  it('sends "/" to the shop for the cliente and to the till for staff', () => {
    expect(resolveAccess({ fullPath: '/', meta: { home: true } }, cliente)).toEqual({ name: 'tienda' })
    expect(resolveAccess({ fullPath: '/', meta: { home: true } }, logged(['caja']))).toEqual({ name: 'caja' })
  })

  it('keeps the cliente out of the till and the staff out of the shop', () => {
    expect(resolveAccess({ fullPath: '/caja', meta: { ability: 'caja' } }, cliente)).toEqual({ name: 'forbidden' })
    expect(resolveAccess({ fullPath: '/tienda', meta: { ability: 'comprar' } }, logged(['caja']))).toEqual({
      name: 'forbidden',
    })
  })
})
