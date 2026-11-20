import { reactive, ref } from 'vue'

export type Theme = 'light' | 'dark'

/** The aids a person can turn on from the accessibility button. */
export interface Aids {
  contrast: boolean
  legible: boolean
  spacing: boolean
  links: boolean
  cursor: boolean
  still: boolean
  guide: boolean
  speech: boolean
}

export type Aid = keyof Aids

const THEME_KEY = 'minisuper.theme'
const SCALE_KEY = 'minisuper.textScale'
const AIDS_KEY = 'minisuper.aids'
const OLD_SIZE_KEY = 'minisuper.textSize'

/** The sizes of the text, in percent of the normal size. */
export const TEXT_SCALES = [85, 100, 115, 130, 150] as const

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string): void {
  try {
    localStorage.setItem(key, value)
  } catch {
    /* storage unavailable: the choice lasts until the page is closed */
  }
}

function savedScale(): number {
  const saved = Number(read(SCALE_KEY))
  if (TEXT_SCALES.includes(saved as (typeof TEXT_SCALES)[number])) return saved
  // The menu before had three sizes; keep what the person had chosen.
  const old = read(OLD_SIZE_KEY)
  return old === 'sm' ? 85 : old === 'lg' ? 115 : 100
}

function savedAids(): Aids {
  const none: Aids = { contrast: false, legible: false, spacing: false, links: false, cursor: false, still: false, guide: false, speech: false }
  try {
    const parsed: unknown = JSON.parse(read(AIDS_KEY) ?? '{}')
    if (typeof parsed !== 'object' || parsed === null) return none
    for (const key of Object.keys(none) as Aid[]) none[key] = (parsed as Record<string, unknown>)[key] === true
  } catch {
    /* nothing saved or unreadable: start without aids */
  }
  return none
}

// Shared by the whole app and kept in this browser.
export const theme = ref<Theme>(read(THEME_KEY) === 'dark' ? 'dark' : 'light')
export const textScale = ref<number>(savedScale())
export const aids = reactive<Aids>(savedAids())

/** Puts the choices on the <html> tag, where the CSS reads them. */
export function applyAppearance(): void {
  const html = document.documentElement
  html.dataset.theme = theme.value
  html.style.fontSize = textScale.value === 100 ? '' : `${textScale.value}%`
  for (const key of Object.keys(aids) as Aid[]) {
    if (aids[key]) html.dataset[key] = ''
    else delete html.dataset[key]
  }
}

export function setTheme(value: Theme): void {
  theme.value = value
  // The dark theme and the high contrast are two ways of painting the page: only one at a time.
  if (value === 'dark') aids.contrast = false
  write(THEME_KEY, value)
  write(AIDS_KEY, JSON.stringify(aids))
  applyAppearance()
}

/** One step bigger (1) or smaller (-1). */
export function stepTextScale(step: 1 | -1): void {
  const index = TEXT_SCALES.indexOf(textScale.value as (typeof TEXT_SCALES)[number])
  const next = TEXT_SCALES[Math.min(TEXT_SCALES.length - 1, Math.max(0, index + step))] ?? 100
  textScale.value = next
  write(SCALE_KEY, String(next))
  applyAppearance()
}

export function toggleAid(aid: Aid): void {
  aids[aid] = !aids[aid]
  if (aid === 'contrast' && aids.contrast && theme.value === 'dark') {
    theme.value = 'light'
    write(THEME_KEY, 'light')
  }
  write(AIDS_KEY, JSON.stringify(aids))
  applyAppearance()
}

/** Back to how the page looks for everybody. */
export function resetAppearance(): void {
  theme.value = 'light'
  textScale.value = 100
  for (const key of Object.keys(aids) as Aid[]) aids[key] = false
  write(THEME_KEY, 'light')
  write(SCALE_KEY, '100')
  write(AIDS_KEY, JSON.stringify(aids))
  applyAppearance()
}
