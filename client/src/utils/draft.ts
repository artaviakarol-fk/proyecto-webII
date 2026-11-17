import type { DraftLine, DraftNotFound, Product, SaleDraft } from '@/services/types'

export interface CartLine {
  product: Product
  qty: number
}

export const draftLines = (draft: SaleDraft): DraftLine[] => draft.lineas as DraftLine[]

export const draftNotFound = (draft: SaleDraft): DraftNotFound[] =>
  draft.no_encontrados as DraftNotFound[]

/**
 * Adds the products the AI understood to the cart. A line never goes over what can be sold,
 * and a product with nothing to sell is left out (the draft already warns about it).
 */
export function mergeDraftIntoCart(
  cart: CartLine[],
  draft: SaleDraft,
  products: ReadonlyMap<string, Product>,
): CartLine[] {
  const next = cart.map((line) => ({ ...line }))
  for (const line of draftLines(draft)) {
    const product = products.get(line.producto_id)
    if (!product) continue
    const max = Math.min(product.stock, line.disponible_para_vender)
    if (max <= 0) continue
    const existing = next.find((l) => l.product.id === product.id)
    if (existing) existing.qty = Math.max(existing.qty, Math.min(existing.qty + line.cantidad, max))
    else next.push({ product, qty: Math.min(line.cantidad, max) })
  }
  return next
}
