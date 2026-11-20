<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import AuthLayout from '@/components/AuthLayout.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { homeFor } from '@/router'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()

// The same limits the API checks (RegisterClienteRequest), so the message comes before sending.
const MIN_LENGTH = 8

const actual = ref('')
const nueva = ref('')
const repetida = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  error.value = null
  if (nueva.value.length < MIN_LENGTH) {
    error.value = `La contraseña nueva debe tener al menos ${MIN_LENGTH} caracteres.`
    return
  }
  if (nueva.value !== repetida.value) {
    error.value = 'Las dos contraseñas nuevas no coinciden.'
    return
  }
  if (nueva.value === actual.value) {
    error.value = 'La contraseña nueva debe ser distinta de la temporal.'
    return
  }
  loading.value = true
  try {
    await session.changePassword(actual.value, nueva.value)
    await router.push(homeFor(session))
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo cambiar la contraseña'
  } finally {
    loading.value = false
  }
}

async function salir() {
  await session.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <AuthLayout>
    <h2>Elija su contraseña</h2>
    <p>
      Hola{{ session.user ? `, ${session.user.nombre}` : '' }}. Entró con una contraseña temporal: antes de seguir,
      cámbiela por una que solo usted conozca.
    </p>

    <form class="lform" novalidate @submit.prevent="submit">
      <label class="lfield">
        Contraseña temporal
        <PasswordInput v-model="actual" autocomplete="current-password" />
      </label>
      <label class="lfield">
        Contraseña nueva
        <PasswordInput v-model="nueva" autocomplete="new-password" :minlength="MIN_LENGTH" />
      </label>
      <label class="lfield">
        Repita la contraseña nueva
        <PasswordInput v-model="repetida" autocomplete="new-password" :minlength="MIN_LENGTH" />
      </label>
      <p class="lerr" role="alert">{{ error }}</p>
      <button class="primary full lg" type="submit" :disabled="loading">
        {{ loading ? 'Guardando…' : 'Guardar y entrar' }}
      </button>
    </form>
    <button class="link" type="button" @click="salir">Salir</button>
  </AuthLayout>
</template>
