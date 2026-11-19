import type { ServiceStatus } from '@/services/types'

/** How each state of the rent is called and the color of its chip and warning. */
export const SERVICE_STATUS: Record<ServiceStatus, { label: string; tone: '' | 'warn' | 'bad' }> = {
  activo: { label: 'Al día', tone: '' },
  por_vencer: { label: 'Por vencer', tone: 'warn' },
  en_gracia: { label: 'En gracia', tone: 'bad' },
  suspendido: { label: 'Suspendido', tone: 'bad' },
}

/** A date of the API shown as a day in Costa Rica: "19 dic 2026". */
export function formatDay(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('es-CR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Costa_Rica' }).format(new Date(iso))
}

const LAST_PAYMENT_KEY = 'minisuper.lastSubscriptionPayment'

/** The payment of the rent that went to PayPal, so the page PayPal returns to knows which one to follow. */
export const lastSubscriptionPayment = {
  get: (): string | null => {
    try {
      return sessionStorage.getItem(LAST_PAYMENT_KEY)
    } catch {
      return null
    }
  },
  set: (id: string): void => {
    try {
      sessionStorage.setItem(LAST_PAYMENT_KEY, id)
    } catch {
      /* storage unavailable */
    }
  },
  clear: (): void => {
    try {
      sessionStorage.removeItem(LAST_PAYMENT_KEY)
    } catch {
      /* storage unavailable */
    }
  },
}
