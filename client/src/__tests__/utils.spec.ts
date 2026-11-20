import { describe, expect, it } from 'vitest'

import { digitsOnly, nationalId, parseExpiry, sinpePhone } from '@/utils/card'
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

describe('SINPE Móvil data for ONVO', () => {
  it('sends the phone with the country code', () => {
    expect(sinpePhone('+50688888888')).toBe('+50688888888')
    expect(sinpePhone('8888-8888')).toBe('+50688888888')
    expect(sinpePhone('+506 8888 4444')).toBe('+50688884444')
  })

  it('sends a national id with dashes as 0#-####-####', () => {
    expect(nationalId('208820914')).toBe('02-0882-0914')
    expect(nationalId('2-0882-0914')).toBe('02-0882-0914')
    expect(nationalId('02-0882-0914')).toBe('02-0882-0914')
  })

  it('leaves an id that is not a national one as it was typed', () => {
    expect(nationalId(' 155812345678 ')).toBe('155812345678')
  })
})
