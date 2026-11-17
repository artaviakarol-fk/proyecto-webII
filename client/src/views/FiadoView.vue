<script setup lang="ts">
import { onMounted, ref } from 'vue'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { grantCreditIncrease, listCustomers, receiveCreditPayment } from '@/services/api'
import type { Customer } from '@/services/types'
import { formatMoney } from '@/utils/money'

const search = ref('')
const customers = useAsync(() => listCustomers(search.value || undefined))

// One customer is worked on at a time: what is typed, what is being sent and what the API answered.
const selected = ref<Customer | null>(null)
const abono = ref<number | null>(null)
const ampliacion = ref<number | null>(null)
const motivo = ref('')
const busy = ref(false)
const message = ref<{ kind: 'ok' | 'error'; text: string } | null>(null)

function choose(customer: Customer) {
  selected.value = customer
  abono.value = null
  ampliacion.value = null
  motivo.value = ''
  message.value = null
}

/** After a change the list is reloaded and the selected customer is replaced by its fresh balance. */
async function refresh() {
  const id = selected.value?.id
  await customers.run()
  selected.value = customers.data.value?.find((c) => c.id === id) ?? null
}

async function pay() {
  if (!selected.value || abono.value === null) return
  busy.value = true
  message.value = null
  try {
    await receiveCreditPayment(selected.value.id, abono.value)
    message.value = { kind: 'ok', text: `Abono de ${formatMoney(abono.value)} registrado.` }
    abono.value = null
    await refresh()
  } catch (e) {
    message.value = { kind: 'error', text: e instanceof Error ? e.message : 'No se pudo registrar el abono' }
  } finally {
    busy.value = false
  }
}

async function extend() {
  if (!selected.value || ampliacion.value === null) return
  busy.value = true
  message.value = null
  try {
    await grantCreditIncrease(selected.value.id, ampliacion.value, motivo.value)
    message.value = { kind: 'ok', text: 'Límite ampliado hasta el final del día.' }
    ampliacion.value = null
    motivo.value = ''
    await refresh()
  } catch (e) {
    message.value = { kind: 'error', text: e instanceof Error ? e.message : 'No se pudo ampliar el límite' }
  } finally {
    busy.value = false
  }
}

onMounted(customers.run)
</script>

<template>
  <div class="fiado">
    <section class="card">
      <h1>Fiado</h1>
      <form class="search" role="search" @submit.prevent="customers.run">
        <input v-model="search" type="search" placeholder="Buscar cliente" aria-label="Buscar cliente" />
        <button type="submit">Buscar</button>
      </form>

      <StateBox
        :loading="customers.loading.value && !customers.data.value"
        :error="customers.error.value"
        :empty="customers.data.value?.length === 0"
        empty-text="No hay clientes registrados."
        @retry="customers.run"
      >
        <table>
          <thead>
            <tr><th>Cliente</th><th>Debe</th><th>Límite</th><th>Disponible</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in customers.data.value ?? []" :key="c.id" :class="{ selected: selected?.id === c.id }">
              <td>
                {{ c.nombre }}
                <span v-if="!c.activo" class="chip">Inactivo</span>
              </td>
              <td>{{ formatMoney(c.saldo_actual) }}</td>
              <td>{{ formatMoney(c.limite_efectivo) }}</td>
              <td>{{ formatMoney(c.disponible) }}</td>
              <td><button type="button" @click="choose(c)">Gestionar</button></td>
            </tr>
          </tbody>
        </table>
      </StateBox>
    </section>

    <aside v-if="selected" class="card" aria-label="Gestión del cliente">
      <h2>{{ selected.nombre }}</h2>
      <p class="muted">
        Debe {{ formatMoney(selected.saldo_actual) }} · límite {{ formatMoney(selected.limite_efectivo) }}
        <template v-if="selected.ampliacion_vigente > 0">
          (incluye {{ formatMoney(selected.ampliacion_vigente) }} de ampliación de hoy)
        </template>
      </p>

      <form class="block" @submit.prevent="pay">
        <h3>Recibir abono</h3>
        <label>
          Monto en colones
          <input v-model.number="abono" type="number" min="1" step="1" required />
        </label>
        <button
          type="button"
          :disabled="selected.saldo_actual <= 0"
          @click="abono = selected.saldo_actual"
        >
          Cancelar todo ({{ formatMoney(selected.saldo_actual) }})
        </button>
        <button class="primary" type="submit" :disabled="busy || selected.saldo_actual <= 0">
          {{ busy ? 'Guardando…' : 'Registrar abono' }}
        </button>
      </form>

      <form class="block" @submit.prevent="extend">
        <h3>Ampliar límite hoy</h3>
        <label>
          Monto en colones
          <input v-model.number="ampliacion" type="number" min="1" step="1" required />
        </label>
        <label>Motivo <input v-model="motivo" maxlength="255" required /></label>
        <button class="primary" type="submit" :disabled="busy">
          {{ busy ? 'Guardando…' : 'Ampliar límite' }}
        </button>
      </form>

      <p v-if="message" :class="message.kind === 'ok' ? 'ok' : 'error-text'" role="status">
        {{ message.text }}
      </p>
    </aside>
  </div>
</template>

<style scoped>
.fiado { display: grid; grid-template-columns: 1fr 340px; gap: 1rem; align-items: start; }
.search { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
.search input { flex: 1; }
table { border-collapse: collapse; width: 100%; }
th, td { text-align: left; padding: 0.45rem 0.5rem; border-bottom: 1px solid var(--border); }
tr.selected { background: var(--verde-suave); }
.block { display: grid; gap: 0.6rem; margin-top: 1rem; }
.block label { display: grid; gap: 0.25rem; }
.ok { color: var(--verde-medio); font-weight: 600; }
@media (max-width: 900px) { .fiado { grid-template-columns: 1fr; } }
</style>
