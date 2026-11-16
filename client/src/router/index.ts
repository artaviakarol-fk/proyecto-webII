import { createRouter, createWebHistory, type RouteLocationRaw } from 'vue-router'

import { useSessionStore } from '@/stores/session'
import type { Ability } from '@/services/types'

declare module 'vue-router' {
  interface RouteMeta {
    public?: boolean
    ability?: Ability
  }
}

interface GuardSession {
  isLogged: boolean
  can: (ability: Ability) => boolean
}

interface GuardTarget {
  fullPath: string
  meta: { public?: boolean; ability?: Ability }
}

/** Decides where a navigation goes: true to continue, or the redirect. */
export function resolveAccess(to: GuardTarget, session: GuardSession): true | RouteLocationRaw {
  if (to.meta.public) return session.isLogged ? { name: 'caja' } : true
  if (!session.isLogged) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.ability && !session.can(to.meta.ability)) return { name: 'forbidden' }
  return true
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: { name: 'caja' } },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/caja',
      name: 'caja',
      component: () => import('@/views/CajaView.vue'),
      meta: { ability: 'caja' },
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
