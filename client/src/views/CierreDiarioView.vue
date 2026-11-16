<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { dailyClosing } from '@/services/api'
import { formatMoney } from '@/utils/money'

const fecha = ref('')
const { data, loading, error, run } = useAsync(() => dailyClosing(fecha.value || undefined))

const methods = computed(() => {
  const totals = data.value?.totales
  return totals
    ? [
        { label: 'Efectivo', value: totals.efectivo },
        { label: 'Tarjeta', value: totals.tarjeta },
        { label: 'SINPE Móvil', value: totals.sinpe_movil },
        { label: 'Fiado', value: totals.fiado },
      ]
    : []
})

onMounted(run)
watch(fecha, run)
</script>

<template>
  <section class="card">
    <h1>Cierre diario</h1>
    <label>Fecha <input v-model="fecha" type="date" /></label>
    <StateBox
      :loading="loading"
      :error="error"
      :empty="data?.cantidad_ventas === 0"
      empty-text="No hubo ventas en esta fecha."
      @retry="run"
    >
      <div v-if="data">
        <p class="muted">{{ data.fecha }} · {{ data.cantidad_ventas }} ventas</p>
        <table>
          <tbody>
            <tr v-for="m in methods" :key="m.label">
              <th>{{ m.label }}</th>
              <td>{{ formatMoney(m.value) }}</td>
            </tr>
            <tr class="total">
              <th>Total</th>
              <td>{{ formatMoney(data.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </StateBox>
  </section>
</template>

<style scoped>
table { border-collapse: collapse; min-width: 320px; margin-top: 0.5rem; }
th { text-align: left; padding: 0.4rem 1rem 0.4rem 0; }
td { text-align: right; }
.total { border-top: 2px solid var(--border); font-weight: 700; }
</style>
