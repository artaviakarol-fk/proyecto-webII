<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import PasswordInput from '@/components/PasswordInput.vue'
import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { listTenants, registerManualPayment, registerNegocio, setTenantActive } from '@/services/api'
import type { Tenant } from '@/services/types'
import { SERVICE_STATUS, formatDay } from '@/utils/subscription'

const search = ref('')
const page = ref(1)
const tenants = useAsync(() => listTenants(search.value.trim() || undefined, page.value))

const busy = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

async function act(work: () => Promise<unknown>, done: string): Promise<boolean> {
  busy.value = true
  error.value = null
  message.value = null
  try {
    await work()
    message.value = done
    await tenants.run()
    return true
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo guardar el cambio'
    return false
  } finally {
    busy.value = false
  }
}

onMounted(tenants.run)

function find() {
  page.value = 1
  void tenants.run()
}

function goToPage(n: number) {
  page.value = n
  void tenants.run()
}

const service = (tenant: Tenant) => SERVICE_STATUS[tenant.servicio.estado ?? 'suspendido']

// --- Abrir un negocio ---
const newNegocio = reactive({ nombre: '', admin: { nombre: '', correo: '', contrasena_temporal: '' } })
async function open() {
  const nombre = newNegocio.nombre
  const ok = await act(
    () => registerNegocio({ nombre, administrador: { ...newNegocio.admin } }),
    `“${nombre}” quedó abierto con 5 días de prueba. Su administración entra con la contraseña temporal.`,
  )
  if (ok) Object.assign(newNegocio, { nombre: '', admin: { nombre: '', correo: '', contrasena_temporal: '' } })
}

// --- Apagar o encender ---
function toggle(tenant: Tenant) {
  const activo = tenant.estado !== 'activo'
  void act(
    () => setTenantActive(tenant.id, activo),
    activo ? `“${tenant.nombre}” está encendido otra vez.` : `“${tenant.nombre}” quedó suspendido. Sus datos se conservan.`,
  )
}

// --- Pago en efectivo o por SINPE a la plataforma ---
const paying = ref<Tenant | null>(null)
const payment = reactive<{ meses: number; metodo: 'efectivo' | 'sinpe'; referencia: string }>({ meses: 1, metodo: 'sinpe', referencia: '' })
async function registerPayment() {
  const tenant = paying.value
  if (!tenant) return
  const ok = await act(
    () => registerManualPayment(tenant.id, { meses: payment.meses, metodo: payment.metodo, referencia: payment.referencia.trim() || null }),
    `Pago registrado: se sumaron ${payment.meses} ${payment.meses === 1 ? 'mes' : 'meses'} a “${tenant.nombre}”.`,
  )
  if (ok) {
    paying.value = null
    Object.assign(payment, { meses: 1, metodo: 'sinpe', referencia: '' })
  }
}
</script>

