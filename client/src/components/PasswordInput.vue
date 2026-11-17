<script setup lang="ts">
import { ref } from 'vue'

defineProps<{ placeholder?: string; autocomplete?: string; minlength?: number }>()
const model = defineModel<string>({ required: true })
const visible = ref(false)
</script>

<template>
  <span class="pwd">
    <input
      v-model="model"
      :type="visible ? 'text' : 'password'"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :minlength="minlength"
      required
    />
    <button
      type="button"
      class="pwd-toggle"
      :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
      :aria-pressed="visible"
      @click="visible = !visible"
    >
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="visible">
          <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
          <circle cx="12" cy="12" r="3" />
        </template>
        <template v-else>
          <path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94" />
          <path d="M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 7 11 7a19.9 19.9 0 0 1-3.17 4.19" />
          <path d="M1 1l22 22" />
        </template>
      </svg>
    </button>
  </span>
</template>
