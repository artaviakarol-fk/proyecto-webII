<script setup lang="ts">
import { ref } from 'vue'

import { setTextSize, setTheme, textSize, theme, type TextSize } from '@/composables/useAppearance'

const open = ref(false)
const sizes: { value: TextSize; label: string }[] = [
  { value: 'sm', label: 'A-' },
  { value: 'md', label: 'A' },
  { value: 'lg', label: 'A+' },
]
</script>

<template>
  <div class="acc">
    <button
      class="acc-toggle"
      type="button"
      aria-label="Opciones de accesibilidad: tamaño de texto y tema"
      :aria-expanded="open"
      @click="open = !open"
    >
      Aa
    </button>
    <div v-if="open" class="acc-panel">
      <div>
        <div class="acc-t">Tamaño de texto</div>
        <div class="tsz">
          <button
            v-for="size in sizes"
            :key="size.value"
            type="button"
            :class="{ on: textSize === size.value }"
            :aria-pressed="textSize === size.value"
            @click="setTextSize(size.value)"
          >
            {{ size.label }}
          </button>
        </div>
      </div>
      <div>
        <div class="acc-t">Tema</div>
        <button class="full" type="button" @click="setTheme(theme === 'dark' ? 'light' : 'dark')">
          {{ theme === 'dark' ? '☀️ Cambiar a tema claro' : '🌙 Cambiar a tema oscuro' }}
        </button>
      </div>
    </div>
  </div>
</template>
