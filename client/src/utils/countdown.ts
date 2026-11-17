/** Seconds that are left until an ISO moment, never below zero. */
export function secondsLeft(expiresAt: string, now: number): number {
  return Math.max(0, Math.ceil((new Date(expiresAt).getTime() - now) / 1000))
}

/** Seconds as "mm:ss", the way the cliente sees the time of a reserva. */
export function formatCountdown(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const rest = seconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`
}
