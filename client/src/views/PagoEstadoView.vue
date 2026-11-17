<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { getPurchasePayment } from '@/services/api'
import { formatMoney } from '@/utils/money'
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
        <p class="amount">{{ formatMoney(payment.data.value.monto) }}</p>

        <div v-if="payment.data.value.estado === 'pagado'" class="status ok" role="status">
          <div class="okmark" aria-hidden="true">✓</div>
          <b>¡Pago recibido!</b>
          <p>Gracias por su compra.</p>
        </div>
        <div v-else-if="payment.data.value.estado === 'fallido'" class="status bad" role="alert">
          <div class="ic" aria-hidden="true">✗</div>
          <b>El pago no se pudo completar</b>
          <p>
            <span v-if="payment.data.value.codigo_rechazo">({{ payment.data.value.codigo_rechazo }}) </span>Sus
            productos ya no están reservados; puede volver a intentarlo desde la tienda.
          </p>
        </div>
        <div v-else-if="payment.data.value.estado === 'reembolsado'" class="status" role="status">
          <b>Este pago fue reembolsado.</b>
        </div>
        <template v-else>
          <div class="status" role="status">
            <div class="spin"></div>
            <b>Esperando la confirmación del pago…</b>
            <p class="muted">Esta página se actualiza sola.</p>
          </div>
          <p v-if="payment.data.value.instrucciones" class="note">
            {{ payment.data.value.instrucciones.mensaje }}
          </p>
          <p v-if="payment.data.value.url_aprobacion">
            <a :href="payment.data.value.url_aprobacion">Aprobar el pago en PayPal</a>
          </p>
        </template>
      </template>
    </StateBox>

    <RouterLink :to="{ name: 'tienda' }">Volver a la tienda</RouterLink>
  </div>
</template>

<style scoped>
.estado { max-width: 520px; margin: 2rem auto; display: grid; gap: 0.8rem; }
.amount { font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; margin: 0; color: var(--titulo); }
</style>
