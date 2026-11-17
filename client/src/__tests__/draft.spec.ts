import { describe, expect, it } from 'vitest'

import { mergeDraftIntoCart } from '@/utils/draft'

const milk = { id: 'p1', nombre: 'Leche', stock: 10 } as never
const bread = { id: 'p2', nombre: 'Pan', stock: 3 } as never
const products = new Map([
  ['p1', milk],
  ['p2', bread],
]) as never

function draftWith(lineas: object[]) {
  return { lineas } as never
}

describe('mergeDraftIntoCart', () => {
  it('adds the lines the AI understood to an empty cart', () => {
    const cart = mergeDraftIntoCart(
      [],
      draftWith([
        { producto_id: 'p1', cantidad: 2, disponible_para_vender: 10 },
        { producto_id: 'p2', cantidad: 1, disponible_para_vender: 3 },
      ]),
      products,
    )
    expect(cart.map((l) => [l.product.id, l.qty])).toEqual([
      ['p1', 2],
      ['p2', 1],
    ])
  })

  it('never goes over what can be sold', () => {
    const cart = mergeDraftIntoCart(
      [],
      draftWith([{ producto_id: 'p2', cantidad: 8, disponible_para_vender: 2 }]),
      products,
    )
    expect(cart[0]?.qty).toBe(2)
  })

  it('leaves out a product with nothing to sell', () => {
    const cart = mergeDraftIntoCart(
      [],
      draftWith([{ producto_id: 'p1', cantidad: 2, disponible_para_vender: 0 }]),
      products,
    )
    expect(cart).toEqual([])
  })

  it('adds to a line that is already in the cart and keeps the rest', () => {
    const original = [
      { product: milk, qty: 1 },
      { product: bread, qty: 2 },
    ] as never
    const cart = mergeDraftIntoCart(
      original,
      draftWith([{ producto_id: 'p1', cantidad: 2, disponible_para_vender: 10 }]),
      products,
    )
    expect(cart.map((l) => l.qty)).toEqual([3, 2])
  })

  it('does not change the cart it receives', () => {
    const original = [{ product: milk, qty: 1 }] as never
    mergeDraftIntoCart(
      original,
      draftWith([{ producto_id: 'p1', cantidad: 2, disponible_para_vender: 10 }]),
      products,
    )
    expect((original as { qty: number }[])[0]?.qty).toBe(1)
  })
})
