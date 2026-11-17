<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'

import { useReservationsStore } from '@/stores/reservations'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const reservations = useReservationsStore()
const router = useRouter()

async function salir() {
  await session.logout()
  reservations.clear()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="session.isLogged" class="bar">
    <strong class="logo">Minisuper Teresita</strong>
    <nav>
      <RouterLink v-if="session.can('comprar')" :to="{ name: 'tienda' }">Tienda</RouterLink>
      <RouterLink v-if="session.can('caja')" :to="{ name: 'caja' }">Caja</RouterLink>
      <RouterLink v-if="session.can('caja')" :to="{ name: 'ventas' }">Ventas</RouterLink>
      <RouterLink v-if="session.can('caja')" :to="{ name: 'fiado' }">Fiado</RouterLink>
      <RouterLink v-if="session.can('administrar')" :to="{ name: 'cierre-diario' }">
        Cierre diario
      </RouterLink>
    </nav>
    <span class="who">{{ session.user?.nombre }} · {{ session.user?.rol }}</span>
    <button type="button" @click="salir">Salir</button>
  </header>
  <main class="page"><RouterView /></main>
</template>

<style scoped>
.bar { position: sticky; top: 0; z-index: 5; display: flex; gap: 1rem; align-items: center; padding: 0.6rem 1rem; background: var(--verde-oscuro); color: #fff; }
.logo { font-family: 'Playfair Display', Georgia, serif; font-size: 1.2rem; }
.bar nav { display: flex; gap: 0.75rem; flex: 1; }
.bar a { color: var(--verde-suave); text-decoration: none; }
.bar a.router-link-active { color: #fff; font-weight: 600; text-decoration: underline; text-decoration-color: var(--naranja); text-underline-offset: 4px; }
.who { color: var(--verde-suave); }
.page { max-width: 1100px; margin: 1rem auto; padding: 0 1rem; }
</style>
