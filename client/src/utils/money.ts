const formatter = new Intl.NumberFormat('es-CR', {
  style: 'currency',
  currency: 'CRC',
  maximumFractionDigits: 0,
})

export function formatMoney(value: number): string {
  return formatter.format(value)
}
