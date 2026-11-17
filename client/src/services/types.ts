import type { components } from './schema'

type Schemas = components['schemas']

export type Product = Schemas['ProductResource']
export type Customer = Schemas['CustomerResource']
export type Sale = Schemas['SaleResource']
export type SaleRequest = Schemas['SaleRequest']
export type FiadoSale = Schemas['FiadoSaleResource']
export type Payment = Schemas['PaymentResource']
export type DailyClosing = Schemas['DailyClosingResource']
export type SaleDraft = Schemas['SaleDraftResource']
export type CurrentUser = Schemas['CurrentUserResource']
export type TokenResult = Schemas['TokenResource']

export type Ability = 'administrar' | 'caja' | 'inventario'

/**
 * The contract types `lineas` and `no_encontrados` of the AI draft as `unknown[]`, because the API builds them
 * with array_map. These are their real shapes (SaleDraftResource.php); replace them when the contract types them.
 */
export interface DraftLine {
  producto_id: string
  nombre: string
  mencionado: string
  cantidad: number
  precio_unitario: number
  subtotal: number
  disponible_para_vender: number
  alcanza: boolean
}

export interface DraftNotFound {
  mencionado: string
  cantidad: number
}
