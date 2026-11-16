<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()
const route = useRoute()

const correo = ref('')
const contrasena = ref('')
const mostrar = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  loading.value = true
  error.value = null
  try {
    await session.login(correo.value, contrasena.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/caja'
    await router.push(redirect.startsWith('/') ? redirect : '/caja')
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="card login" @submit.prevent="submit">
    <h1>Iniciar sesión</h1>
    <label>Correo <input v-model="correo" type="email" required autocomplete="username" /></label>
    <label>
      Contraseña
      <span class="pass">
        <input
          v-model="contrasena"
          :type="mostrar ? 'text' : 'password'"
          required
          autocomplete="current-password"
        />
        <button
          type="button"
          class="eye"
          :aria-label="mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'"
          :aria-pressed="mostrar"
          @click="mostrar = !mostrar"
        >
          {{ mostrar ? '🙈' : '👁️' }}
        </button>
      </span>
    </label>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    <button class="primary" type="submit" :disabled="loading">
      {{ loading ? 'Ingresando…' : 'Ingresar' }}
    </button>
  </form>
</template>

<style scoped>
.login { max-width: 380px; margin: 3rem auto; display: grid; gap: 0.8rem; }
label { display: grid; gap: 0.25rem; }
.pass { display: flex; gap: 0.4rem; }
.pass input { flex: 1; }
.eye { padding: 0.3rem 0.6rem; }
</style>
