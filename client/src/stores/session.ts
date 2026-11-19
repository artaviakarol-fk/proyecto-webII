import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import * as api from '@/services/api'
import { setUnauthorizedHandler, tokenStorage } from '@/services/http'
import type { Ability, CurrentUser } from '@/services/types'

export const useSessionStore = defineStore('session', () => {
  const user = ref<CurrentUser | null>(null)
  const hasToken = ref(tokenStorage.get() !== null)

  const isLogged = computed(() => hasToken.value)
  const abilities = computed<string[]>(() => (user.value?.abilities ?? []) as string[])
  // An account opened with a temporary password must choose its own before using anything else.
  const mustChangePassword = computed(() => user.value?.debe_cambiar_contrasena === true)

  function can(ability: Ability): boolean {
    return abilities.value.includes(ability)
  }

  function clear(): void {
    tokenStorage.clear()
    hasToken.value = false
    user.value = null
  }

  async function login(correo: string, contrasena: string): Promise<void> {
    const result = await api.login(correo, contrasena)
    tokenStorage.set(result.token)
    hasToken.value = true
    try {
      user.value = await api.currentUser()
    } catch (e) {
      clear()
      throw e
    }
  }

  /** Reloads the user after a page refresh; drops the token if it is no longer valid. */
  async function restore(): Promise<void> {
    if (!hasToken.value || user.value) return
    try {
      user.value = await api.currentUser()
    } catch {
      clear()
    }
  }

  /** Changes the password of the account and reloads it, so the forced change is over. */
  async function changePassword(current: string, next: string): Promise<void> {
    await api.changePassword(current, next)
    user.value = await api.currentUser()
  }

  async function logout(): Promise<void> {
    try {
      await api.logout()
    } catch {
      /* the local session is closed anyway */
    }
    clear()
  }

  setUnauthorizedHandler(clear)

  return { user, isLogged, abilities, mustChangePassword, can, login, restore, changePassword, logout }
})
