import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

import AccessibilityMenu from '@/components/AccessibilityMenu.vue'
import { resetAppearance } from '@/composables/useAppearance'

describe('accessibility menu', () => {
  beforeEach(() => {
    localStorage.clear()
    resetAppearance()
  })

  it('opens the panel from the accessibility button', async () => {
    const w = mount(AccessibilityMenu, { attachTo: document.body })
    expect(w.find('.acc-panel').exists()).toBe(false)
    await w.find('.acc-toggle').trigger('click')
    expect(w.find('.acc-panel').exists()).toBe(true)
    expect(w.findAll('.acc-opt')).toHaveLength(8)
    w.unmount()
  })

  it('makes the text bigger in steps and remembers it', async () => {
    const w = mount(AccessibilityMenu)
    await w.find('.acc-toggle').trigger('click')
    await w.find('[aria-label="Letra más grande"]').trigger('click')
    expect(document.documentElement.style.fontSize).toBe('115%')
    expect(localStorage.getItem('minisuper.textScale')).toBe('115')
  })

  it('turns an aid on and marks it as pressed', async () => {
    const w = mount(AccessibilityMenu)
    await w.find('.acc-toggle').trigger('click')
    const contrast = w.findAll('.acc-opt').find((b) => b.text() === 'Alto contraste')!
    await contrast.trigger('click')
    expect(document.documentElement.dataset.contrast).toBe('')
    expect(contrast.attributes('aria-pressed')).toBe('true')
  })

  it('never paints dark theme and high contrast at the same time', async () => {
    const w = mount(AccessibilityMenu)
    await w.find('.acc-toggle').trigger('click')
    await w.findAll('.acc-opt').find((b) => b.text() === 'Alto contraste')!.trigger('click')
    await w.findAll('.acc-opt').find((b) => b.text() === 'Modo oscuro')!.trigger('click')
    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(document.documentElement.dataset.contrast).toBeUndefined()
  })

  it('puts everything back with Restablecer todo', async () => {
    const w = mount(AccessibilityMenu)
    await w.find('.acc-toggle').trigger('click')
    await w.findAll('.acc-opt').find((b) => b.text() === 'Guía de lectura')!.trigger('click')
    expect(w.find('.reading-guide').exists()).toBe(true)
    await w.find('.acc-reset').trigger('click')
    expect(w.find('.reading-guide').exists()).toBe(false)
    expect(document.documentElement.style.fontSize).toBe('')
  })
})

describe('reading aloud', () => {
  it('reads a text aloud in one go, without extra spaces', async () => {
    const spoken: string[] = []
    const synth = { cancel: () => {}, speak: (u: { text: string }) => spoken.push(u.text), getVoices: () => [] }
    Object.defineProperty(window, 'speechSynthesis', { value: synth, configurable: true })
    ;(globalThis as Record<string, unknown>).SpeechSynthesisUtterance = class {
      text: string
      constructor(text: string) {
        this.text = text
      }
    }
    const { speak } = await import('@/composables/useSpeech')
    speak('  Hola   mundo ')
    expect(spoken).toEqual(['Hola mundo'])
  })
})

describe('self-typing word of the login', () => {
  it('stays still on the last word when animations are paused', async () => {
    const { aids } = await import('@/composables/useAppearance')
    const TypedWord = (await import('@/components/TypedWord.vue')).default
    aids.still = true
    const w = mount(TypedWord, { props: { words: ['arroz', 'compra'], still: 'compra' } })
    await nextTick()
    expect(w.text()).toBe('compra')
    const emitted = w.emitted('word') ?? []
    expect(emitted[emitted.length - 1]).toEqual([1])
    expect(w.attributes('aria-hidden')).toBe('true')
    aids.still = false
    w.unmount()
  })
})
