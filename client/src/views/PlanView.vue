<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { getSubscription, listGateways, paySubscription, refreshSubscriptionPayment } from '@/services/api'
import { createCardMethod, createSinpeMethod } from '@/services/onvo'
import type { GatewayOption, SubscriptionPayment } from '@/services/types'
import { useSessionStore } from '@/stores/session'
import { useSubscriptionStore } from '@/stores/subscription'
import { digitsOnly, nationalId, parseExpiry, sinpePhone } from '@/utils/card'
import { formatMoney } from '@/utils/money'
import { goTo } from '@/utils/navigation'
import { SERVICE_STATUS, formatDay, lastSubscriptionPayment } from '@/utils/subscription'

const session = useSessionStore()
const banner = useSubscriptionStore()
const route = useRoute()
const router = useRouter()
const plan = useAsync(getSubscription)
const gateways = useAsync(listGateways)

const PAYMENT_STATUS: Record<SubscriptionPayment['estado'], { label: string; tone: string }> = {
  pendiente: { label: 'Pendiente', tone: 'warn' },
  aprobado: { label: 'Aprobado', tone: '' },
  rechazado: { label: 'Rechazado', tone: 'bad' },
}
const METHOD: Record<SubscriptionPayment['metodo'], string> = {
  efectivo: 'Efectivo',
  sinpe: 'SINPE a la plataforma',
  tarjeta: 'Tarjeta',
  sinpe_movil: 'SINPE Móvil',
}
const PROVIDER: Record<SubscriptionPayment['proveedor'], string> = { manual: 'Registrado por la plataforma', onvo: 'ONVO', paypal: 'PayPal' }

const months = ref(1)
const gateway = ref<GatewayOption['proveedor'] | null>(null)
const method = ref<'tarjeta' | 'sinpe_movil' | null>(null)
const card = reactive({ holder: '', number: '', expiry: '', cvv: '' })
const sinpe = reactive({ identification: '', phone: '' })
const submitting = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const following = ref<string | null>(null)

const available = computed(() => (gateways.data.value ?? []).filter((g) => g.configurada))
const chosen = computed(() => available.value.find((g) => g.proveedor === gateway.value) ?? null)
const total = computed(() => (plan.data.value?.precio_mensual ?? 0) * months.value)
const status = computed(() => (plan.data.value ? SERVICE_STATUS[plan.data.value.estado] : null))

watch(chosen, (option) => {
  if (option && (method.value === null || !option.metodos.includes(method.value))) method.value = option.metodos[0] ?? null
})

/** Reloads the rent here and in the warning of the header. */
async function reload() {
  await plan.run()
  void banner.load()
}

onMounted(async () => {
  await Promise.all([plan.run(), gateways.run()])
  gateway.value = (available.value.find((g) => g.predeterminada) ?? available.value[0])?.proveedor ?? null
  // Back from PayPal: follow the payment that went there.
  const pending = lastSubscriptionPayment.get()
  if (route.query.volver === 'paypal' && pending) {
    lastSubscriptionPayment.clear()
    await follow(pending)
    await router.replace({ name: 'plan' })
  }
})

/** Creates the ONVO payment method in the browser, so the card or the SINPE data never go through our API. */
async function onvoMethodId(option: GatewayOption): Promise<string> {
  const key = option.llave_publica
  if (!key) throw new Error('La pasarela no está lista para pagos. Intente con otra.')
  const billing = { name: session.user?.nombre ?? '', email: session.user?.correo ?? '' }
  if (method.value === 'sinpe_movil') {
    return createSinpeMethod(key, { identification: nationalId(sinpe.identification), phone: sinpePhone(sinpe.phone) }, billing)
  }
  const expiry = parseExpiry(card.expiry)
  if (!expiry) throw new Error('La fecha de vencimiento debe ser MM/AA.')
  return createCardMethod(
    key,
    { holderName: card.holder, number: digitsOnly(card.number), expMonth: expiry.month, expYear: expiry.year, cvv: digitsOnly(card.cvv) },
    billing,
  )
}

async function pay() {
  const option = chosen.value
  if (!option || !method.value || submitting.value) return
  submitting.value = true
  error.value = null
  message.value = null
  try {
    const providerMethodId = option.requiere_metodo_cliente ? await onvoMethodId(option) : null
    const payment = await paySubscription({ meses: months.value, pasarela: option.proveedor, metodo_pago: method.value, metodo_proveedor_id: providerMethodId })
    if (payment.url_aprobacion) {
      lastSubscriptionPayment.set(payment.id)
      goTo(payment.url_aprobacion)
      return
    }
    message.value = describe(payment)
    await reload()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo hacer el pago'
  } finally {
    submitting.value = false
  }
}

/** Asks the provider what happened with a payment that is still pending. */
async function follow(id: string) {
  following.value = id
  error.value = null
  try {
    message.value = describe(await refreshSubscriptionPayment(id))
    await reload()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo revisar el pago'
  } finally {
    following.value = null
  }
}

function describe(payment: SubscriptionPayment): string {
  if (payment.estado === 'aprobado') return `Pago aprobado: se sumaron ${payment.meses} ${payment.meses === 1 ? 'mes' : 'meses'} al plan.`
  if (payment.estado === 'rechazado') return 'El pago fue rechazado. No se cobró nada; puede intentar de nuevo.'
  return payment.instrucciones?.mensaje ?? 'El pago está pendiente. Revise su estado en unos minutos.'
}
</script>

