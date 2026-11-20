import { request, requestBlob } from './http'
import type {
  CatalogPage,
  CreditPayment,
  Customer,
  CurrentUser,
  DailyClosing,
  ExpiredEntry,
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
  StaffMember,
  StaffRole,
  Permission,
  Subscription,
  SubscriptionPayment,
  Tenant,
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

export const listCatalog = (buscar?: string, pagina = 1, categoria?: string | null) =>
  request<CatalogPage>('GET', '/catalogo', { query: { buscar, pagina, por_pagina: 24, categoria: categoria ?? undefined } })

export const listCatalogCategories = () =>
  request<{ data: string[] }>('GET', '/catalogo/categorias').then((r) => r.data)

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

export const listExpired = () =>
  request<{ data: ExpiredEntry[] }>('GET', '/vencidos').then((r) => r.data)

export const markExpired = (productId: string, cantidad: number, motivo: string) =>
  request<One<ExpiredUnits>>('POST', `/productos/${productId}/vencidos`, {
    body: { cantidad, motivo },
  }).then((r) => r.data)

export const changePassword = (contrasenaActual: string, contrasenaNueva: string) =>
  request<void>('PUT', '/usuario/contrasena', {
    body: { contrasena_actual: contrasenaActual, contrasena_nueva: contrasenaNueva },
  })

// Personal y roles del negocio (solo la administración).
export const listStaff = () => request<Many<StaffMember>>('GET', '/personal').then((r) => r.data)

export const openStaffAccount = (body: { nombre: string; correo: string; contrasena_temporal: string; rol: string }) =>
  request<One<StaffMember>>('POST', '/personal', { body }).then((r) => r.data)

export const changeStaffAccess = (id: string, body: { rol?: string; activo?: boolean }) =>
  request<One<StaffMember>>('PATCH', `/personal/${id}`, { body }).then((r) => r.data)

export const resetStaffPassword = (id: string, contrasenaTemporal: string) =>
  request<One<StaffMember>>('POST', `/personal/${id}/contrasena-temporal`, {
    body: { contrasena_temporal: contrasenaTemporal },
  }).then((r) => r.data)

export const listStaffRoles = () => request<Many<StaffRole>>('GET', '/roles').then((r) => r.data)

export const createStaffRole = (nombre: string, permisos: Permission[]) =>
  request<One<StaffRole>>('POST', '/roles', { body: { nombre, permisos } }).then((r) => r.data)

export const updateStaffRole = (id: string, nombre: string, permisos: Permission[]) =>
  request<One<StaffRole>>('PUT', `/roles/${id}`, { body: { nombre, permisos } }).then((r) => r.data)

export const deleteStaffRole = (id: string) => request<void>('DELETE', `/roles/${id}`)

// Alquiler de la plataforma (la administración del negocio).
export const getSubscription = () => request<One<Subscription>>('GET', '/suscripcion').then((r) => r.data)

export const paySubscription = (body: {
  meses: number
  pasarela: 'onvo' | 'paypal'
  metodo_pago: 'tarjeta' | 'sinpe_movil'
  metodo_proveedor_id: string | null
}) => request<One<SubscriptionPayment>>('POST', '/suscripcion/pagos', { body }).then((r) => r.data)

export const refreshSubscriptionPayment = (id: string) =>
  request<One<SubscriptionPayment>>('GET', `/suscripcion/pagos/${id}`).then((r) => r.data)

// Plataforma (solo superadmin): negocios, su estado y su alquiler.
export const listTenants = (buscar?: string, pagina = 1) =>
  request<Page<Tenant>>('GET', '/plataforma/negocios', { query: { buscar, pagina, por_pagina: 20 } })

export const registerNegocio = (body: {
  nombre: string
  administrador: { nombre: string; correo: string; contrasena_temporal: string }
}) => request<One<Tenant>>('POST', '/plataforma/negocios', { body }).then((r) => r.data)

export const setTenantActive = (id: string, activo: boolean) =>
  request<One<Tenant>>('PATCH', `/plataforma/negocios/${id}`, {
    body: { estado: activo ? 'activo' : 'inactivo' },
  }).then((r) => r.data)

export const registerManualPayment = (id: string, body: { meses: number; metodo: 'efectivo' | 'sinpe'; referencia: string | null }) =>
  request<One<SubscriptionPayment>>('POST', `/plataforma/negocios/${id}/pagos`, { body }).then((r) => r.data)
