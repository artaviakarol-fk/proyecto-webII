<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AuthLayout from '@/components/AuthLayout.vue'
import PasswordInput from '@/components/PasswordInput.vue'
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
  <AuthLayout>
    <h2>Crear cuenta</h2>
    <p>Pida a su minisúper y pague en línea.</p>
    <form class="lform" novalidate @submit.prevent="submit">
      <label class="lfield">
        Nombre completo
        <input v-model="nombre" required autocomplete="name" />
      </label>
      <label class="lfield">
        Correo electrónico
        <input v-model="correo" type="email" required placeholder="nombre@correo.com" autocomplete="email" />
      </label>
      <label class="lfield">
        Contraseña
        <PasswordInput v-model="contrasena" :minlength="8" placeholder="Mínimo 8 caracteres" autocomplete="new-password" />
      </label>
      <p class="lerr" role="alert">{{ error }}</p>
      <button class="primary full lg" type="submit" :disabled="loading">
        {{ loading ? 'Creando cuenta…' : 'Crear cuenta y entrar' }}
      </button>
    </form>
    <p class="center">¿Ya tiene cuenta? <RouterLink :to="{ name: 'login' }">Iniciar sesión</RouterLink></p>
  </AuthLayout>
</template>

<style scoped>
.center { text-align: center; margin: 0; }
</style>
