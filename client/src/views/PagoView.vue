<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { buy, listGateways } from '@/services/api'
import { createCardMethod, createSinpeMethod } from '@/services/onvo'
import type { GatewayId, PayMethod } from '@/services/types'
import { useReservationsStore } from '@/stores/reservations'
import { useSessionStore } from '@/stores/session'
import { digitsOnly, parseExpiry } from '@/utils/card'
import { goTo, lastSale } from '@/utils/navigation'

const router = useRouter()
const reservations = useReservationsStore()
const session = useSessionStore()
const gateways = useAsync(listGateways)

const money = (value: number) => `₡${value.toLocaleString('es-CR')}`
const methodLabel: Record<string, string> = { tarjeta: 'Tarjeta', sinpe_movil: 'SINPE Móvil' }

// The cliente chooses the gateway and the method for THIS payment; nothing is read from the .env.
const gateway = ref<GatewayId | null>(null)
const method = ref<PayMethod | null>(null)

const card = ref({ holder: '', number: '', expiry: '', cvv: '' })
const sinpe = ref({ identification: '', phone: '' })
const buyerId = ref('')

const submitting = ref(false)
const error = ref<string | null>(null)

const available = computed(() => (gateways.data.value ?? []).filter((g) => g.configurada))
const chosen = computed(() => available.value.find((g) => g.proveedor === gateway.value) ?? null)

watch(chosen, (option) => {
  // Keep the method valid for the gateway just chosen.
  if (option && (method.value === null || !option.metodos.includes(method.value))) {
    method.value = option.metodos[0] ?? null
  }
})

onMounted(async () => {
  void reservations.load()
  await gateways.run()
  const preferred = available.value.find((g) => g.predeterminada) ?? available.value[0]
  gateway.value = preferred?.proveedor ?? null
})

/** Builds the ONVO payment method in the browser, so card data never goes through our API. */
async function onvoMethodId(option: NonNullable<typeof chosen.value>): Promise<string> {
  const key = option.llave_publica
  if (!key) throw new Error('La pasarela no está lista para pagos. Intente con otra.')
  const billing = { name: session.user?.nombre ?? '', email: session.user?.correo ?? '' }

  if (method.value === 'sinpe_movil') {
    return createSinpeMethod(
      key,
      { identification: digitsOnly(sinpe.value.identification), phone: digitsOnly(sinpe.value.phone) },
      billing,
    )
  }

  const expiry = parseExpiry(card.value.expiry)
  if (!expiry) throw new Error('La fecha de vencimiento debe ser MM/AA.')
  return createCardMethod(
    key,
    {
      holderName: card.value.holder,
      number: digitsOnly(card.value.number),
      expMonth: expiry.month,
      expYear: expiry.year,
      cvv: digitsOnly(card.value.cvv),
    },
    billing,
  )
}

async function pay() {
  if (!chosen.value || !method.value || submitting.value) return
  submitting.value = true
  error.value = null
  try {
    const providerMethodId = chosen.value.requiere_metodo_cliente ? await onvoMethodId(chosen.value) : null
    const payment = await buy({
      metodo_pago: method.value,
      pasarela: chosen.value.proveedor,
      metodo_proveedor_id: providerMethodId,
      cedula_comprador: buyerId.value.trim() === '' ? null : buyerId.value.trim(),
    })
    lastSale.set(payment.venta_id)
    reservations.clear()

    // PayPal: the cliente approves the payment on PayPal's page and comes back to the status page.
    if (payment.url_aprobacion) {
      goTo(payment.url_aprobacion)
      return
    }
    await router.push({ name: 'pago-estado', params: { venta: payment.venta_id } })
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo completar el pago'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="pago">
    <h1>Pagar</h1>

    <section class="card">
      <h2>Su compra</h2>
      <StateBox
        :loading="reservations.loading && reservations.items.length === 0"
        :error="reservations.error"
        :empty="reservations.items.length === 0"
        empty-text="No tiene reservas activas. Vuelva a la tienda y reserve productos."
        @retry="reservations.load"
      >
        <ul class="lines">
          <li v-for="item in reservations.items" :key="item.id">
            {{ item.nombre }} × {{ item.cantidad }} <span>{{ money(item.subtotal) }}</span>
          </li>
        </ul>
        <p class="total">Total: {{ money(reservations.total) }}</p>
      </StateBox>
    </section>

    <section v-if="reservations.items.length > 0" class="card">
      <h2>Forma de pago</h2>
      <StateBox
        :loading="gateways.loading.value"
        :error="gateways.error.value"
        :empty="available.length === 0"
        empty-text="No hay pasarelas de pago disponibles por ahora."
        @retry="gateways.run"
      >
        <form class="form" @submit.prevent="pay">
          <fieldset>
            <legend>Pasarela</legend>
            <label v-for="g in available" :key="g.proveedor" class="choice">
              <input v-model="gateway" type="radio" name="pasarela" :value="g.proveedor" />
              {{ g.nombre }}
            </label>
          </fieldset>

          <fieldset v-if="chosen && chosen.metodos.length > 1">
            <legend>Método</legend>
            <label v-for="m in chosen.metodos" :key="m" class="choice">
              <input v-model="method" type="radio" name="metodo" :value="m" />
              {{ methodLabel[m] ?? m }}
            </label>
          </fieldset>

          <template v-if="chosen?.requiere_metodo_cliente && method === 'tarjeta'">
            <label>Nombre en la tarjeta <input v-model="card.holder" required autocomplete="cc-name" /></label>
            <label>
              Número de tarjeta
              <input v-model="card.number" inputmode="numeric" required autocomplete="cc-number" />
            </label>
            <div class="row">
              <label>
                Vence (MM/AA)
                <input v-model="card.expiry" placeholder="MM/AA" required autocomplete="cc-exp" />
              </label>
              <label>
                CVV
                <input v-model="card.cvv" inputmode="numeric" maxlength="4" required autocomplete="cc-csc" />
              </label>
            </div>
          </template>

          <template v-if="chosen?.requiere_metodo_cliente && method === 'sinpe_movil'">
            <label>
              Cédula
              <input v-model="sinpe.identification" inputmode="numeric" required />
            </label>
            <label>
              Teléfono SINPE
              <input v-model="sinpe.phone" inputmode="tel" required autocomplete="tel" />
            </label>
          </template>

          <p v-if="chosen && !chosen.requiere_metodo_cliente" class="muted">
            Se le llevará a {{ chosen.nombre }} para aprobar el pago y luego volverá aquí.
          </p>

          <label>
            Cédula (solo si compra licor)
            <input v-model="buyerId" inputmode="numeric" />
          </label>

          <p v-if="error" class="error-text" role="alert">{{ error }}</p>
          <button class="primary" type="submit" :disabled="submitting || !chosen || !method">
            {{ submitting ? 'Procesando pago…' : `Pagar ${money(reservations.total)}` }}
          </button>
        </form>
      </StateBox>
    </section>
  </div>
</template>

<style scoped>
.pago { max-width: 560px; margin: 0 auto; display: grid; gap: 1rem; }
.lines { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.4rem; }
.lines li { display: flex; justify-content: space-between; gap: 1rem; }
.total { font-weight: 600; }
.form { display: grid; gap: 0.8rem; }
label { display: grid; gap: 0.25rem; }
fieldset { border: 1px solid var(--border); border-radius: 8px; display: grid; gap: 0.4rem; }
.choice { display: flex; gap: 0.5rem; align-items: center; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
</style>
