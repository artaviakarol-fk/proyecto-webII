<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import {
  aids,
  resetAppearance,
  setTheme,
  stepTextScale,
  TEXT_SCALES,
  textScale,
  theme,
  toggleAid,
  type Aid,
} from '@/composables/useAppearance'
import { canSpeak, readableTarget, speak, speaking, stopSpeaking, textOf } from '@/composables/useSpeech'

const open = ref(false)
const panel = ref<HTMLElement | null>(null)
const toggle = ref<HTMLButtonElement | null>(null)

// Each aid with its name and a line icon (paths in a 24×24 box).
const OPTIONS: { aid: Aid | 'dark'; label: string; icon: string[] }[] = [
  { aid: 'dark', label: 'Modo oscuro', icon: ['M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z'] },
  { aid: 'contrast', label: 'Alto contraste', icon: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M12 3v18', 'M12 7h4', 'M12 11h6', 'M12 15h5'] },
  { aid: 'legible', label: 'Letra fácil de leer', icon: ['M4 20l5-14h2l5 14', 'M6.5 14h7', 'M17 20v-6a2 2 0 0 1 4 0v6'] },
  { aid: 'spacing', label: 'Más espacio en el texto', icon: ['M4 8h16', 'M4 16h16', 'M8 4l-4 4 4 4', 'M16 12l4 4-4 4'] },
  { aid: 'links', label: 'Resaltar enlaces', icon: ['M10 14a4 4 0 0 0 6 0l3-3a4 4 0 0 0-6-6l-1 1', 'M14 10a4 4 0 0 0-6 0l-3 3a4 4 0 0 0 6 6l1-1'] },
  { aid: 'cursor', label: 'Cursor grande', icon: ['M5 3l14 8-6 1.5L16 20l-3 1.5-3-7.5-5 4z'] },
  { aid: 'still', label: 'Pausar animaciones', icon: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M10 9v6', 'M14 9v6'] },
  { aid: 'guide', label: 'Guía de lectura', icon: ['M3 9h18v6H3z', 'M7 9v2', 'M11 9v3', 'M15 9v2', 'M19 9v3'] },
  ...(canSpeak()
    ? [{ aid: 'speech' as const, label: 'Leer en voz alta', icon: ['M4 9v6h4l5 4V5L8 9z', 'M16 9a4 4 0 0 1 0 6', 'M19 6a8 8 0 0 1 0 12'] }]
    : []),
]

const isOn = (aid: Aid | 'dark') => (aid === 'dark' ? theme.value === 'dark' : aids[aid])

function choose(aid: Aid | 'dark') {
  if (aid === 'dark') setTheme(theme.value === 'dark' ? 'light' : 'dark')
  else toggleAid(aid)
  if (aid === 'speech') {
    if (aids.speech) speak('Lectura en voz alta activada. Toque o recorra con el teclado cualquier texto para escucharlo.')
    else stopSpeaking()
  }
}

/** Reads the main content of the page, from its title down. */
function readPage() {
  const main = document.querySelector('main') ?? document.body
  speak(main.innerText)
}

// With "Leer en voz alta" on, what is touched or reached with the keyboard is read.
function onReadClick(event: MouseEvent) {
  if (!aids.speech) return
  const target = readableTarget(event.target)
  if (target) speak(textOf(target))
}
function onReadFocus(event: FocusEvent) {
  if (!aids.speech) return
  const target = readableTarget(event.target)
  if (target) speak(textOf(target))
}

async function show(value: boolean) {
  open.value = value
  await nextTick()
  if (value) panel.value?.querySelector<HTMLButtonElement>('button')?.focus()
  else toggle.value?.focus()
}

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) void show(false)
}

// The reading guide: a band that follows the pointer.
const guideTop = ref(-100)
function onMove(event: MouseEvent) {
  guideTop.value = event.clientY
}

onMounted(() => {
  document.addEventListener('keydown', onKey)
  document.addEventListener('mousemove', onMove)
  document.addEventListener('click', onReadClick, true)
  document.addEventListener('focusin', onReadFocus)
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  document.removeEventListener('mousemove', onMove)
  document.removeEventListener('click', onReadClick, true)
  document.removeEventListener('focusin', onReadFocus)
  stopSpeaking()
})
</script>

<template>
  <div v-if="aids.guide" class="reading-guide" :style="{ top: `${guideTop}px` }" aria-hidden="true" />
  <div class="acc">
    <button
      ref="toggle"
      class="acc-toggle"
      type="button"
      aria-label="Accesibilidad"
      :aria-expanded="open"
      aria-controls="acc-panel"
      @click="show(!open)"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10" />
        <circle cx="12" cy="7" r="1.4" fill="currentColor" />
        <path d="M7.5 10l4.5 1 4.5-1M12 11v4M12 15l-2.5 4M12 15l2.5 4" />
      </svg>
    </button>
    <div v-if="open" id="acc-panel" ref="panel" class="acc-panel" role="dialog" aria-label="Opciones de accesibilidad">
      <div class="acc-head">
        <b>Accesibilidad</b>
        <button class="acc-close" type="button" aria-label="Cerrar" @click="show(false)">×</button>
      </div>
      <div class="acc-size">
        <span id="acc-size-label">Tamaño del texto</span>
        <button type="button" aria-label="Letra más pequeña" :disabled="textScale === TEXT_SCALES[0]" @click="stepTextScale(-1)">A−</button>
        <b aria-live="polite" aria-labelledby="acc-size-label">{{ textScale }}%</b>
        <button type="button" aria-label="Letra más grande" :disabled="textScale === TEXT_SCALES[TEXT_SCALES.length - 1]" @click="stepTextScale(1)">A+</button>
      </div>
      <div class="acc-grid">
        <button
          v-for="option in OPTIONS"
          :key="option.aid"
          type="button"
          class="acc-opt"
          :class="{ on: isOn(option.aid) }"
          :aria-pressed="isOn(option.aid)"
          @click="choose(option.aid)"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path v-for="d in option.icon" :key="d" :d="d" />
          </svg>
          {{ option.label }}
        </button>
      </div>
      <div v-if="canSpeak()" class="acc-read">
        <button type="button" @click="speaking ? stopSpeaking() : readPage()">
          {{ speaking ? 'Detener la lectura' : 'Leer esta página' }}
        </button>
      </div>
      <button class="acc-reset" type="button" @click="stopSpeaking(); resetAppearance()">Restablecer todo</button>
    </div>
  </div>
</template>
