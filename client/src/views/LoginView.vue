<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AuthLayout from '@/components/AuthLayout.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { homeFor } from '@/router'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()
const route = useRoute()

// The two tabs only guide the person: the API decides the role of the account.
const tab = ref<'cliente' | 'personal'>('cliente')
const correo = ref('')
const contrasena = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const registroLink = computed(() => ({
  name: 'registro',
  query: route.query.negocio ? { negocio: route.query.negocio } : {},
}))

async function submit() {
  loading.value = true
  error.value = null
  try {
    await session.login(correo.value, contrasena.value)
    const isCliente = session.can('comprar')
    if (tab.value === 'cliente' && !isCliente) {
      await session.logout()
      error.value = 'Esa cuenta es de personal. Use la pestaña “Trabajo en un negocio”.'
      return
    }
    if (tab.value === 'personal' && isCliente) {
      await session.logout()
      error.value = 'Esa cuenta es de cliente. Use la pestaña “Soy cliente”.'
      return
    }
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    await router.push(redirect?.startsWith('/') ? redirect : homeFor(session))
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <h2>Iniciar sesión</h2>
    <p>{{ tab === 'cliente' ? 'Pida a su minisúper y pague en línea.' : 'Acceso para cajeros, bodega y administración.' }}</p>

    <div class="ltabs" role="tablist">
      <button type="button" role="tab" :class="{ on: tab === 'cliente' }" :aria-selected="tab === 'cliente'" @click="tab = 'cliente'">
        Soy cliente
      </button>
      <button type="button" role="tab" :class="{ on: tab === 'personal' }" :aria-selected="tab === 'personal'" @click="tab = 'personal'">
        Trabajo en un negocio
      </button>
    </div>

    <form class="lform" novalidate @submit.prevent="submit">
      <label class="lfield">
        Correo electrónico
        <input v-model="correo" type="email" required placeholder="nombre@correo.com" autocomplete="username" />
      </label>
      <label class="lfield">
        Contraseña
        <PasswordInput v-model="contrasena" placeholder="••••••••" autocomplete="current-password" />
      </label>
      <p class="lerr" role="alert">{{ error }}</p>
      <button class="primary full lg" type="submit" :disabled="loading">{{ loading ? 'Ingresando…' : 'Entrar' }}</button>
    </form>

    <p v-if="tab === 'cliente'" class="center">
      ¿Primera vez? <RouterLink :to="registroLink">Crear cuenta de cliente</RouterLink>
    </p>
    <p v-else class="muted center">Las cuentas del personal las crea el administrador de cada negocio.</p>
  </AuthLayout>
</template>

<style scoped>
.center { text-align: center; margin: 0; }
</style>
