<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

import PasswordInput from '@/components/PasswordInput.vue'
import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import {
  changeStaffAccess,
  createStaffRole,
  deleteStaffRole,
  listStaff,
  listStaffRoles,
  openStaffAccount,
  resetStaffPassword,
  updateStaffRole,
} from '@/services/api'
import type { Permission, StaffMember, StaffRole } from '@/services/types'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const staff = useAsync(listStaff)
const roles = useAsync(listStaffRoles)

const PERMISSIONS: { value: Permission; label: string; help: string }[] = [
  { value: 'administrar', label: 'Administrar', help: 'reportes, personal, roles, precios y el plan' },
  { value: 'caja', label: 'Caja', help: 'vender, cobrar, fiado y abonos' },
  { value: 'inventario', label: 'Inventario', help: 'recibir mercadería y registrar vencidos' },
]
const permissionLabel = (p: string) => PERMISSIONS.find((x) => x.value === p)?.label ?? p

const message = ref<string | null>(null)
const error = ref<string | null>(null)
const busy = ref(false)

/** Runs a change, shows its result and reloads both lists. */
async function act(work: () => Promise<unknown>, done: string): Promise<boolean> {
  busy.value = true
  error.value = null
  message.value = null
  try {
    await work()
    message.value = done
    await Promise.all([staff.run(), roles.run()])
    return true
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo guardar el cambio'
    return false
  } finally {
    busy.value = false
  }
}

onMounted(() => Promise.all([staff.run(), roles.run()]))

// --- Personal ---
const roleOptions = computed<StaffRole[]>(() => roles.data.value ?? [])
const roleValue = (member: StaffMember) => member.rol_id ?? member.rol
const newAccount = reactive({ nombre: '', correo: '', contrasena_temporal: '', rol: 'cajero' })

async function openAccount() {
  const ok = await act(() => openStaffAccount({ ...newAccount }), `Cuenta creada. ${newAccount.nombre} debe cambiar la contraseña temporal al entrar.`)
  if (ok) Object.assign(newAccount, { nombre: '', correo: '', contrasena_temporal: '', rol: 'cajero' })
}

function changeRole(member: StaffMember, rol: string) {
  void act(() => changeStaffAccess(member.id, { rol }), `Rol de ${member.nombre} cambiado. Debe volver a iniciar sesión.`)
}

function toggleActive(member: StaffMember) {
  const activo = !member.activo
  void act(
    () => changeStaffAccess(member.id, { activo }),
    activo ? `${member.nombre} puede volver a entrar.` : `${member.nombre} ya no puede entrar y sus sesiones se cerraron.`,
  )
}

const resetting = ref<StaffMember | null>(null)
const temporary = ref('')
async function resetPassword() {
  const member = resetting.value
  if (!member) return
  const ok = await act(() => resetStaffPassword(member.id, temporary.value), `${member.nombre} entrará con la contraseña temporal y deberá cambiarla.`)
  if (ok) {
    resetting.value = null
    temporary.value = ''
  }
}

// --- Roles ---
const editing = ref<StaffRole | null>(null)
const roleForm = reactive<{ nombre: string; permisos: Permission[] }>({ nombre: '', permisos: [] })

function editRole(role: StaffRole | null) {
  editing.value = role
  roleForm.nombre = role?.nombre ?? ''
  roleForm.permisos = role ? [...role.permisos] : []
}

async function saveRole() {
  const role = editing.value
  const ok = await act(
    () => (role ? updateStaffRole(role.id, roleForm.nombre, roleForm.permisos) : createStaffRole(roleForm.nombre, roleForm.permisos)),
    role ? `Rol “${roleForm.nombre}” guardado.` : `Rol “${roleForm.nombre}” creado.`,
  )
  if (ok) editRole(null)
}

function removeRole(role: StaffRole) {
  void act(() => deleteStaffRole(role.id), `Rol “${role.nombre}” eliminado.`)
}
</script>