<template>
  <div class="plan">
    <h1>Plan del negocio</h1>
    <StateBox :loading="plan.loading.value && !plan.data.value" :error="plan.error.value" @retry="plan.run">
      <template v-if="plan.data.value && status">
        <p v-if="plan.data.value.aviso" class="note" :class="status.tone" role="status">{{ plan.data.value.aviso }}</p>

        <section class="card summary">
          <div>
            <small class="muted">Estado</small>
            <b><span class="chip" :class="status.tone">{{ status.label }}</span></b>
          </div>
          <div>
            <small class="muted">{{ plan.data.value.en_prueba ? 'Prueba gratis hasta' : 'Pagado hasta' }}</small>
            <b>{{ formatDay(plan.data.value.vence_el) }}</b>
          </div>
          <div>
            <small class="muted">{{ plan.data.value.dias_restantes >= 0 ? 'Días restantes' : 'Días vencido' }}</small>
            <b>{{ Math.abs(plan.data.value.dias_restantes) }}</b>
          </div>
          <div>
            <small class="muted">Mensualidad</small>
            <b>{{ formatMoney(plan.data.value.precio_mensual) }}</b>
          </div>
        </section>

        <div class="co">
          <form class="card form" @submit.prevent="pay">
            <div class="step"><i>1</i><h3>Pagar el plan</h3></div>
            <label>
              Meses
              <select v-model.number="months">
                <option v-for="n in 12" :key="n" :value="n">{{ n }} {{ n === 1 ? 'mes' : 'meses' }}</option>
              </select>
            </label>

            <StateBox
              :loading="gateways.loading.value"
              :error="gateways.error.value"
              :empty="available.length === 0"
              empty-text="No hay pasarelas de pago configuradas. Pague por SINPE o en efectivo a la plataforma."
              @retry="gateways.run"
            >
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
                  <small>{{ g.requiere_metodo_cliente ? 'Tarjeta o SINPE Móvil, en colones' : 'Se le lleva a PayPal para aprobar' }}</small>
                </button>
              </div>
              <div v-if="chosen && chosen.metodos.length > 1" class="opts">
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
                  <b>{{ m === 'tarjeta' ? '💳 Tarjeta' : '📱 SINPE Móvil' }}</b>
                </button>
              </div>

              <template v-if="chosen?.requiere_metodo_cliente && method === 'tarjeta'">
                <label>Nombre en la tarjeta <input v-model="card.holder" required autocomplete="cc-name" /></label>
                <label>Número de tarjeta <input v-model="card.number" inputmode="numeric" required autocomplete="cc-number" /></label>
                <div class="row">
                  <label>Vence (MM/AA) <input v-model="card.expiry" placeholder="MM/AA" required autocomplete="cc-exp" /></label>
                  <label>CVV <input v-model="card.cvv" inputmode="numeric" maxlength="4" required autocomplete="cc-csc" /></label>
                </div>
              </template>
              <template v-if="chosen?.requiere_metodo_cliente && method === 'sinpe_movil'">
                <label>Cédula <input v-model="sinpe.identification" inputmode="numeric" required /></label>
                <label>Teléfono SINPE <input v-model="sinpe.phone" inputmode="tel" required autocomplete="tel" /></label>
              </template>
            </StateBox>

            <p v-if="error" class="note bad" role="alert">{{ error }}</p>
            <p v-if="message" class="note" role="status">{{ message }}</p>
            <button class="primary full lg" type="submit" :disabled="submitting || !chosen || !method">
              {{ submitting ? 'Procesando…' : `Pagar ${formatMoney(total)}` }}
            </button>
            <p class="muted small">
              ¿Prefiere pagar en efectivo o por SINPE al número de la plataforma? Avise a la plataforma y ahí se registra el pago.
            </p>
          </form>

          <section class="card">
            <div class="step"><i>2</i><h3>Pagos</h3></div>
            <p v-if="plan.data.value.pagos.length === 0" class="muted">Todavía no hay pagos.</p>
            <div v-for="p in plan.data.value.pagos" :key="p.id" class="li">
              <div>
                <b>{{ formatMoney(p.monto) }}</b> · {{ p.meses }} {{ p.meses === 1 ? 'mes' : 'meses' }}
                <div class="muted small">{{ formatDay(p.creado_el) }} · {{ METHOD[p.metodo] }} · {{ PROVIDER[p.proveedor] }}</div>
                <div v-if="p.instrucciones" class="muted small">{{ p.instrucciones.mensaje }}</div>
              </div>
              <div class="right">
                <span class="chip" :class="PAYMENT_STATUS[p.estado].tone">{{ PAYMENT_STATUS[p.estado].label }}</span>
                <button v-if="p.estado === 'pendiente' && p.proveedor !== 'manual'" class="sm" type="button" :disabled="following === p.id" @click="follow(p.id)">
                  {{ following === p.id ? 'Revisando…' : 'Revisar' }}
                </button>
              </div>
            </div>
          </section>
        </div>
      </template>
    </StateBox>
  </div>
</template>

<style scoped>
.plan { max-width: 1000px; margin: 0 auto; display: grid; gap: 1rem; }
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.summary > div { display: grid; gap: 0.2rem; }
.summary b { font-size: 1.25rem; font-family: var(--font-display); color: var(--titulo); }
.form { display: grid; gap: 0.8rem; }
.form label { display: grid; gap: 0.25rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
.opts + .opts { margin-top: 0.7rem; }
.right { display: grid; gap: 0.3rem; justify-items: end; }
.small { font-size: 0.82rem; margin: 0; }
</style>
