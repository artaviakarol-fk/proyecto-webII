<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import PagerBar from '@/components/PagerBar.vue'
import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { getRestock, listExpired, listProducts, markExpired, registerReceipt } from '@/services/api'
import type { Product, RestockStatus } from '@/services/types'
import { usePager } from '@/composables/usePager'
import { formatMoney } from '@/utils/money'

const search = ref('')
const products = useAsync(() => listProducts(search.value || undefined))
const expired = useAsync(listExpired)

// The screen is split in tabs, so nothing needs scrolling to reach it.
type Tab = 'inventario' | 'reposicion' | 'entrada' | 'vencidos'
const tab = ref<Tab>('inventario')

// Reposición sugerida: what to order, from the paid sales of the last days.
const restockDays = ref(30)
const restock = useAsync(() => getRestock(restockDays.value))
const restockItems = computed(() => restock.data.value?.productos ?? [])
const topSellers = computed(() => restock.data.value?.mas_vendidos ?? [])
const toOrder = computed(() => restockItems.value.filter((i) => i.sugerido > 0))
const statusLabel: Record<RestockStatus, string> = {
  agotado: 'Agotado',
  critico: 'Crítico',
  bajo: 'Bajo',
  ok: 'Bien',
}
const statusClass: Record<RestockStatus, string> = {
  agotado: 'bad',
  critico: 'bad',
  bajo: 'warn',
  ok: '',
}
const daysLeft = (value: number | null) => (value === null ? '—' : String(value))

const today = () => new Date().toISOString().slice(0, 10)

// Entrada de mercadería: a proveedor, a date and one or more lines of products already in the catalog.
interface Line {
  productId: string
  cantidad: number | null
  costo: number | null
}
const proveedor = ref('')
const fecha = ref(today())
const lines = ref<Line[]>([{ productId: '', cantidad: null, costo: null }])

// Vencidos: the product chosen with its "Marcar vencidos" button.
const expiring = ref<Product | null>(null)
const expiredQuantity = ref<number | null>(null)
const expiredReason = ref('')

const busy = ref(false)
const message = ref<{ kind: 'ok' | 'error'; text: string } | null>(null)

const list = computed(() => products.data.value ?? [])

// Each long table shows a few rows at a time, as many as the person chooses.
const inventoryPager = usePager(() => list.value)
const restockPager = usePager(() => restockItems.value)
const expiredList = computed(() => expired.data.value ?? [])
const expiredPager = usePager(() => expiredList.value, 5)

// Units marked expired stay in the stock but cannot be sold: the table shows both numbers.
const expiredByProduct = computed(() => {
  const totals = new Map<string, number>()
  for (const e of expiredList.value)
    totals.set(e.producto_id, (totals.get(e.producto_id) ?? 0) + e.cantidad)
  return totals
})
const expiredOf = (productId: string) => expiredByProduct.value.get(productId) ?? 0

const dateTime = (iso: string) =>
  new Date(iso).toLocaleString('es-CR', { dateStyle: 'short', timeStyle: 'short' })

function addLine() {
  lines.value.push({ productId: '', cantidad: null, costo: null })
}
function removeLine(index: number) {
  if (lines.value.length > 1) lines.value.splice(index, 1)
}

async function submitReceipt() {
  busy.value = true
  message.value = null
  try {
    await registerReceipt({
      proveedor: proveedor.value,
      fecha: fecha.value,
      lineas: lines.value.map((l) => ({
        producto_id: l.productId,
        cantidad: l.cantidad ?? 0,
        costo_unitario: l.costo ?? 0,
      })),
    })
    message.value = { kind: 'ok', text: 'Entrada registrada: el stock ya está actualizado.' }
    proveedor.value = ''
    lines.value = [{ productId: '', cantidad: null, costo: null }]
    await Promise.all([products.run(), restock.run()])
  } catch (e) {
    message.value = {
      kind: 'error',
      text: e instanceof Error ? e.message : 'No se pudo registrar la entrada',
    }
  } finally {
    busy.value = false
  }
}

