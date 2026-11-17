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

describe('resolveAccess for the bodeguero', () => {
  const keeper = logged(['inventario'])

  it('sends the bodeguero to the warehouse, not to the till', () => {
    expect(resolveAccess({ fullPath: '/login', meta: { public: true } }, keeper)).toEqual({ name: 'bodega' })
  })

  it('keeps the bodeguero out of the till, the fiado and the reports', () => {
    expect(resolveAccess({ fullPath: '/caja', meta: { ability: 'caja' } }, keeper)).toEqual({ name: 'forbidden' })
    expect(resolveAccess({ fullPath: '/fiado', meta: { ability: 'caja' } }, keeper)).toEqual({ name: 'forbidden' })
    expect(resolveAccess({ fullPath: '/cierre-diario', meta: { ability: 'administrar' } }, keeper)).toEqual({
      name: 'forbidden',
    })
  })

  it('keeps the cashier out of the warehouse', () => {
    expect(resolveAccess({ fullPath: '/bodega', meta: { ability: 'inventario' } }, logged(['caja']))).toEqual({
      name: 'forbidden',
    })
  })
})
