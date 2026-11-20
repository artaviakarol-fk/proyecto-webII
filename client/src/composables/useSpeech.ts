import { ref } from 'vue'

/** Whether this browser can read aloud (Web Speech API); every current browser can. */
export function canSpeak(): boolean {
  return typeof window !== 'undefined' && 'speechSynthesis' in window
}

export const speaking = ref(false)

/** The Spanish voice of the device, preferring Costa Rica, then Latin America, then any Spanish. */
function spanishVoice(): SpeechSynthesisVoice | null {
  const voices = window.speechSynthesis.getVoices()
  return (
    voices.find((v) => v.lang === 'es-CR') ??
    voices.find((v) => v.lang === 'es-MX' || v.lang === 'es-US' || v.lang === 'es-419') ??
    voices.find((v) => v.lang.startsWith('es')) ??
    null
  )
}

/** Reads a text aloud, stopping whatever was being read. */
export function speak(text: string): void {
  if (!canSpeak()) return
  const clean = text.replace(/\s+/g, ' ').trim()
  if (clean === '') return
  window.speechSynthesis.cancel()
  const utterance = new SpeechSynthesisUtterance(clean)
  utterance.lang = 'es-CR'
  const voice = spanishVoice()
  if (voice) utterance.voice = voice
  utterance.rate = 0.95
  utterance.onstart = () => (speaking.value = true)
  utterance.onend = () => (speaking.value = false)
  utterance.onerror = () => (speaking.value = false)
  window.speechSynthesis.speak(utterance)
}

export function stopSpeaking(): void {
  if (!canSpeak()) return
  window.speechSynthesis.cancel()
  speaking.value = false
}

/** What a person would hear for an element: its label if it has one, otherwise its visible text. */
export function textOf(element: Element): string {
  const label = element.getAttribute('aria-label')
  if (label) return label
  if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement) {
    const field = element.closest('label')?.textContent ?? element.placeholder
    return `${field ?? ''} ${element.type === 'password' ? '' : element.value}`
  }
  if (element instanceof HTMLImageElement) return element.alt
  return element.textContent ?? ''
}

const READABLE = 'h1, h2, h3, h4, p, li, label, button, a, td, th, dt, dd, img, input, select, textarea, [aria-label], .pprice, .pname'

/** The closest element worth reading for a click or a focus. */
export function readableTarget(target: EventTarget | null): Element | null {
  return target instanceof Element ? target.closest(READABLE) : null
}
