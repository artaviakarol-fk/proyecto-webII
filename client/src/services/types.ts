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

export type Ability = 'administrar' | 'caja' | 'inventario' | 'comprar' | 'plataforma'

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

/** The three permissions a role of the staff can have. */
export type Permission = 'administrar' | 'caja' | 'inventario'

/** A role of the staff: the four by default and the ones the negocio created (GET /roles). */
export interface StaffRole {
  id: string
  nombre: string
  permisos: Permission[]
  predeterminado: boolean
  en_uso: number
}

/** An account of the staff of the negocio (GET /personal). */
export interface StaffMember {
  id: string
  nombre: string
  correo: string
  rol: string
  rol_id: string | null
  rol_nombre: string
  permisos: string[]
  activo: boolean
  debe_cambiar_contrasena: boolean
}

export type ServiceStatus = 'activo' | 'por_vencer' | 'en_gracia' | 'suspendido'

/** A payment of the rent of the platform. */
export interface SubscriptionPayment {
  id: string
  monto: number
  meses: number
  metodo: 'efectivo' | 'sinpe' | 'tarjeta' | 'sinpe_movil'
  proveedor: 'manual' | 'onvo' | 'paypal'
  estado: 'pendiente' | 'aprobado' | 'rechazado'
  referencia: string | null
  creado_el: string
  url_aprobacion: string | null
  instrucciones: { numero_sinpe: string; monto: number; mensaje: string } | null
}

/** The rent of the negocio (GET /suscripcion). */
export interface Subscription {
  estado: ServiceStatus
  vence_el: string
  dias_restantes: number
  en_prueba: boolean
  precio_mensual: number
  aviso: string | null
  pagos: SubscriptionPayment[]
}

/** A negocio as the platform sees it: never its sales, customers, fiado or stock. */
export interface Tenant {
  id: string
  nombre: string
  estado: 'activo' | 'inactivo'
  creado_el: string
  administradores: { nombre: string; correo: string; activo: boolean; debe_cambiar_contrasena: boolean }[]
  servicio: { estado: ServiceStatus | null; vence_el: string | null; dias_restantes: number | null; en_prueba: boolean }
}
