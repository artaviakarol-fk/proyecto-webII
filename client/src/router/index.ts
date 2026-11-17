import { createRouter, createWebHistory, type RouteLocationRaw } from 'vue-router'

import { useSessionStore } from '@/stores/session'
import type { Ability } from '@/services/types'

declare module 'vue-router' {
  interface RouteMeta {
    public?: boolean
    ability?: Ability
    home?: boolean
  }
}

interface GuardSession {
  isLogged: boolean
  can: (ability: Ability) => boolean
}

interface GuardTarget {
  fullPath: string
  meta: { public?: boolean; ability?: Ability; home?: boolean }
}

/** The first page of each kind of account: the cliente shops, the staff works at the till. */
export function homeFor(session: GuardSession): RouteLocationRaw {
  if (session.can('comprar')) return { name: 'tienda' }
  // The bodeguero only has the inventario permission, so the till would be forbidden for them.
  return session.can('caja') ? { name: 'caja' } : { name: 'bodega' }
}

/** Decides where a navigation goes: true to continue, or the redirect. */
export function resolveAccess(to: GuardTarget, session: GuardSession): true | RouteLocationRaw {
  if (to.meta.public) return session.isLogged ? homeFor(session) : true
  if (!session.isLogged) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.home) return homeFor(session)
  if (to.meta.ability && !session.can(to.meta.ability)) return { name: 'forbidden' }
  return true
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: () => import('@/views/ForbiddenView.vue'), meta: { home: true } },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/registro',
      name: 'registro',
      component: () => import('@/views/RegistroView.vue'),
      meta: { public: true },
    },
    {
      path: '/tienda',
      name: 'tienda',
      component: () => import('@/views/TiendaView.vue'),
      meta: { ability: 'comprar' },
    },
    {
      path: '/pago',
      name: 'pago',
      component: () => import('@/views/PagoView.vue'),
      meta: { ability: 'comprar' },
    },
    {
      path: '/pago/estado/:venta?',
      name: 'pago-estado',
      component: () => import('@/views/PagoEstadoView.vue'),
      meta: { ability: 'comprar' },
    },
    {
      path: '/caja',
      name: 'caja',
      component: () => import('@/views/CajaView.vue'),
      meta: { ability: 'caja' },
    },
    {
      path: '/ventas',
      name: 'ventas',
      component: () => import('@/views/VentasView.vue'),
      meta: { ability: 'caja' },
    },
    {
      path: '/fiado',
      name: 'fiado',
      component: () => import('@/views/FiadoView.vue'),
      meta: { ability: 'caja' },
    },
    {
      path: '/bodega',
      name: 'bodega',
      component: () => import('@/views/BodegaView.vue'),
      meta: { ability: 'inventario' },
    },
    {
      path: '/cierre-diario',
      name: 'cierre-diario',
      component: () => import('@/views/CierreDiarioView.vue'),
      meta: { ability: 'administrar' },
    },
    {
      path: '/sin-permiso',
      name: 'forbidden',
      component: () => import('@/views/ForbiddenView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const session = useSessionStore()
  await session.restore()
  return resolveAccess(to, session)
})

export default router
