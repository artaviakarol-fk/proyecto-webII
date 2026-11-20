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
import { digitsOnly, nationalId, parseExpiry, sinpePhone } from '@/utils/card'
import { formatMoney } from '@/utils/money'
import { goTo, lastSale } from '@/utils/navigation'

const router = useRouter()
const reservations = useReservationsStore()
const session = useSessionStore()
const gateways = useAsync(listGateways)

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
      { identification: nationalId(sinpe.value.identification), phone: sinpePhone(sinpe.value.phone) },
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

    <StateBox
      :loading="reservations.loading && reservations.items.length === 0"
      :error="reservations.error"
      :empty="reservations.items.length === 0"
      empty-text="No tiene productos en su compra. Vuelva a la tienda y elija lo que necesita."
      @retry="reservations.load"
    >
      <div class="co">
        <div class="stack">
          <div class="card">
            <div class="step"><i>1</i><h3>¿Cómo quiere pagar?</h3></div>
            <StateBox
              :loading="gateways.loading.value"
              :error="gateways.error.value"
              :empty="available.length === 0"
              empty-text="No hay pasarelas de pago disponibles por ahora."
              @retry="gateways.run"
            >
              <label class="f">Procesar el pago con</label>
              <div class="opts" role="radiogroup" aria-label="Pasarela de pago">
                <button
                  v-for="g in available"
                  :key="g.proveedor"
                  type="button"
                  class="opt"
                  :class="{ on: gateway === g.proveedor }"
                  role="radio"
                  :aria-checked="gateway === g.proveedor"
                  @click="gateway = g.proveedor"
                >
                  <b>{{ g.nombre }}</b>
                  <small>
                    {{
                      g.requiere_metodo_cliente
                        ? 'Procesador costarricense: tarjeta y SINPE Móvil, en colones'
                        : 'Tarjeta o saldo PayPal: se le lleva a PayPal para aprobar'
                    }}
                  </small>
                </button>
              </div>

              <template v-if="chosen && chosen.metodos.length > 1">
                <label class="f gap">Método</label>
                <div class="opts" role="radiogroup" aria-label="Método de pago">
                  <button
                    v-for="m in chosen.metodos"
                    :key="m"
                    type="button"
                    class="opt"
                    :class="{ on: method === m }"
                    role="radio"
                    :aria-checked="method === m"
                    @click="method = m"
                  >
                    <span class="ic" aria-hidden="true">{{ m === 'tarjeta' ? '💳' : '📱' }}</span>
                    <b>{{ methodLabel[m] ?? m }}</b>
                    <small>{{ m === 'tarjeta' ? 'Débito o crédito' : 'Transferencia desde su banco' }}</small>
                  </button>
                </div>
              </template>
            </StateBox>
          </div>

          <form v-if="chosen" id="pay-form" class="card form" @submit.prevent="pay">
            <div class="step"><i>2</i><h3>Sus datos de pago</h3></div>

            <template v-if="chosen.requiere_metodo_cliente && method === 'tarjeta'">
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

            <template v-if="chosen.requiere_metodo_cliente && method === 'sinpe_movil'">
              <label>Cédula <input v-model="sinpe.identification" inputmode="numeric" required /></label>
              <label>Teléfono SINPE <input v-model="sinpe.phone" inputmode="tel" required autocomplete="tel" /></label>
            </template>

            <p v-if="!chosen.requiere_metodo_cliente" class="note">
              Se le llevará a {{ chosen.nombre }} para aprobar el pago y luego volverá aquí.
            </p>

            <label>
              Cédula (solo si compra licor)
              <input v-model="buyerId" inputmode="numeric" />
            </label>
            <p v-if="error" class="error-text" role="alert">{{ error }}</p>
          </form>
        </div>

        <div class="card">
          <h3>Resumen del pedido</h3>
          <div v-for="item in reservations.items" :key="item.id" class="li">
            <span>{{ item.cantidad }} × {{ item.nombre }}</span>
            <b>{{ formatMoney(item.subtotal) }}</b>
          </div>
          <div class="tot">
            <div class="big"><span>Total</span><span>{{ formatMoney(reservations.total) }}</span></div>
          </div>
          <button class="primary full lg" type="submit" form="pay-form" :disabled="submitting || !chosen || !method">
            {{ submitting ? 'Procesando pago…' : `Pagar ${formatMoney(reservations.total)}${chosen ? ' con ' + chosen.nombre : ''}` }}
          </button>
        </div>
      </div>
    </StateBox>
  </div>
</template>

<style scoped>
.pago { max-width: 1000px; margin: 0 auto; }
.stack { display: grid; gap: 1rem; }
.form { display: grid; gap: 0.8rem; }
.form label { display: grid; gap: 0.25rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
.f { display: block; margin-bottom: 0.4rem; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
.gap { margin-top: 1rem; }
</style>