function startExpired(product: Product) {
  expiring.value = product
  expiredQuantity.value = null
  expiredReason.value = ''
  message.value = null
}

async function submitExpired() {
  if (!expiring.value || expiredQuantity.value === null) return
  busy.value = true
  message.value = null
  try {
    const result = await markExpired(expiring.value.id, expiredQuantity.value, expiredReason.value)
    message.value = {
      kind: 'ok',
      text: `Vencidos registrados. Stock vendible de ${expiring.value.nombre}: ${result.stock_vendible}.`,
    }
    expiring.value = null
    await Promise.all([products.run(), expired.run(), restock.run()])
  } catch (e) {
    message.value = {
      kind: 'error',
      text: e instanceof Error ? e.message : 'No se pudo registrar el vencimiento',
    }
  } finally {
    busy.value = false
  }
}

onMounted(() => Promise.all([products.run(), expired.run(), restock.run()]))
</script>

<template>
  <div class="bodega">
    <p v-if="message" :class="message.kind === 'ok' ? 'ok' : 'error-text'" role="status">
      {{ message.text }}
    </p>

    <div class="tabs" role="tablist" aria-label="Secciones de bodega">
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'inventario'"
        :class="{ active: tab === 'inventario' }"
        @click="tab = 'inventario'"
      >
        Inventario
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'reposicion'"
        :class="{ active: tab === 'reposicion' }"
        @click="tab = 'reposicion'"
      >
        Reposición
        <span v-if="toOrder.length > 0" class="count">{{ toOrder.length }}</span>
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'entrada'"
        :class="{ active: tab === 'entrada' }"
        @click="tab = 'entrada'"
      >
        Entrada de mercadería
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'vencidos'"
        :class="{ active: tab === 'vencidos' }"
        @click="tab = 'vencidos'"
      >
        Vencidos
        <span v-if="expiredList.length > 0" class="count">{{ expiredList.length }}</span>
      </button>
    </div>

    <div v-show="tab === 'inventario'" role="tabpanel">
      <section class="card">
        <h2>Inventario</h2>
        <form class="search" role="search" @submit.prevent="products.run">
          <input
            v-model="search"
            type="search"
            placeholder="Buscar por nombre o código"
            aria-label="Buscar producto"
          />
          <button type="submit">Buscar</button>
        </form>

        <StateBox
          :loading="products.loading.value && !products.data.value"
          :error="products.error.value"
          :empty="list.length === 0"
          empty-text="No hay productos en el catálogo."
          @retry="products.run"
        >
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Producto</th>
                  <th>Stock</th>
                  <th>Vencidas</th>
                  <th>Disponibles</th>
                  <th>Costo</th>
                  <th>Estado</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in inventoryPager.visible.value" :key="p.id">
                  <td>{{ p.codigo }}</td>
                  <td>
                    {{ p.nombre }} <span v-if="p.es_perecedero" class="chip">Perecedero</span>
                  </td>
                  <td>{{ p.stock }}</td>
                  <td :class="{ 'expired-count': expiredOf(p.id) > 0 }">{{ expiredOf(p.id) }}</td>
                  <td>{{ Math.max(0, p.stock - expiredOf(p.id)) }}</td>
                  <td>{{ formatMoney(p.costo_actual) }}</td>
                  <td>
                    <span v-if="p.agotado" class="chip agotado">Agotado</span>
                    <span v-else class="chip">Disponible</span>
                  </td>
                  <td>
                    <button
                      class="sm"
                      type="button"
                      :disabled="p.stock <= 0"
                      @click="startExpired(p)"
                    >
                      Marcar vencidos
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <PagerBar :pager="inventoryPager" noun="Productos" />
        </StateBox>
      </section>
    </div>

    <div v-show="tab === 'reposicion'" role="tabpanel">
      <section class="card restock-card">
        <div class="restock-head">
          <h2>Reposición sugerida</h2>
          <label class="days">
            Ventas de los últimos
            <select v-model.number="restockDays" aria-label="Días a analizar" @change="restock.run">
              <option :value="7">7 días</option>
              <option :value="15">15 días</option>
              <option :value="30">30 días</option>
              <option :value="60">60 días</option>
              <option :value="90">90 días</option>
            </select>
          </label>
        </div>
        <p class="muted">
          Según lo vendido, estos son los productos que se acaban y cuántas unidades pedir para
          cubrir 14 días.
        </p>

        <StateBox
          :loading="restock.loading.value && !restock.data.value"
          :error="restock.error.value"
          :empty="restockItems.length === 0"
          empty-text="No hay productos para analizar."
          @retry="restock.run"
        >
          <p v-if="toOrder.length === 0" class="ok">Todo el inventario está bien por ahora.</p>
          <p v-else class="muted">
            Hay {{ toOrder.length }} producto{{ toOrder.length === 1 ? '' : 's' }} para pedir.
          </p>

          <div class="table-wrap">
            <table aria-label="Reposición sugerida">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Stock</th>
                  <th>Vendidas</th>
                  <th>Por día</th>
                  <th>Alcanza (días)</th>
                  <th>Estado</th>
                  <th>Pedir</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="i in restockPager.visible.value" :key="i.id">
                  <td>
                    {{ i.nombre }} <span class="muted">{{ i.codigo }}</span>
                  </td>
                  <td>{{ i.stock }}</td>
                  <td>{{ i.vendidas }}</td>
                  <td>{{ i.promedio_diario }}</td>
                  <td>{{ daysLeft(i.dias_restantes) }}</td>
                  <td>
                    <span class="chip" :class="statusClass[i.estado]">{{
                      statusLabel[i.estado]
                    }}</span>
                  </td>
                  <td :class="{ 'to-order': i.sugerido > 0 }">{{ i.sugerido }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <PagerBar :pager="restockPager" noun="Productos" />

          <h3>Los más vendidos</h3>
          <p v-if="topSellers.length === 0" class="muted">Todavía no hay ventas en este período.</p>
          <ol v-else class="top">
            <li v-for="i in topSellers" :key="i.id">
              {{ i.nombre }}: {{ i.vendidas }} unidades
              <span class="chip" :class="statusClass[i.estado]">{{ statusLabel[i.estado] }}</span>
            </li>
          </ol>
        </StateBox>
      </section>
    </div>

    <div v-show="tab === 'entrada'" role="tabpanel">
      <form class="card block receipt" @submit.prevent="submitReceipt">
        <h2>Entrada de mercadería</h2>
        <label>Proveedor <input v-model="proveedor" maxlength="120" required /></label>
        <label>Fecha <input v-model="fecha" type="date" required /></label>
        <fieldset v-for="(line, index) in lines" :key="index">
          <legend>Producto {{ index + 1 }}</legend>
          <select v-model="line.productId" required :aria-label="`Producto ${index + 1}`">
            <option value="" disabled>Elegir producto</option>
            <option v-for="p in list" :key="p.id" :value="p.id">{{ p.nombre }}</option>
          </select>
          <div class="row">
            <input
              v-model.number="line.cantidad"
              type="number"
              min="1"
              step="1"
              placeholder="Cantidad"
              required
              :aria-label="`Cantidad ${index + 1}`"
            />
            <input
              v-model.number="line.costo"
              type="number"
              min="0"
              step="0.01"
              placeholder="Costo unitario"
              required
              :aria-label="`Costo ${index + 1}`"
            />
          </div>
          <button v-if="lines.length > 1" class="sm" type="button" @click="removeLine(index)">
            Quitar
          </button>
        </fieldset>
        <button type="button" @click="addLine">Agregar otro producto</button>
        <button class="primary" type="submit" :disabled="busy">
          {{ busy ? 'Guardando…' : 'Registrar entrada' }}
        </button>
      </form>
    </div>

    <div v-show="tab === 'vencidos'" role="tabpanel">
      <section class="card expired-card">
        <h2>Vencidos en el estante</h2>
        <p class="muted">Siguen contados en el stock, pero no se pueden vender.</p>
        <p v-if="expiredList.length === 0" class="muted">No hay unidades vencidas.</p>
        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Motivo</th>
                <th>Registró</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in expiredPager.visible.value" :key="e.id">
                <td>
                  {{ e.producto }} <span class="muted">{{ e.codigo }}</span>
                </td>
                <td>{{ e.cantidad }}</td>
                <td>{{ e.motivo }}</td>
                <td>{{ e.registrada_por }}</td>
                <td>{{ dateTime(e.registrada_en) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <PagerBar :pager="expiredPager" noun="Vencidos" />
      </section>
    </div>

    <div v-if="expiring" class="overlay" @click.self="expiring = null">
      <div role="dialog" aria-modal="true" :aria-label="`Vencidos: ${expiring.nombre}`">
        <form class="card block dialog-card" @submit.prevent="submitExpired">
          <h2>Vencidos: {{ expiring.nombre }}</h2>
          <p class="muted">Stock actual: {{ expiring.stock }}</p>
          <label>
            Unidades vencidas
            <input
              v-model.number="expiredQuantity"
              type="number"
              min="1"
              :max="expiring.stock"
              step="1"
              required
            />
          </label>
          <label>Motivo <input v-model="expiredReason" maxlength="200" required /></label>
          <button class="primary" type="submit" :disabled="busy">
            {{ busy ? 'Guardando…' : 'Registrar vencidos' }}
          </button>
          <button type="button" :disabled="busy" @click="expiring = null">Cancelar</button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bodega {
  display: grid;
  gap: 1rem;
}
.bodega > * {
  min-width: 0;
}
.tabs {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
  border-bottom: 2px solid var(--border);
}
.tabs button {
  border: none;
  background: transparent;
  padding: 0.6rem 1rem;
  font-weight: 600;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  border-radius: 8px 8px 0 0;
  color: inherit;
}
.tabs button:hover {
  background: var(--verde-suave);
}
.tabs button.active {
  border-bottom-color: var(--verde-medio);
  color: var(--verde-medio);
}
.count {
  display: inline-block;
  min-width: 1.3rem;
  padding: 0 0.4rem;
  margin-left: 0.3rem;
  border-radius: 50px;
  background: var(--badsoft);
  color: var(--bad);
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
}
.block input,
.block select {
  width: 100%;
  box-sizing: border-box;
}
.receipt {
  max-width: 560px;
}
.table-wrap {
  overflow-x: auto;
}
.search {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
.search input {
  flex: 1;
}
table {
  border-collapse: collapse;
  width: 100%;
}
th,
td {
  text-align: left;
  padding: 0.45rem 0.5rem;
  border-bottom: 1px solid var(--border);
}
.chip.agotado {
  background: #fde2e2;
  color: var(--danger);
}
.block {
  display: grid;
  gap: 0.6rem;
}
.block label {
  display: grid;
  gap: 0.25rem;
}
fieldset {
  border: 1px solid var(--border);
  border-radius: 8px;
  display: grid;
  gap: 0.4rem;
}
.row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.ok {
  color: var(--verde-medio);
  font-weight: 600;
}
.restock-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.days {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.to-order {
  font-weight: 700;
}
.top {
  margin: 0;
  padding-left: 1.2rem;
  display: grid;
  gap: 0.3rem;
}
.expired-count {
  color: var(--bad);
  font-weight: 700;
}
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: grid;
  place-items: center;
  padding: 1rem;
  z-index: 50;
}
.dialog-card {
  width: min(420px, 100%);
}
.dialog-card input {
  width: 100%;
  box-sizing: border-box;
}
</style>
