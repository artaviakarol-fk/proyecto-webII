import { request, requestBlob } from './http'
import type {
  CatalogPage,
  Customer,
  CurrentUser,
  DailyClosing,
  FiadoSale,
  GatewayOption,
  Payment,
  Product,
  PurchaseRequest,
  RegisterClienteRequest,
  Reservation,
  Sale,
  SaleDraft,
  SaleRequest,
  TokenResult,
} from './types'

interface One<T> { data: T }
interface Many<T> { data: T[] }

export const login = (correo: string, contrasena: string) =>
  request<One<TokenResult>>('POST', '/tokens', {
    body: { correo, contrasena, dispositivo: 'cliente-web' },
  }).then((r) => r.data)

export const logout = () => request<void>('DELETE', '/tokens/actual')

export const currentUser = () => request<One<CurrentUser>>('GET', '/usuario').then((r) => r.data)

export const listProducts = (buscar?: string) =>
  request<Many<Product>>('GET', '/productos', { query: { buscar, por_pagina: 50 } }).then(
    (r) => r.data,
  )

export const getProduct = (id: string) =>
  request<One<Product>>('GET', `/productos/${id}`).then((r) => r.data)

export const listCustomers = () =>
  request<Many<Customer>>('GET', '/clientes', { query: { por_pagina: 50 } }).then((r) => r.data)

export const createSale = (body: SaleRequest) =>
  request<One<Sale>>('POST', '/ventas', { body }).then((r) => r.data)

export const chargeCash = (saleId: string) =>
  request<One<Sale>>('POST', `/ventas/${saleId}/cobrar`).then((r) => r.data)

export const chargeFiado = (saleId: string, clienteId: string) =>
  request<One<FiadoSale>>('POST', `/ventas/${saleId}/fiado`, {
    body: { cliente_id: clienteId },
  }).then((r) => r.data)

export const startSinpe = (saleId: string) =>
  request<One<Payment>>('POST', `/ventas/${saleId}/pago`, {
    body: { metodo_pago: 'sinpe_movil' },
  }).then((r) => r.data)

export const getPayment = (saleId: string) =>
  request<One<Payment>>('GET', `/ventas/${saleId}/pago`).then((r) => r.data)

export const dailyClosing = (fecha?: string) =>
  request<One<DailyClosing>>('GET', '/reportes/cierre-diario', { query: { fecha } }).then(
    (r) => r.data,
  )

export const draftSale = (texto: string) =>
  request<One<SaleDraft>>('POST', '/ventas/borrador-ia', { body: { texto } }).then((r) => r.data)

export const registerCliente = (negocioId: string, body: RegisterClienteRequest) =>
  request<One<CurrentUser>>('POST', `/negocios/${negocioId}/registro`, { body }).then((r) => r.data)

export const listCatalog = (buscar?: string, pagina = 1) =>
  request<CatalogPage>('GET', '/catalogo', { query: { buscar, pagina, por_pagina: 24 } })

export const catalogPhoto = (productId: string) => requestBlob(`/catalogo/${productId}/foto`)

export const listReservations = () =>
  request<{ data: Reservation[]; total: number }>('GET', '/reservas')

export const reserve = (productoId: string, cantidad: number) =>
  request<One<Reservation>>('POST', '/reservas', {
    body: { producto_id: productoId, cantidad },
  }).then((r) => r.data)

export const cancelReservation = (reservationId: string) =>
  request<void>('DELETE', `/reservas/${reservationId}`)

export const listGateways = () =>
  request<Many<GatewayOption>>('GET', '/pagos/pasarelas').then((r) => r.data)

export const buy = (body: PurchaseRequest) =>
  request<One<Payment>>('POST', '/compras', { body }).then((r) => r.data)

export const getPurchasePayment = (saleId: string) =>
  request<One<Payment>>('GET', `/compras/${saleId}/pago`).then((r) => r.data)
