import { describe, expect, it } from 'vitest'

import { digitsOnly, parseExpiry } from '@/utils/card'
import { formatCountdown, secondsLeft } from '@/utils/countdown'

describe('countdown', () => {
  it('counts seconds left and never goes below zero', () => {
    const now = new Date('2026-11-17T10:00:00Z').getTime()
    expect(secondsLeft('2026-11-17T10:15:00Z', now)).toBe(900)
    expect(secondsLeft('2026-11-17T09:59:00Z', now)).toBe(0)
  })

  it('formats as mm:ss', () => {
    expect(formatCountdown(900)).toBe('15:00')
    expect(formatCountdown(65)).toBe('01:05')
    expect(formatCountdown(0)).toBe('00:00')
  })
})

describe('card helpers', () => {
  it('keeps only digits', () => {
    expect(digitsOnly('4242 4242-4242')).toBe('424242424242')
  })

  it('reads MM/AA and MM/AAAA', () => {
    expect(parseExpiry('08/29')).toEqual({ month: 8, year: 2029 })
    expect(parseExpiry('8 / 2030')).toEqual({ month: 8, year: 2030 })
  })

  it('rejects a wrong month or format', () => {
    expect(parseExpiry('13/29')).toBeNull()
    expect(parseExpiry('0829')).toBeNull()
  })
})
