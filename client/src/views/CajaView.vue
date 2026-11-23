<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import * as api from '@/services/api'
import type { Product, SaleDraft } from '@/services/types'
import { draftLines, draftNotFound, mergeDraftIntoCart, type CartLine } from '@/utils/draft'
import { formatMoney } from '@/utils/money'
import { authorizationCode, MAX_REFERENCE } from '@/utils/register'

type Method = 'efectivo' | 'fiado' | 'tarjeta' | 'sinpe_movil'
type Datafono = 'esperando' | 'aprobada' | 'rechazada'
const MAX_TEXT = 500

const search = ref('')
const products = useAsync(() => api.listProducts(search.value))
const customers = useAsync(() => api.listCustomers())

const dictation = ref('')
const drafted = ref<SaleDraft | null>(null)
const draft = useAsync(async () => {
  const result = await api.draftSale(dictation.value)
  const found = await Promise.all(draftLines(result).map((l) => api.getProduct(l.producto_id)))
  return { draft: result, products: new Map(found.map((p) => [p.id, p])) }
})

const cart = ref<CartLine[]>([])
const method = ref<Method>('efectivo')
const customerId = ref('')
const cedula = ref('')

const submitting = ref(false)
const submitError = ref<string | null>(null)
const confirmation = ref<string | null>(null)
// The simulated datáfono answers the card payment, and the cashier records the SINPE transfer that arrived.
const datafono = ref<Datafono>('esperando')
const authorization = ref('')
const voucher = ref('')
const transferReceived = ref(false)

const total = computed(() => cart.value.reduce((sum, l) => sum + l.product.precio_venta * l.qty, 0))
const needsCedula = computed(() => cart.value.some((l) => l.product.es_licor))
const selectedCustomer = computed(() =>
  customers.data.value?.find((c) => c.id === customerId.value),
)
const exceedsLimit = computed(
  () =>
    method.value === 'fiado' &&
    !!selectedCustomer.value &&
    total.value > selectedCustomer.value.disponible,
)
const notFound = computed(() => (drafted.value ? draftNotFound(drafted.value) : []))
const canDictate = computed(
  () => dictation.value.trim() !== '' && !draft.loading.value && !submitting.value,
)
const paymentReady = computed(() => {
  if (method.value === 'fiado') return !!customerId.value && !exceedsLimit.value
  if (method.value === 'tarjeta') return datafono.value === 'aprobada'
  if (method.value === 'sinpe_movil') return transferReceived.value && voucher.value.trim() !== ''
  return true
})
const canPay = computed(
  () =>
    cart.value.length > 0 &&
    !submitting.value &&
    (!needsCedula.value || cedula.value.trim() !== '') &&
    paymentReady.value,
)

/** The datáfono answers: an approval prints an authorization code, a refusal asks for another card. */
function useDatafono(answer: 'aprobada' | 'rechazada') {
  datafono.value = answer
  authorization.value = answer === 'aprobada' ? authorizationCode() : ''
}

function resetRegisterPayment() {
  datafono.value = 'esperando'
  authorization.value = ''
  voucher.value = ''
  transferReceived.value = false
}

function add(product: Product) {
  const line = cart.value.find((l) => l.product.id === product.id)
  if (line) line.qty = Math.min(line.qty + 1, product.stock)
  else cart.value.push({ product, qty: 1 })
}

function remove(id: string) {
  cart.value = cart.value.filter((l) => l.product.id !== id)
}

/** Asks the AI for a draft and loads it into the sale: the cashier reviews it and charges as usual. */
async function interpret() {
  drafted.value = null
  confirmation.value = null
  await draft.run()
  const result = draft.data.value
  if (draft.error.value || !result) return
  cart.value = mergeDraftIntoCart(cart.value, result.draft, result.products)
  if (result.draft.metodo_pago === 'fiado') {
    method.value = 'fiado'
    if (result.draft.cliente?.activo) customerId.value = result.draft.cliente.id
  } else {
    method.value = 'efectivo'
  }
  drafted.value = result.draft
  dictation.value = ''
}

