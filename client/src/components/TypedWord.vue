<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { aids } from '@/composables/useAppearance'

/**
 * Writes the words one letter at a time, erases them and goes on with the next one. It is only decoration: who uses a
 * screen reader hears the whole sentence once, and with "Pausar animaciones" (or the system's reduced motion) the
 * last word stays still.
 */
const props = withDefaults(defineProps<{ words: string[]; still?: string }>(), { still: undefined })
// Which word is being written, so the sentence around it can agree with it.
const emit = defineEmits<{ word: [index: number] }>()

const shown = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
let word = 0
let letters = 0
let erasing = false

const reduced = () => aids.still || (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches)

function tick() {
  if (reduced()) {
    const index = props.still === undefined ? props.words.length - 1 : props.words.indexOf(props.still)
    shown.value = props.still ?? props.words[index] ?? ''
    emit('word', index)
    return
  }
  if (letters === 0 && !erasing) emit('word', word)
  const current = props.words[word] ?? ''
  if (!erasing) {
    letters++
    shown.value = current.slice(0, letters)
    if (letters >= current.length) {
      erasing = true
      timer = setTimeout(tick, 1400)
      return
    }
  } else {
    letters--
    shown.value = current.slice(0, letters)
    if (letters <= 0) {
      erasing = false
      word = (word + 1) % props.words.length
    }
  }
  timer = setTimeout(tick, erasing ? 60 : 110)
}

function restart() {
  clearTimeout(timer)
  word = 0
  letters = 0
  erasing = false
  tick()
}

watch(() => aids.still, restart)
onMounted(restart)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <span class="typed" aria-hidden="true">{{ shown }}</span>
</template>

<style scoped>
.typed { color: #f6b48c; border-right: 0.08em solid #f6b48c; padding-right: 0.05em; white-space: nowrap; }
</style>
