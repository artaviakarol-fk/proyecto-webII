<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { cancelSale, getSale, listSales } from '@/services/api'
import type { Sale, SaleStatus } from '@/services/types'
import { formatMoney } from '@/utils/money'

const estado = ref<SaleStatus | ''>('')
const fecha = ref('')
const page = ref(1)
const sales = useAsync(() =>
  listSales({ estado: estado.value || undefined, fecha: fecha.value || undefined, pagina: page.value }),
)

// The sale the user opened to see its lines, and the one waiting for the "yes, cancel it" confirmation.
const detail = ref<Sale | null>(null)
const detailError = ref<string | null>(null)
const confirming = ref<string | null>(null)
const cancelling = ref(false)
const notice = ref<string | null>(null)

const statusLabel: Record<string, string> = { abierta: 'Abierta', cobrada: 'Cobrada', anulada: 'Anulada' }
const methodLabel: Record<string, string> = {
  efectivo: 'Efectivo',
  tarjeta: 'Tarjeta',
  sinpe_movil: 'SINPE Móvil',
  fiado: 'Fiado',
}
const time = (iso: string) => new Date(iso).toLocaleString('es-CR')

async function open(saleId: string) {
  detailError.value = null
  confirming.value = null
  try {
    detail.value = await getSale(saleId)
  } catch (e) {
    detail.value = null
    detailError.value = e instanceof Error ? e.message : 'No se pudo cargar la venta'
  }
}

async function confirmCancel(saleId: string) {
  cancelling.value = true
  detailError.value = null
  try {
    detail.value = await cancelSale(saleId)
    notice.value = 'Venta anulada: las unidades volvieron al inventario.'
    confirming.value = null
    await sales.run()
  } catch (e) {
    // A 409 is a rule of the business (for example, a sale on fiado with abonos); the API says why.
    detailError.value = e instanceof Error ? e.message : 'No se pudo anular la venta'
  } finally {
    cancelling.value = false
  }
}

function goToPage(next: number) {
  page.value = next
  void sales.run()
}

onMounted(sales.run)
watch([estado, fecha], () => {
  page.value = 1
  detail.value = null
  notice.value = null
  void sales.run()
})
</script>

<template>
  <div class="ventas">
    <section class="card">
      <h1>Ventas</h1>
      <div class="filters">
        <label>
          Estado
          <select v-model="estado">
            <option value="">Todas</option>
            <option value="abierta">Abiertas</option>
            <option value="cobrada">Cobradas</option>
            <option value="anulada">Anuladas</option>
          </select>
        </label>
        <label>Día <input v-model="fecha" type="date" /></label>
      </div>

      <p v-if="notice" class="ok" role="status">{{ notice }}</p>

      <StateBox
        :loading="sales.loading.value && !sales.data.value"
        :error="sales.error.value"
        :empty="sales.data.value?.data.length === 0"
        empty-text="No hay ventas con esos filtros."
        @retry="sales.run"
      >
        <table>
          <thead>
            <tr><th>Fecha</th><th>Cajero</th><th>Método</th><th>Estado</th><th>Total</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="sale in sales.data.value?.data ?? []" :key="sale.id" :class="{ selected: detail?.id === sale.id }">
              <td>{{ time(sale.creada_en) }}</td>
              <td>{{ sale.cajero.nombre }}</td>
              <td>{{ methodLabel[sale.metodo_pago] ?? sale.metodo_pago }}</td>
              <td><span class="chip" :class="sale.estado">{{ statusLabel[sale.estado] ?? sale.estado }}</span></td>
              <td>{{ formatMoney(sale.total) }}</td>
              <td><button type="button" @click="open(sale.id)">Ver detalle</button></td>
            </tr>
          </tbody>
        </table>
        <nav v-if="(sales.data.value?.meta.ultima_pagina ?? 1) > 1" class="pager" aria-label="Páginas">
          <button type="button" :disabled="page <= 1" @click="goToPage(page - 1)">Anterior</button>
          <span class="muted">Página {{ page }} de {{ sales.data.value?.meta.ultima_pagina }}</span>
          <button
            type="button"
            :disabled="page >= (sales.data.value?.meta.ultima_pagina ?? 1)"
            @click="goToPage(page + 1)"
          >
            Siguiente
          </button>
        </nav>
      </StateBox>
    </section>

    <aside v-if="detail || detailError" class="card" aria-label="Detalle de la venta">
      <p v-if="detailError" class="error-text" role="alert">{{ detailError }}</p>
      <template v-if="detail">
        <h2>Venta {{ statusLabel[detail.estado] ?? detail.estado }}</h2>
        <p class="muted">{{ time(detail.creada_en) }} · {{ detail.cajero.nombre }}</p>
        <ul class="lines">
          <li v-for="line in detail.lineas" :key="line.producto_id">
            {{ line.cantidad }} × {{ line.nombre }} <span>{{ formatMoney(line.subtotal) }}</span>
          </li>
        </ul>
        <p class="total">Total: {{ formatMoney(detail.total) }}</p>
        <p v-if="detail.anulada_en" class="muted">Anulada el {{ time(detail.anulada_en) }}</p>

        <template v-if="detail.estado !== 'anulada'">
          <button v-if="confirming !== detail.id" type="button" @click="confirming = detail.id">
            Anular venta
          </button>
          <div v-else class="confirm" role="alertdialog" aria-label="Confirmar anulación">
            <p>¿Anular esta venta? Las unidades volverán al inventario.</p>
            <button class="primary" type="button" :disabled="cancelling" @click="confirmCancel(detail.id)">
              {{ cancelling ? 'Anulando…' : 'Sí, anular' }}
            </button>
            <button type="button" :disabled="cancelling" @click="confirming = null">No, volver</button>
          </div>
        </template>
      </template>
    </aside>
  </div>
</template>

<style scoped>
.ventas { display: grid; grid-template-columns: 1fr 340px; gap: 1rem; align-items: start; }
.filters { display: flex; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.filters label { display: grid; gap: 0.25rem; }
table { border-collapse: collapse; width: 100%; }
th, td { text-align: left; padding: 0.45rem 0.5rem; border-bottom: 1px solid var(--border); }
tr.selected { background: var(--verde-suave); }
.chip.anulada { background: #fde2e2; color: var(--danger); }
.chip.abierta { background: #fff1e0; color: var(--naranja-fuerte); }
.pager { display: flex; gap: 1rem; align-items: center; justify-content: center; margin-top: 1rem; }
.lines { list-style: none; margin: 0 0 0.5rem; padding: 0; display: grid; gap: 0.3rem; }
.lines li { display: flex; justify-content: space-between; gap: 1rem; }
.total { font-weight: 600; }
.ok { color: var(--verde-medio); font-weight: 600; }
.confirm { display: grid; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--naranja-aa); border-radius: 8px; }
@media (max-width: 900px) { .ventas { grid-template-columns: 1fr; } }
</style>
