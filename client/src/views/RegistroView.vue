<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { registerCliente } from '@/services/api'
import { useSessionStore } from '@/stores/session'

const route = useRoute()
const router = useRouter()
const session = useSessionStore()

// The shop is chosen by the link the cliente received (?negocio=...) or by the default of this installation.
const negocioId = computed(() => {
  const fromLink = route.query.negocio
  return typeof fromLink === 'string' && fromLink !== '' ? fromLink : (import.meta.env.VITE_NEGOCIO_ID ?? '')
})

const nombre = ref('')
const correo = ref('')
const contrasena = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  if (negocioId.value === '') {
    error.value = 'Falta el enlace del negocio. Pida el enlace de registro a la tienda.'
    return
  }
  loading.value = true
  error.value = null
  try {
    await registerCliente(negocioId.value, {
      nombre: nombre.value,
      correo: correo.value,
      contrasena: contrasena.value,
    })
    await session.login(correo.value, contrasena.value)
    await router.push({ name: 'tienda' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo crear la cuenta'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="card registro" @submit.prevent="submit">
    <h1>Crear cuenta de cliente</h1>
    <label>Nombre <input v-model="nombre" required autocomplete="name" /></label>
    <label>Correo <input v-model="correo" type="email" required autocomplete="email" /></label>
    <label>
      Contraseña
      <input v-model="contrasena" type="password" minlength="8" required autocomplete="new-password" />
    </label>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    <button class="primary" type="submit" :disabled="loading">
      {{ loading ? 'Creando cuenta…' : 'Crear cuenta' }}
    </button>
    <p class="muted">¿Ya tiene cuenta? <RouterLink :to="{ name: 'login' }">Inicie sesión</RouterLink></p>
  </form>
</template>

<style scoped>
.registro { max-width: 380px; margin: 3rem auto; display: grid; gap: 0.8rem; }
label { display: grid; gap: 0.25rem; }
</style>
