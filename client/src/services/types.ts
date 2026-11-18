import type { components } from './schema'

type Schemas = components['schemas']

export type Product = Schemas['ProductResource']
export type Customer = Schemas['CustomerResource']
export type Sale = Schemas['SaleResource']
export type SaleSummary = Schemas['SaleSummaryResource']
export type CreditPayment = Schemas['CreditPaymentResource']
export type Receipt = Schemas['ReceiptResource']
export type ExpiredUnits = Schemas['ExpiredUnitsResource']

/** Units found expired that are still on the shelf (GET /vencidos). */
export interface ExpiredEntry {
  id: string
  producto_id: string
  codigo: string
  producto: string
  cantidad: number
  motivo: string
  registrada_por: string
  registrada_en: string
}
export type SaleRequest = Schemas['SaleRequest']
export type FiadoSale = Schemas['FiadoSaleResource']
export type Payment = Schemas['PaymentResource']
export type DailyClosing = Schemas['DailyClosingResource']
export type SaleDraft = Schemas['SaleDraftResource']
export type CurrentUser = Schemas['CurrentUserResource']
export type TokenResult = Schemas['TokenResource']
export type CatalogProduct = Schemas['CatalogProductResource']
export type Reservation = Schemas['ReservationResource']
export type PurchaseRequest = Schemas['PurchaseRequest']
export type RegisterClienteRequest = Schemas['RegisterClienteRequest']

export type GatewayId = PurchaseRequest['pasarela']
export type PayMethod = PurchaseRequest['metodo_pago']

export type Ability = 'administrar' | 'caja' | 'inventario' | 'comprar'

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

/**
 * The contract types the items of `GET /pagos/pasarelas` as `unknown[]`, because the API builds them with array_map.
 * This is their real shape (PaymentGateways::options); replace it when the contract types them.
 */
export interface GatewayOption {
  proveedor: GatewayId
  nombre: string
  metodos: PayMethod[]
  requiere_metodo_cliente: boolean
  llave_publica: string | null
  configurada: boolean
  predeterminada: boolean
}

export interface CatalogPage {
  data: CatalogProduct[]
  meta: { pagina: number; por_pagina: number; total: number; ultima_pagina: number }
}

export interface Page<T> {
  data: T[]
  meta: { pagina: number; por_pagina: number; total: number; ultima_pagina: number }
}

export type SaleStatus = Schemas['SaleStatus']
