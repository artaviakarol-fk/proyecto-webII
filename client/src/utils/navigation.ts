/** Sends the browser to another site (the approval page of PayPal). Kept apart so tests can replace it. */
export function goTo(url: string): void {
  window.location.assign(url)
}

const LAST_SALE_KEY = 'minisuper.lastSale'

/** The sale the cliente just paid, so the page PayPal returns to knows which payment to follow. */
export const lastSale = {
  get: (): string | null => {
    try {
      return sessionStorage.getItem(LAST_SALE_KEY)
    } catch {
      return null
    }
  },
  set: (saleId: string): void => {
    try {
      sessionStorage.setItem(LAST_SALE_KEY, saleId)
    } catch {
      /* storage unavailable */
    }
  },
}
