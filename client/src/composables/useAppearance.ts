import { ref } from 'vue'

export type Theme = 'light' | 'dark'
export type TextSize = 'sm' | 'md' | 'lg'

const THEME_KEY = 'minisuper.theme'
const SIZE_KEY = 'minisuper.textSize'

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

const savedTheme = read(THEME_KEY)
const savedSize = read(SIZE_KEY)

// Shared by the whole app: the theme and the text size chosen with the "Aa" button.
export const theme = ref<Theme>(savedTheme === 'dark' ? 'dark' : 'light')
export const textSize = ref<TextSize>(savedSize === 'sm' || savedSize === 'lg' ? savedSize : 'md')

/** Puts the saved choices on the <html> tag, where the CSS reads them. */
export function applyAppearance(): void {
  document.documentElement.dataset.theme = theme.value
  document.documentElement.dataset.textSize = textSize.value
}

export function setTheme(value: Theme): void {
  theme.value = value
  write(THEME_KEY, value)
  applyAppearance()
}

export function setTextSize(value: TextSize): void {
  textSize.value = value
  write(SIZE_KEY, value)
  applyAppearance()
}
