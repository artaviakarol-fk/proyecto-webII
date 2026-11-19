<script setup lang="ts">
import { computed, watch } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'

import AccessibilityMenu from '@/components/AccessibilityMenu.vue'
import ReservationsDrawer from '@/components/ReservationsDrawer.vue'
import { useReservationsStore } from '@/stores/reservations'
import { useSessionStore } from '@/stores/session'
import { useSubscriptionStore } from '@/stores/subscription'
import { SERVICE_STATUS } from '@/utils/subscription'

const session = useSessionStore()
const reservations = useReservationsStore()
const subscription = useSubscriptionStore()
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
  supervisor:
    '<b>Puede:</b> vender, cobrar, fiado, recibir mercadería y vencidos · <b>No puede:</b> ver reportes ni administrar el personal',
  superadmin:
    '<b>Plataforma:</b> abre negocios, ve su plan y registra pagos · No ve ventas, clientes, fiado ni inventario de ningún negocio',
}
const permissionText = computed(() => {
  const user = session.user
  if (!user) return ''
  // A role created by the negocio is described by the permissions it was given.
  if (user.rol === 'personalizado') {
    const names: Record<string, string> = { administrar: 'administrar', caja: 'caja', inventario: 'inventario' }
    const list = session.abilities.map((a) => names[a]).filter(Boolean)
    return `<b>${escapeHtml(user.rol_nombre)}:</b> ${list.join(', ')}`
  }
  return permissions[user.rol] ?? ''
})
const showChrome = computed(() => session.isLogged && !session.mustChangePassword)
const warning = computed(() => {
  const plan = subscription.current
  return plan?.aviso ? { text: plan.aviso, tone: SERVICE_STATUS[plan.estado].tone } : null
})

function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] ?? c)
}

// The administration sees the warning of the rent on every page while it is close to the date or past it.
watch(
  () => [session.user?.id, session.mustChangePassword] as const,
  ([id, mustChange]) => {
    if (id && !mustChange && session.can('administrar')) {
      // Suspended: the administration can only see and pay, so it starts in the plan.
      void subscription.load().then(() => {
        if (subscription.current?.estado === 'suspendido') void router.push({ name: 'plan' })
      })
    } else subscription.clear()
  },
  { immediate: true },
)

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
  subscription.clear()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="showChrome" class="site-header">
    <div class="header-inner">
      <div class="logo"><span class="logo-icon" aria-hidden="true">🛒</span><span>Tu<strong>MiniSúper</strong></span></div>
      <nav class="nav-list" aria-label="Secciones">
        <RouterLink v-if="session.can('plataforma')" class="nav-link" :to="{ name: 'plataforma' }">Negocios</RouterLink>
        <RouterLink v-if="session.can('comprar')" class="nav-link" :to="{ name: 'tienda' }">Tienda</RouterLink>
        <RouterLink v-if="session.can('caja')" class="nav-link" :to="{ name: 'caja' }">Caja</RouterLink>
        <RouterLink v-if="session.can('caja')" class="nav-link" :to="{ name: 'ventas' }">Ventas</RouterLink>
        <RouterLink v-if="session.can('caja')" class="nav-link" :to="{ name: 'fiado' }">Fiado</RouterLink>
        <RouterLink v-if="session.can('inventario')" class="nav-link" :to="{ name: 'bodega' }">Bodega</RouterLink>
        <RouterLink v-if="session.can('administrar')" class="nav-link" :to="{ name: 'cierre-diario' }">
          Cierre diario
        </RouterLink>
        <RouterLink v-if="session.can('administrar')" class="nav-link" :to="{ name: 'personal' }">Personal</RouterLink>
        <RouterLink v-if="session.can('administrar')" class="nav-link" :to="{ name: 'plan' }">Plan</RouterLink>
      </nav>
      <div class="hright">
        <button v-if="session.can('comprar')" class="hbtn cartb" type="button" @click="reservations.drawerOpen = true">
          🛒 Mi compra<template v-if="reservations.count > 0"> ({{ reservations.count }})</template>
        </button>
        <div>
          <b>{{ session.user?.nombre }}</b>
          <small>{{ session.user?.rol_nombre ?? session.user?.rol }}</small>
        </div>
        <button class="hbtn" type="button" @click="salir">Salir</button>
      </div>
    </div>
  </header>
  <div v-if="showChrome && permissionText" class="subbar">
    <!-- eslint-disable-next-line vue/no-v-html -- fixed text written in this file, no user data -->
    <div v-html="permissionText" />
  </div>
  <div v-if="showChrome && warning && $route.name !== 'plan'" class="planbar" :class="warning.tone" role="status">
    <div>
      {{ warning.text }}
      <RouterLink :to="{ name: 'plan' }">Ver plan y pagar</RouterLink>
    </div>
  </div>
  <main :class="{ page: showChrome }"><RouterView /></main>
  <footer v-if="showChrome" class="site-footer">
    <b>🛒 TuMiniSúper</b> · Minisúper Teresita · Sistema de caja, inventario, fiado y pedidos en línea
  </footer>
  <ReservationsDrawer v-if="session.can('comprar')" />
  <AccessibilityMenu />
</template>