<template>
  <div class="plataforma">
    <h1>Negocios de la plataforma</h1>
    <p class="muted">
      Aquí se ven los negocios, quién los administra y su plan. Los datos de cada negocio (ventas, clientes, fiado,
      inventario) no se ven desde la plataforma.
    </p>
    <p v-if="message" class="note" role="status">{{ message }}</p>
    <p v-if="error" class="note bad" role="alert">{{ error }}</p>

    <section class="card">
      <form class="search" role="search" @submit.prevent="find">
        <input v-model="search" type="search" placeholder="Buscar un negocio por nombre" aria-label="Buscar negocio" />
        <button type="submit">Buscar</button>
      </form>
      <StateBox
        :loading="tenants.loading.value && !tenants.data.value"
        :error="tenants.error.value"
        :empty="tenants.data.value?.data.length === 0"
        empty-text="No hay negocios con ese nombre."
        @retry="tenants.run"
      >
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Negocio</th><th>Administración</th><th>Plan</th><th>Vence</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="tenant in tenants.data.value?.data ?? []" :key="tenant.id">
                <td>
                  <b>{{ tenant.nombre }}</b>
                  <div class="muted small">Desde {{ formatDay(tenant.creado_el) }}</div>
                </td>
                <td>
                  <div v-for="admin in tenant.administradores" :key="admin.correo" class="small">
                    {{ admin.nombre }} <span class="muted">· {{ admin.correo }}</span>
                    <span v-if="admin.debe_cambiar_contrasena" class="chip warn">Temporal</span>
                  </div>
                </td>
                <td>
                  <span class="chip" :class="service(tenant).tone">{{ service(tenant).label }}</span>
                  <span v-if="tenant.estado === 'inactivo'" class="chip bad">Apagado</span>
                  <span v-if="tenant.servicio.en_prueba" class="chip warn">Prueba</span>
                </td>
                <td class="small">
                  {{ formatDay(tenant.servicio.vence_el) }}
                  <div v-if="tenant.servicio.dias_restantes !== null" class="muted">
                    {{ tenant.servicio.dias_restantes >= 0 ? `faltan ${tenant.servicio.dias_restantes} días` : `vencido hace ${-tenant.servicio.dias_restantes} días` }}
                  </div>
                </td>
                <td><div class="actions">
                  <button class="sm" type="button" :disabled="busy" @click="paying = tenant">Registrar pago</button>
                  <button class="sm" :class="{ danger: tenant.estado === 'activo' }" type="button" :disabled="busy" @click="toggle(tenant)">
                    {{ tenant.estado === 'activo' ? 'Apagar' : 'Encender' }}
                  </button>
                </div></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="tenants.data.value && tenants.data.value.meta.ultima_pagina > 1" class="pager">
          <button class="sm" type="button" :disabled="page <= 1" @click="goToPage(page - 1)">Anterior</button>
          <span>Página {{ page }} de {{ tenants.data.value.meta.ultima_pagina }}</span>
          <button class="sm" type="button" :disabled="page >= tenants.data.value.meta.ultima_pagina" @click="goToPage(page + 1)">Siguiente</button>
        </div>
      </StateBox>

      <form v-if="paying" class="pay-form" @submit.prevent="registerPayment">
        <h3>Pago de “{{ paying.nombre }}”</h3>
        <label>
          Medio
          <select v-model="payment.metodo">
            <option value="sinpe">SINPE al número de la plataforma</option>
            <option value="efectivo">Efectivo</option>
          </select>
        </label>
        <label>
          Meses
          <select v-model.number="payment.meses">
            <option v-for="n in 12" :key="n" :value="n">{{ n }}</option>
          </select>
        </label>
        <label>Comprobante (opcional) <input v-model="payment.referencia" maxlength="100" /></label>
        <div class="actions start">
          <button class="primary" type="submit" :disabled="busy">Registrar</button>
          <button type="button" @click="paying = null">Cancelar</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Abrir un negocio</h2>
      <form class="open-form" @submit.prevent="open">
        <label>Nombre del negocio <input v-model="newNegocio.nombre" required maxlength="120" /></label>
        <label>Nombre de quien lo administra <input v-model="newNegocio.admin.nombre" required maxlength="120" /></label>
        <label>Correo <input v-model="newNegocio.admin.correo" type="email" required /></label>
        <label>Contraseña temporal <PasswordInput v-model="newNegocio.admin.contrasena_temporal" autocomplete="new-password" :minlength="8" /></label>
        <div class="actions start">
          <button class="primary" type="submit" :disabled="busy">Abrir negocio</button>
        </div>
        <p class="muted small">El negocio empieza con 5 días de prueba. Quien lo administra debe cambiar la contraseña al entrar.</p>
      </form>
    </section>
  </div>
</template>

<style scoped>
.plataforma { display: grid; gap: 1rem; }
.table-wrap { overflow-x: auto; }
.actions { display: flex; gap: 0.4rem; flex-wrap: wrap; justify-content: flex-end; }
.actions.start { justify-content: flex-start; }
.pay-form, .open-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.7rem; align-items: end; }
.pay-form { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.pay-form h3, .pay-form .actions, .open-form .actions, .open-form .small { grid-column: 1 / -1; }
label { display: grid; gap: 0.25rem; }
.small { font-size: 0.82rem; margin: 0; }
</style>