async function pay() {
  submitting.value = true
  submitError.value = null
  confirmation.value = null
  try {
    const sale = await api.createSale({
      lineas: cart.value.map((l) => ({ producto_id: l.product.id, cantidad: l.qty })),
      cedula_comprador: needsCedula.value ? cedula.value || null : null,
    })
    try {
      if (method.value === 'efectivo') {
        await api.chargeCash(sale.id)
        done(`Venta cobrada en efectivo: ${formatMoney(sale.total)}`)
      } else if (method.value === 'fiado') {
        const fiado = await api.chargeFiado(sale.id, customerId.value)
        done(`Venta a fiado de ${fiado.cliente.nombre}: ${formatMoney(fiado.total)}`)
      } else if (method.value === 'tarjeta') {
        await api.chargeAtRegister(sale.id, {
          metodo_pago: 'tarjeta',
          referencia: authorization.value,
        })
        done(`Venta cobrada con tarjeta (${authorization.value}): ${formatMoney(sale.total)}`)
      } else {
        const reference = voucher.value.trim()
        await api.chargeAtRegister(sale.id, { metodo_pago: 'sinpe_movil', referencia: reference })
        done(`Venta cobrada por SINPE Móvil (comprobante ${reference}): ${formatMoney(sale.total)}`)
      }
    } catch (e) {
      // The sale was opened but not collected: cancel it so no open sale keeps the units of the stock.
      await api.cancelSale(sale.id).catch(() => undefined)
      throw e
    }
  } catch (e) {
    submitError.value = e instanceof Error ? e.message : 'No se pudo registrar la venta'
  } finally {
    submitting.value = false
  }
}

function done(message: string) {
  confirmation.value = message
  drafted.value = null
  cart.value = []
  cedula.value = ''
  customerId.value = ''
  resetRegisterPayment()
  void products.run()
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(products.run, 300)
})
watch(method, (m) => {
  resetRegisterPayment()
  if (m === 'fiado' && !customers.data.value) void customers.run()
})
onMounted(products.run)
</script>

