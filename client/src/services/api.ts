import { request } from './http'
import type {
  Customer,
  CurrentUser,
  DailyClosing,
  FiadoSale,
  Payment,
  Product,
  Sale,
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
