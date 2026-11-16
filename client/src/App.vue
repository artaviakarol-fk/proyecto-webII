<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'

import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()

async function salir() {
  await session.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="session.isLogged" class="bar">
    <strong>Minisuper Teresita</strong>
    <nav>
      <RouterLink v-if="session.can('caja')" :to="{ name: 'caja' }">Caja</RouterLink>
      <RouterLink v-if="session.can('administrar')" :to="{ name: 'cierre-diario' }">
        Cierre diario
      </RouterLink>
    </nav>
    <span class="who muted">{{ session.user?.nombre }} · {{ session.user?.rol }}</span>
    <button type="button" @click="salir">Salir</button>
  </header>
  <main class="page"><RouterView /></main>
</template>

<style scoped>
.bar { display: flex; gap: 1rem; align-items: center; padding: 0.6rem 1rem; background: var(--brand); color: #fff; }
.bar nav { display: flex; gap: 0.75rem; flex: 1; }
.bar a { color: #d1fae5; text-decoration: none; }
.bar a.router-link-active { color: #fff; font-weight: 600; text-decoration: underline; }
.who { color: #d1fae5; }
.page { max-width: 1100px; margin: 1rem auto; padding: 0 1rem; }
</style>
