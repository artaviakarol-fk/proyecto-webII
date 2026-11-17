/** Keeps only the digits of what the cliente typed (spaces and dashes are allowed while typing). */
export function digitsOnly(value: string): string {
  return value.replace(/\D/g, '')
}

/** Reads "MM/AA" or "MM/AAAA" into month and four-digit year; null when it is not a valid date format. */
export function parseExpiry(value: string): { month: number; year: number } | null {
  const match = /^\s*(\d{1,2})\s*\/\s*(\d{2}|\d{4})\s*$/.exec(value)
  if (!match) return null
  const month = Number(match[1])
  const year = match[2]!.length === 2 ? 2000 + Number(match[2]) : Number(match[2])
  return month >= 1 && month <= 12 ? { month, year } : null
}
