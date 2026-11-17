<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { listExpired, listProducts, markExpired, registerReceipt } from '@/services/api'
import type { Product } from '@/services/types'
import { formatMoney } from '@/utils/money'

const search = ref('')
const products = useAsync(() => listProducts(search.value || undefined))
const expired = useAsync(listExpired)

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
const expiredList = computed(() => expired.data.value ?? [])

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
    await products.run()
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
    await Promise.all([products.run(), expired.run()])
  } catch (e) {
    message.value = {
      kind: 'error',
      text: e instanceof Error ? e.message : 'No se pudo registrar el vencimiento',
    }
  } finally {
    busy.value = false
  }
}

onMounted(() => Promise.all([products.run(), expired.run()]))
</script>

<template>
  <div class="bodega">
    <div class="main">
      <section class="card">
        <h1>Inventario</h1>
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
                <tr v-for="p in list" :key="p.id">
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
        </StateBox>
      </section>

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
              <tr v-for="e in expiredList" :key="e.id">
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
      </section>
    </div>

    <aside class="side">
      <p v-if="message" :class="message.kind === 'ok' ? 'ok' : 'error-text'" role="status">
        {{ message.text }}
      </p>

      <form class="card block" @submit.prevent="submitReceipt">
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

      <form v-if="expiring" class="card block" @submit.prevent="submitExpired">
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
    </aside>
  </div>
</template>

<style scoped>
.bodega {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 360px);
  gap: 1rem;
  align-items: start;
}
.bodega > * {
  min-width: 0;
}
.side input,
.side select {
  width: 100%;
  box-sizing: border-box;
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
.side {
  display: grid;
  gap: 1rem;
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
.expired-count {
  color: var(--bad);
  font-weight: 700;
}
.main {
  display: grid;
  gap: 1rem;
}
@media (max-width: 1000px) {
  .bodega {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
