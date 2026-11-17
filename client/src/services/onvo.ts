const PAYMENT_METHODS_URL = 'https://api.onvopay.com/v1/payment-methods'

export interface CardData {
  holderName: string
  number: string
  expMonth: number
  expYear: number
  cvv: string
}

export interface SinpeData {
  identification: string
  phone: string
}

export interface Billing {
  name: string
  email: string
}

/**
 * ONVO is asked from the browser with the PUBLISHABLE key, so card data goes straight to ONVO and never reaches our API.
 * The API only receives the id of the payment method that ONVO answers.
 */
async function createMethod(publishableKey: string, body: unknown): Promise<string> {
  let response: Response
  try {
    response = await fetch(PAYMENT_METHODS_URL, {
      method: 'POST',
      headers: { Authorization: `Bearer ${publishableKey}`, 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
  } catch {
    throw new Error('No se pudo conectar con ONVO. Revise su conexión.')
  }

  const payload = (await response.json().catch(() => null)) as { id?: unknown } | null
  if (!response.ok || typeof payload?.id !== 'string') {
    throw new Error('ONVO no aceptó los datos. Revíselos e intente de nuevo.')
  }
  return payload.id
}

export function createCardMethod(publishableKey: string, card: CardData, billing: Billing): Promise<string> {
  return createMethod(publishableKey, {
    type: 'card',
    card,
    billing: { name: billing.name, address: { country: 'CR' } },
    customer: { name: billing.name, email: billing.email },
  })
}

export function createSinpeMethod(publishableKey: string, sinpe: SinpeData, billing: Billing): Promise<string> {
  return createMethod(publishableKey, {
    type: 'mobile_number',
    mobileNumber: { identification: sinpe.identification, identificationType: 0, number: sinpe.phone },
    billing,
  })
}
