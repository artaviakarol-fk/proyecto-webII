const BASE_URL = '/api/v1'
const TOKEN_KEY = 'minisuper.token'

export interface Problem {
  title?: string
  detail?: string
  status?: number
  errors?: Record<string, string[]>
  errores?: Record<string, string[]>
}

export class ApiError extends Error {
  readonly status: number
  readonly problem?: Problem

  constructor(status: number, message: string, problem?: Problem) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.problem = problem
  }
}

let onUnauthorized: (() => void) | null = null

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

export const tokenStorage = {
  get: (): string | null => {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set: (token: string): void => {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      /* storage unavailable */
    }
  },
  clear: (): void => {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* storage unavailable */
    }
  },
}

type Query = Record<string, string | number | null | undefined>

export async function request<T>(
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  path: string,
  options: { body?: unknown; query?: Query } = {},
): Promise<T> {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(options.query ?? {})) {
    if (value !== undefined && value !== null && value !== '') params.set(key, String(value))
  }
  const qs = params.toString()

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'
  const token = tokenStorage.get()
  if (token) headers.Authorization = `Bearer ${token}`

  let response: Response
  try {
    const init: RequestInit = { method, headers }
    if (options.body !== undefined) init.body = JSON.stringify(options.body)
    response = await fetch(`${BASE_URL}${path}${qs ? `?${qs}` : ''}`, init)
  } catch {
    throw new ApiError(0, 'No se pudo conectar con el servidor. Revise su conexión.')
  }

  if (response.status === 204) return undefined as T

  let payload: unknown = null
  try {
    payload = await response.json()
  } catch {
    payload = null
  }

  if (!response.ok) {
    const problem = (payload ?? undefined) as Problem | undefined
    if (response.status === 401 && token) onUnauthorized?.()
    throw new ApiError(
      response.status,
      problem?.detail ?? problem?.title ?? `Error ${response.status}`,
      problem,
    )
  }
  return payload as T
}

/** Downloads a protected file (a product photo) with the token, since an <img> cannot send the header. */
export async function requestBlob(path: string): Promise<Blob> {
  const headers: Record<string, string> = {}
  const token = tokenStorage.get()
  if (token) headers.Authorization = `Bearer ${token}`

  let response: Response
  try {
    response = await fetch(`${BASE_URL}${path}`, { headers })
  } catch {
    throw new ApiError(0, 'No se pudo conectar con el servidor. Revise su conexión.')
  }
  if (!response.ok) {
    if (response.status === 401 && token) onUnauthorized?.()
    throw new ApiError(response.status, `Error ${response.status}`)
  }
  return response.blob()
}
