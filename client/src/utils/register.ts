import type { Sale, SaleSummary } from '@/services/types'

/** The longest reference (authorization or voucher number) the API keeps. */
export const MAX_REFERENCE = 60

/** An authorization code like the one a datáfono prints: "AUT-" and six digits. */
export function authorizationCode(): string {
  const digits = new Uint32Array(1)
  crypto.getRandomValues(digits)
  return `AUT-${String(digits[0]! % 1_000_000).padStart(6, '0')}`
}

const METHODS: Record<string, string> = {
  efectivo: 'Efectivo',
  fiado: 'Fiado',
  tarjeta: 'Tarjeta',
  sinpe_movil: 'SINPE Móvil',
}

/** How a sale was paid, saying where a card or SINPE payment was taken: the register or a payment provider. */
export function paymentLabel(
  sale: Pick<Sale | SaleSummary, 'metodo_pago' | 'origen_pago'>,
): string {
  const method = METHODS[sale.metodo_pago] ?? sale.metodo_pago
  if (sale.metodo_pago !== 'tarjeta' && sale.metodo_pago !== 'sinpe_movil') return method
  if (sale.origen_pago === 'pasarela') return `${method} (pasarela)`
  return sale.metodo_pago === 'tarjeta' ? `${method} (datáfono)` : `${method} (recibido en caja)`
}
