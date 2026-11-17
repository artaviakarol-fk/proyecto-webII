<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { getPurchasePayment } from '@/services/api'
import { lastSale } from '@/utils/navigation'

const POLL_MS = 5000

const route = useRoute()
// PayPal sends the cliente back without the sale in the URL, so the one saved before leaving is used.
const saleId = computed(() => {
  const fromRoute = route.params.venta
  return typeof fromRoute === 'string' && fromRoute !== '' ? fromRoute : lastSale.get()
})

const payment = useAsync(() => getPurchasePayment(saleId.value ?? ''))
const pending = computed(() => payment.data.value?.estado === 'pendiente')
let timer: ReturnType<typeof setInterval> | null = null

const money = (value: number) => `₡${value.toLocaleString('es-CR')}`

function stop() {
  if (timer) clearInterval(timer)
  timer = null
}

// While the payment is pending (SINPE not confirmed, PayPal not captured) the page asks again every 5 seconds.
async function refresh() {
  await payment.run()
  if (!pending.value) stop()
}

onMounted(async () => {
  if (!saleId.value) return
  await refresh()
  if (pending.value) timer = setInterval(() => void refresh(), POLL_MS)
})
onBeforeUnmount(stop)
</script>

<template>
  <div class="estado card">
    <h1>Estado de su pago</h1>

    <p v-if="!saleId" class="muted">No hay ninguna compra para mostrar.</p>

    <StateBox
      v-else
      :loading="payment.loading.value && !payment.data.value"
      :error="payment.error.value"
      @retry="refresh"
    >
      <template v-if="payment.data.value">
        <p class="amount">{{ money(payment.data.value.monto) }}</p>

        <p v-if="payment.data.value.estado === 'pagado'" class="ok" role="status">
          ¡Pago recibido! Gracias por su compra.
        </p>
        <p v-else-if="payment.data.value.estado === 'fallido'" class="error-text" role="alert">
          El pago no se pudo completar<span v-if="payment.data.value.codigo_rechazo">
            ({{ payment.data.value.codigo_rechazo }})</span
          >. Sus reservas ya no están activas; puede volver a intentarlo desde la tienda.
        </p>
        <p v-else-if="payment.data.value.estado === 'reembolsado'" role="status">
          Este pago fue reembolsado.
        </p>
        <template v-else>
          <p role="status">Esperando la confirmación del pago…</p>
          <p v-if="payment.data.value.instrucciones" class="instrucciones">
            {{ payment.data.value.instrucciones.mensaje }}
          </p>
          <p v-if="payment.data.value.url_aprobacion">
            <a :href="payment.data.value.url_aprobacion">Aprobar el pago en PayPal</a>
          </p>
          <p class="muted">Esta página se actualiza sola.</p>
        </template>
      </template>
    </StateBox>

    <RouterLink :to="{ name: 'tienda' }">Volver a la tienda</RouterLink>
  </div>
</template>

<style scoped>
.estado { max-width: 520px; margin: 2rem auto; display: grid; gap: 0.8rem; }
.amount { font-size: 1.6rem; font-weight: 600; margin: 0; color: var(--verde-oscuro); }
.ok { color: var(--verde-medio); font-weight: 600; }
.instrucciones { background: var(--verde-suave); padding: 0.75rem; border-radius: 8px; }
</style>
