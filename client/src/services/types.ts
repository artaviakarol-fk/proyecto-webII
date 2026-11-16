import type { components } from './schema'

type Schemas = components['schemas']

export type Product = Schemas['ProductResource']
export type Customer = Schemas['CustomerResource']
export type Sale = Schemas['SaleResource']
export type SaleRequest = Schemas['SaleRequest']
export type FiadoSale = Schemas['FiadoSaleResource']
export type Payment = Schemas['PaymentResource']
export type DailyClosing = Schemas['DailyClosingResource']
export type CurrentUser = Schemas['CurrentUserResource']
export type TokenResult = Schemas['TokenResource']

export type Ability = 'administrar' | 'caja' | 'inventario'