<template>
  <div class="personal">
    <h1>Personal y roles</h1>
    <p v-if="message" class="note" role="status">{{ message }}</p>
    <p v-if="error" class="note bad" role="alert">{{ error }}</p>

    <section class="card">
      <h2>Personal</h2>
      <StateBox :loading="staff.loading.value && !staff.data.value" :error="staff.error.value" @retry="staff.run">
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="member in staff.data.value ?? []" :key="member.id">
                <td>
                  {{ member.nombre }}
                  <span v-if="member.id === session.user?.id" class="chip">Usted</span>
                </td>
                <td class="muted">{{ member.correo }}</td>
                <td>
                  <select
                    :value="roleValue(member)"
                    :disabled="busy || member.id === session.user?.id"
                    :aria-label="`Rol de ${member.nombre}`"
                    @change="changeRole(member, ($event.target as HTMLSelectElement).value)"
                  >
                    <option v-for="role in roleOptions" :key="role.id" :value="role.id">{{ role.nombre }}</option>
                  </select>
                </td>
                <td>
                  <span class="chip" :class="{ bad: !member.activo }">{{ member.activo ? 'Activa' : 'Desactivada' }}</span>
                  <span v-if="member.debe_cambiar_contrasena" class="chip warn">Contraseña temporal</span>
                </td>
                <td><div class="actions">
                  <button class="sm" type="button" :disabled="busy" @click="resetting = member">Contraseña temporal</button>
                  <button
                    class="sm"
                    :class="{ danger: member.activo }"
                    type="button"
                    :disabled="busy || member.id === session.user?.id"
                    @click="toggleActive(member)"
                  >
                    {{ member.activo ? 'Desactivar' : 'Activar' }}
                  </button>
                </div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </StateBox>

      <form v-if="resetting" class="inline-form" @submit.prevent="resetPassword">
        <b>Nueva contraseña temporal para {{ resetting.nombre }}</b>
        <PasswordInput v-model="temporary" autocomplete="new-password" :minlength="8" />
        <button class="primary" type="submit" :disabled="busy">Guardar</button>
        <button type="button" @click="resetting = null">Cancelar</button>
      </form>

      <form class="new-account" @submit.prevent="openAccount">
        <h3>Crear una cuenta</h3>
        <label>Nombre <input v-model="newAccount.nombre" required maxlength="120" /></label>
        <label>Correo <input v-model="newAccount.correo" type="email" required /></label>
        <label>Contraseña temporal <PasswordInput v-model="newAccount.contrasena_temporal" autocomplete="new-password" :minlength="8" /></label>
        <label>
          Rol
          <select v-model="newAccount.rol">
            <option v-for="role in roleOptions" :key="role.id" :value="role.id">{{ role.nombre }}</option>
          </select>
        </label>
        <button class="primary" type="submit" :disabled="busy">Crear cuenta</button>
        <p class="muted small">La persona entra con la contraseña temporal y el sistema le pide cambiarla.</p>
      </form>
    </section>

    <section class="card">
      <h2>Roles</h2>
      <p class="muted">
        Los cuatro roles por defecto no se pueden cambiar. Puede crear otros eligiendo sus permisos; un rol que alguien
        tiene asignado no se puede eliminar.
      </p>
      <StateBox :loading="roles.loading.value && !roles.data.value" :error="roles.error.value" @retry="roles.run">
        <ul class="roles">
          <li v-for="role in roleOptions" :key="role.id">
            <div>
              <b>{{ role.nombre }}</b>
              <span v-if="role.predeterminado" class="chip">Por defecto</span>
              <small class="muted"> · {{ role.en_uso }} {{ role.en_uso === 1 ? 'persona' : 'personas' }}</small>
              <div class="perms">
                <span v-for="p in role.permisos" :key="p" class="chip warn">{{ permissionLabel(p) }}</span>
              </div>
            </div>
            <div v-if="!role.predeterminado" class="actions">
              <button class="sm" type="button" :disabled="busy" @click="editRole(role)">Editar</button>
              <button class="sm danger" type="button" :disabled="busy || role.en_uso > 0" @click="removeRole(role)">Eliminar</button>
            </div>
          </li>
        </ul>
      </StateBox>

      <form class="role-form" @submit.prevent="saveRole">
        <h3>{{ editing ? `Editar “${editing.nombre}”` : 'Crear un rol' }}</h3>
        <label>Nombre <input v-model="roleForm.nombre" required maxlength="60" placeholder="Por ejemplo: Encargado de turno" /></label>
        <fieldset>
          <legend>Permisos</legend>
          <label v-for="p in PERMISSIONS" :key="p.value" class="check">
            <input v-model="roleForm.permisos" type="checkbox" :value="p.value" />
            <span><b>{{ p.label }}</b> <small class="muted">— {{ p.help }}</small></span>
          </label>
        </fieldset>
        <div class="actions">
          <button class="primary" type="submit" :disabled="busy || roleForm.permisos.length === 0">
            {{ editing ? 'Guardar rol' : 'Crear rol' }}
          </button>
          <button v-if="editing" type="button" @click="editRole(null)">Cancelar</button>
        </div>
      </form>
    </section>
  </div>
</template>

<style scoped>
.personal { display: grid; gap: 1rem; }
.table-wrap { overflow-x: auto; }
.actions { display: flex; gap: 0.4rem; flex-wrap: wrap; justify-content: flex-end; }
.inline-form { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; margin-top: 1rem; padding: 0.8rem; border: 1px dashed var(--border); border-radius: var(--r-md); }
.new-account, .role-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.7rem; align-items: end; margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.new-account h3, .role-form h3, .new-account .small, .role-form fieldset, .role-form .actions { grid-column: 1 / -1; }
.role-form .actions { justify-content: flex-start; }
label { display: grid; gap: 0.25rem; }
.check { display: flex; gap: 0.5rem; align-items: center; font-weight: 400; color: var(--texto); padding: 0.25rem 0; }
.roles { list-style: none; margin: 0; padding: 0; }
.roles li { display: flex; justify-content: space-between; align-items: center; gap: 0.8rem; padding: 0.7rem 0; border-bottom: 1px solid var(--border); }
.perms { display: flex; gap: 0.3rem; margin-top: 0.25rem; flex-wrap: wrap; }
.small { font-size: 0.82rem; margin: 0; }
</style>
