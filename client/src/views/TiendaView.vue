<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import AuthImage from '@/components/AuthImage.vue'
import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { useNow } from '@/composables/useNow'
import { listCatalog } from '@/services/api'
import { useReservationsStore } from '@/stores/reservations'
import { formatCountdown, secondsLeft } from '@/utils/countdown'

const router = useRouter()
const reservations = useReservationsStore()
const now = useNow()

const search = ref('')
const page = ref(1)
const catalog = useAsync(() => listCatalog(search.value, page.value))

// Quantity typed on each product card, and the message each card shows after trying to reserve.
const quantities = ref<Record<string, number>>({})
const messages = ref<Record<string, string>>({})
const busy = ref<string | null>(null)

const money = (value: number) => `₡${value.toLocaleString('es-CR')}`
const quantityOf = (id: string) => quantities.value[id] ?? 1

function searchNow() {
  page.value = 1
  void catalog.run()
}

function goToPage(next: number) {
  page.value = next
  void catalog.run()
}

async function reserveProduct(productId: string) {
  busy.value = productId
  messages.value[productId] = ''
  try {
    await reservations.reserve(productId, quantityOf(productId))
    messages.value[productId] = 'Reservado. Tiene 15 minutos para pagar.'
    await catalog.run()
  } catch (e) {
    // A 409 means someone else was faster; the API says how many units are left.
    messages.value[productId] = e instanceof Error ? e.message : 'No se pudo reservar'
  } finally {
    busy.value = null
  }
}

async function release(reservationId: string) {
  await reservations.release(reservationId)
  await catalog.run()
}

// When a reserva runs out of time the units go back to the shelf: reload both lists once.
const expired = computed(
  () => reservations.items.filter((item) => secondsLeft(item.expira_en, now.value) === 0).length,
)
watch(expired, (count) => {
  if (count > 0) {
    void reservations.load()
    void catalog.run()
  }
})

onMounted(() => {
  void catalog.run()
  void reservations.load()
})
</script>

<template>
  <div class="shop">
    <section>
      <h1>Productos</h1>
      <form class="search" role="search" @submit.prevent="searchNow">
        <input v-model="search" type="search" placeholder="Buscar producto" aria-label="Buscar producto" />
        <button type="submit">Buscar</button>
      </form>

      <StateBox
        :loading="catalog.loading.value"
        :error="catalog.error.value"
        :empty="catalog.data.value?.data.length === 0"
        empty-text="No hay productos que coincidan."
        @retry="catalog.run"
      >
        <ul class="grid">
          <li v-for="product in catalog.data.value?.data ?? []" :key="product.id" class="card product">
            <AuthImage :product-id="product.id" :alt="product.nombre" />
            <h3>{{ product.nombre }}</h3>
            <span v-if="product.categoria" class="chip">{{ product.categoria }}</span>
            <p class="price">{{ money(product.precio_venta) }}</p>
            <p v-if="product.agotado" class="error-text">Agotado</p>
            <p v-else class="muted">Disponibles: {{ product.disponible }}</p>
            <p v-if="product.es_licor" class="muted">Producto con licor: se pide su cédula al pagar.</p>
            <form v-if="!product.agotado" class="reserve" @submit.prevent="reserveProduct(product.id)">
              <input
                v-model.number="quantities[product.id]"
                type="number"
                min="1"
                :max="product.disponible"
                :aria-label="`Cantidad de ${product.nombre}`"
                :placeholder="'1'"
              />
              <button class="primary" type="submit" :disabled="busy === product.id">
                {{ busy === product.id ? 'Reservando…' : 'Reservar' }}
              </button>
            </form>
            <p v-if="messages[product.id]" class="msg" role="status">{{ messages[product.id] }}</p>
          </li>
        </ul>
        <nav v-if="(catalog.data.value?.meta.ultima_pagina ?? 1) > 1" class="pager" aria-label="Páginas">
          <button type="button" :disabled="page <= 1" @click="goToPage(page - 1)">Anterior</button>
          <span class="muted">Página {{ page }} de {{ catalog.data.value?.meta.ultima_pagina }}</span>
          <button
            type="button"
            :disabled="page >= (catalog.data.value?.meta.ultima_pagina ?? 1)"
            @click="goToPage(page + 1)"
          >
            Siguiente
          </button>
        </nav>
      </StateBox>
    </section>

    <aside class="card cart" aria-label="Mis reservas">
      <h2>Mis reservas</h2>
      <StateBox
        :loading="reservations.loading && reservations.items.length === 0"
        :error="reservations.error"
        :empty="reservations.items.length === 0"
        empty-text="Aún no reserva nada."
        @retry="reservations.load"
      >
        <ul class="lines">
          <li v-for="item in reservations.items" :key="item.id">
            <div>
              <strong>{{ item.nombre }}</strong> × {{ item.cantidad }}
              <div class="muted">{{ money(item.subtotal) }}</div>
              <div class="timer" :class="{ low: secondsLeft(item.expira_en, now) < 60 }">
                Vence en {{ formatCountdown(secondsLeft(item.expira_en, now)) }}
              </div>
            </div>
            <button type="button" :aria-label="`Liberar ${item.nombre}`" @click="release(item.id)">
              Liberar
            </button>
          </li>
        </ul>
        <p class="total">Total: {{ money(reservations.total) }}</p>
        <button class="primary" type="button" @click="router.push({ name: 'pago' })">Pagar</button>
      </StateBox>
    </aside>
  </div>
</template>

<style scoped>
.shop { display: grid; grid-template-columns: 1fr 320px; gap: 1rem; align-items: start; }
.search { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.search input { flex: 1; }
.grid { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 1rem; }
.product { display: grid; gap: 0.35rem; align-content: start; }
.product h3 { font-size: 1.05rem; margin: 0.25rem 0 0; }
.price { font-weight: 600; font-size: 1.1rem; margin: 0; color: var(--verde-oscuro); }
.reserve { display: flex; gap: 0.4rem; }
.reserve input { width: 4.5rem; }
.msg { margin: 0; font-size: 0.9rem; color: var(--verde-medio); }
.pager { display: flex; gap: 1rem; align-items: center; justify-content: center; margin-top: 1rem; }
.cart { position: sticky; top: 4.5rem; }
.lines { list-style: none; margin: 0 0 0.75rem; padding: 0; display: grid; gap: 0.75rem; }
.lines li { display: flex; justify-content: space-between; gap: 0.5rem; align-items: start; }
.timer { color: var(--naranja-aa); font-variant-numeric: tabular-nums; }
.timer.low { color: var(--danger); font-weight: 600; }
.total { font-weight: 600; }
@media (max-width: 800px) { .shop { grid-template-columns: 1fr; } .cart { position: static; } }
</style>