<template>
  <div class="layout">
    <section class="card">
      <h1>Productos</h1>
      <input v-model="search" type="search" placeholder="Buscar producto…" />
      <StateBox
        :loading="products.loading.value"
        :error="products.error.value"
        :empty="products.data.value?.length === 0"
        empty-text="No hay productos que coincidan."
        @retry="products.run"
      >
        <ul class="products">
          <li v-for="p in products.data.value" :key="p.id">
            <span
              >{{ p.nombre }} <small class="muted">{{ p.codigo }}</small></span
            >
            <span>{{ formatMoney(p.precio_venta) }}</span>
            <button type="button" :disabled="p.agotado" @click="add(p)">
              {{ p.agotado ? 'Agotado' : 'Agregar' }}
            </button>
          </li>
        </ul>
      </StateBox>
    </section>

    <div class="side">
      <section class="card">
        <h1>Dictar venta</h1>
        <label>
          Escriba la venta con sus palabras
          <textarea
            v-model="dictation"
            rows="3"
            :maxlength="MAX_TEXT"
            placeholder="Ej.: anote 2 leches y 1 pan a doña María en fiado"
          />
        </label>
        <p class="muted counter">{{ dictation.length }}/{{ MAX_TEXT }}</p>
        <button class="primary" type="button" :disabled="!canDictate" @click="interpret">
          {{ draft.loading.value ? 'Interpretando…' : 'Interpretar' }}
        </button>
        <StateBox :loading="draft.loading.value" :error="draft.error.value" @retry="interpret">
          <div v-if="drafted" class="draft" role="status">
            <p>
              Se cargó en la venta lo que se entendió. Revise y presione Cobrar: la venta no se
              registra sola.
            </p>
            <ul v-if="drafted.avisos.length" class="notices">
              <li v-for="aviso in drafted.avisos" :key="aviso">{{ aviso }}</li>
            </ul>
            <p v-if="notFound.length" class="notices">
              No se encontró en el catálogo:
              <span v-for="n in notFound" :key="n.mencionado">
                {{ n.cantidad }} × {{ n.mencionado }}.
              </span>
            </p>
          </div>
        </StateBox>
      </section>

      <section class="card">
        <h1>Venta actual</h1>
        <p v-if="confirmation" class="ok" role="status">{{ confirmation }}</p>
        <StateBox :empty="cart.length === 0" empty-text="Agregue productos para iniciar la venta.">
          <ul class="cart">
            <li v-for="l in cart" :key="l.product.id">
              <span>{{ l.product.nombre }}</span>
              <input
                v-model.number="l.qty"
                type="number"
                min="1"
                :max="l.product.stock"
                class="qty"
              />
              <span>{{ formatMoney(l.product.precio_venta * l.qty) }}</span>
              <button type="button" aria-label="Quitar" @click="remove(l.product.id)">✕</button>
            </li>
          </ul>
          <p class="total">Total: {{ formatMoney(total) }}</p>

          <label>
            Método de pago
            <select v-model="method">
              <option value="efectivo">Efectivo</option>
              <option value="fiado">Fiado</option>
              <option value="tarjeta">Tarjeta (datáfono)</option>
              <option value="sinpe_movil">SINPE Móvil (transferencia recibida)</option>
            </select>
          </label>

          <label v-if="needsCedula">
            Cédula del comprador (licor)
            <input v-model="cedula" required />
          </label>

          <div v-if="method === 'fiado'" class="fiado">
            <StateBox
              :loading="customers.loading.value"
              :error="customers.error.value"
              @retry="customers.run"
            >
              <select v-model="customerId">
                <option value="">Seleccione un cliente</option>
                <option
                  v-for="c in customers.data.value"
                  :key="c.id"
                  :value="c.id"
                  :disabled="!c.activo"
                >
                  {{ c.nombre }}
                </option>
              </select>
              <p v-if="selectedCustomer" class="muted">
                Debe {{ formatMoney(selectedCustomer.saldo_actual) }} · límite
                {{ formatMoney(selectedCustomer.limite_efectivo) }} · disponible
                {{ formatMoney(selectedCustomer.disponible) }}
              </p>
              <p v-if="exceedsLimit" class="error-text">El total supera el crédito disponible.</p>
            </StateBox>
          </div>

          <div v-if="method === 'tarjeta'" class="card register-payment" aria-label="Datáfono">
            <p>
              <strong>Datáfono (simulado)</strong>: cobre {{ formatMoney(total) }}. El cajero no
              digita datos de la tarjeta.
            </p>
            <p v-if="datafono === 'esperando'" class="muted">Esperando la tarjeta del cliente…</p>
            <p v-else-if="datafono === 'aprobada'" class="ok" role="status">
              Aprobada. Autorización: <strong>{{ authorization }}</strong>
            </p>
            <p v-else class="error-text" role="status">
              Tarjeta rechazada. Pruebe con otra o cambie el método de pago.
            </p>
            <div class="row">
              <button type="button" :disabled="submitting" @click="useDatafono('aprobada')">
                Aprobar en el datáfono
              </button>
              <button type="button" :disabled="submitting" @click="useDatafono('rechazada')">
                Rechazar en el datáfono
              </button>
            </div>
          </div>

          <div
            v-if="method === 'sinpe_movil'"
            class="card register-payment"
            aria-label="SINPE Móvil"
          >
            <p>
              Reciba {{ formatMoney(total) }} por SINPE Móvil y revise que la transferencia llegó a
              la cuenta del negocio.
            </p>
            <label>
              Número de comprobante
              <input v-model="voucher" :maxlength="MAX_REFERENCE" autocomplete="off" />
            </label>
            <label class="check">
              <input v-model="transferReceived" type="checkbox" />
              Confirmo que la transferencia ya se acreditó
            </label>
          </div>

          <p v-if="submitError" class="error-text" role="alert">{{ submitError }}</p>
          <button class="primary" type="button" :disabled="!canPay" @click="pay">
            {{ submitting ? 'Procesando…' : 'Cobrar' }}
          </button>
        </StateBox>
      </section>
    </div>
  </div>
</template>

<style scoped>
.side {
  display: grid;
  gap: 1rem;
}
.counter {
  margin: 0.2rem 0 0.5rem;
  font-size: 0.85rem;
  text-align: right;
}
textarea {
  width: 100%;
  resize: vertical;
}
.notices {
  background: #fef3c7;
  color: #92400e;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  margin: 0.5rem 0;
}
.layout {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 1rem;
  align-items: start;
}
@media (max-width: 800px) {
  .layout {
    grid-template-columns: 1fr;
  }
}
input[type='search'] {
  width: 100%;
}
ul {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0;
}
.products li,
.cart li {
  display: flex;
  gap: 0.6rem;
  align-items: center;
  justify-content: space-between;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--border);
}
.qty {
  width: 4.5rem;
}
.total {
  font-size: 1.2rem;
  font-weight: 700;
}
label {
  display: grid;
  gap: 0.25rem;
  margin: 0.6rem 0;
}
.ok {
  color: var(--brand-dark);
  font-weight: 600;
}
.register-payment {
  margin: 0.6rem 0;
}
.row {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>
