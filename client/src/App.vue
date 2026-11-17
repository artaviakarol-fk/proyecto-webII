<script setup lang="ts">
import { computed, watch } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'

import AccessibilityMenu from '@/components/AccessibilityMenu.vue'
import ReservationsDrawer from '@/components/ReservationsDrawer.vue'
import { useReservationsStore } from '@/stores/reservations'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const reservations = useReservationsStore()
const router = useRouter()

// What each kind of account can and cannot do, shown under the header like in the prototype.
const permissions: Record<string, string> = {
  cliente:
    '<b>Cliente:</b> ve solo el catálogo de este negocio, compra productos y paga en línea',
  cajero:
    '<b>Puede:</b> vender, cobrar, registrar abonos y anular ventas · <b>No puede:</b> ver costos ni cambiar el inventario',
  administrador:
    '<b>Acceso total a este negocio:</b> reportes, ventas, fiados e inventario · No ve datos de otros negocios',
  bodeguero:
    '<b>Puede:</b> recibir mercadería y registrar vencidos · <b>No puede:</b> vender ni ver cobros y fiados',
}
const permissionText = computed(() => permissions[session.user?.rol ?? ''] ?? '')

// The cliente keeps the reservas of the side cart up to date as soon as the session is known.
watch(
  () => session.user?.id,
  (id) => {
    if (id && session.can('comprar')) void reservations.load()
  },
  { immediate: true },
)

async function salir() {
  await session.logout()
  reservations.clear()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="session.isLogged" class="site-header">
    <div class="header-inner">
      <div class="logo"><span class="logo-icon" aria-hidden="true">🛒</span><span>Tu<strong>MiniSúper</strong></span></div>
      <nav class="nav-list" aria-label="Secciones">
        <RouterLink v-if="session.can('comprar')" class="nav-link" :to="{ name: 'tienda' }">Tienda</RouterLink>
        <RouterLink v-if="session.can('caja')" class="nav-link" :to="{ name: 'caja' }">Caja</RouterLink>
        <RouterLink v-if="session.can('caja')" class="nav-link" :to="{ name: 'ventas' }">Ventas</RouterLink>
        <RouterLink v-if="session.can('caja')" class="nav-link" :to="{ name: 'fiado' }">Fiado</RouterLink>
        <RouterLink v-if="session.can('inventario')" class="nav-link" :to="{ name: 'bodega' }">Bodega</RouterLink>
        <RouterLink v-if="session.can('administrar')" class="nav-link" :to="{ name: 'cierre-diario' }">
          Cierre diario
        </RouterLink>
      </nav>
      <div class="hright">
        <button v-if="session.can('comprar')" class="hbtn cartb" type="button" @click="reservations.drawerOpen = true">
          🛒 Mi compra<template v-if="reservations.count > 0"> ({{ reservations.count }})</template>
        </button>
        <div>
          <b>{{ session.user?.nombre }}</b>
          <small>{{ session.user?.rol }}</small>
        </div>
        <button class="hbtn" type="button" @click="salir">Salir</button>
      </div>
    </div>
  </header>
  <div v-if="session.isLogged && permissionText" class="subbar">
    <!-- eslint-disable-next-line vue/no-v-html -- fixed text written in this file, no user data -->
    <div v-html="permissionText" />
  </div>
  <main :class="{ page: session.isLogged }"><RouterView /></main>
  <footer v-if="session.isLogged" class="site-footer">
    <b>🛒 TuMiniSúper</b> · Minisúper Teresita · Sistema de caja, inventario, fiado y pedidos en línea
  </footer>
  <ReservationsDrawer v-if="session.can('comprar')" />
  <AccessibilityMenu />
</template>
