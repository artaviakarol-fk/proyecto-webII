import { request, requestBlob } from './http'
import type {
  CatalogPage,
  CreditPayment,
  Customer,
  CurrentUser,
  DailyClosing,
  ExpiredUnits,
  FiadoSale,
  GatewayOption,
  Payment,
  Product,
  PurchaseRequest,
  RegisterClienteRequest,
  Page,
  Receipt,
  Reservation,
  Sale,
  SaleStatus,
  SaleSummary,
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

export const listCustomers = (buscar?: string) =>
  request<Many<Customer>>('GET', '/clientes', { query: { buscar, por_pagina: 50 } }).then(
    (r) => r.data,
  )

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

export const listSales = (filters: { estado?: SaleStatus; fecha?: string; pagina?: number } = {}) =>
  request<Page<SaleSummary>>('GET', '/ventas', {
    query: { estado: filters.estado, fecha: filters.fecha, pagina: filters.pagina ?? 1, por_pagina: 15 },
  })

export const getSale = (saleId: string) =>
  request<One<Sale>>('GET', `/ventas/${saleId}`).then((r) => r.data)

export const cancelSale = (saleId: string) =>
  request<One<Sale>>('POST', `/ventas/${saleId}/anular`).then((r) => r.data)

export const receiveCreditPayment = (customerId: string, monto: number) =>
  request<One<CreditPayment>>('POST', `/clientes/${customerId}/abonos`, {
    body: { monto, metodo_pago: 'efectivo' },
  }).then((r) => r.data)

export const grantCreditIncrease = (customerId: string, monto: number, motivo: string) =>
  request<unknown>('POST', `/clientes/${customerId}/ampliaciones`, { body: { monto, motivo } })

export const registerReceipt = (body: {
  proveedor: string
  fecha: string
  lineas: { producto_id: string; cantidad: number; costo_unitario: number }[]
}) => request<One<Receipt>>('POST', '/entradas', { body }).then((r) => r.data)

export const markExpired = (productId: string, cantidad: number, motivo: string) =>
  request<One<ExpiredUnits>>('POST', `/productos/${productId}/vencidos`, {
    body: { cantidad, motivo },
  }).then((r) => r.data)
