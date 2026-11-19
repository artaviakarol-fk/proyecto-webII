/** Keeps only the digits of what the cliente typed (spaces and dashes are allowed while typing). */
export function digitsOnly(value: string): string {
  return value.replace(/\D/g, '')
}

/** ONVO wants the SINPE Móvil number with the country code: "8888 8888" or "+506 8888-8888" become "+50688888888". */
export function sinpePhone(value: string): string {
  return `+506${digitsOnly(value).slice(-8)}`
}

/**
 * ONVO wants a national id (cédula física) with dashes, as 0#-####-####: "208820914" or "2-0882-0914" become
 * "02-0882-0914". Anything that is not a national id is sent as it was typed.
 */
export function nationalId(value: string): string {
  const digits = digitsOnly(value)
  if (digits.length < 9 || digits.length > 10) return value.trim()
  const padded = digits.padStart(10, '0')
  return `${padded.slice(0, 2)}-${padded.slice(2, 6)}-${padded.slice(6)}`
}

/** Reads "MM/AA" or "MM/AAAA" into month and four-digit year; null when it is not a valid date format. */
export function parseExpiry(value: string): { month: number; year: number } | null {
  const match = /^\s*(\d{1,2})\s*\/\s*(\d{2}|\d{4})\s*$/.exec(value)
  if (!match) return null
  const month = Number(match[1])
  const year = match[2]!.length === 2 ? 2000 + Number(match[2]) : Number(match[2])
  return month >= 1 && month <= 12 ? { month, year } : null
}
