<script setup lang="ts">
const heroImage = '/img/portada.webp'
import { computed, onMounted, ref, watch } from 'vue'

import AuthImage from '@/components/AuthImage.vue'
import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { useNow } from '@/composables/useNow'
import { listCatalog } from '@/services/api'
import { useReservationsStore } from '@/stores/reservations'
import { secondsLeft } from '@/utils/countdown'
import { formatMoney } from '@/utils/money'

const reservations = useReservationsStore()
const now = useNow()

const search = ref('')
const page = ref(1)
const catalog = useAsync(() => listCatalog(search.value, page.value))

// Quantity chosen on each product card, and the message each card shows after trying to reserve.
const quantities = ref<Record<string, number>>({})
const messages = ref<Record<string, string>>({})
const busy = ref<string | null>(null)

const quantityOf = (id: string) => quantities.value[id] ?? 1

function change(id: string, delta: number, max: number) {
  quantities.value[id] = Math.min(max, Math.max(1, quantityOf(id) + delta))
}

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
    messages.value[productId] = 'Agregado a su compra. Tiene 15 minutos para pagar.'
    await catalog.run()
  } catch (e) {
    // A 409 means someone else was faster; the API says how many units are left.
    messages.value[productId] = e instanceof Error ? e.message : 'No se pudo agregar a su compra'
  } finally {
    busy.value = null
  }
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
  <div>
    <div class="shero">
      <img :src="heroImage" alt="" />
      <div>
        <h1>Minisúper Teresita</h1>
        <p>Compre sus productos y pague en línea. Las unidades se guardan 15 minutos.</p>
      </div>
    </div>

    <form class="search" role="search" @submit.prevent="searchNow">
      <span aria-hidden="true">🔎</span>
      <input v-model="search" type="search" placeholder="Buscar producto… ej: arroz, jabón, leche" aria-label="Buscar producto" />
      <button type="submit">Buscar</button>
    </form>

    <StateBox
      :loading="catalog.loading.value"
      :error="catalog.error.value"
      :empty="catalog.data.value?.data.length === 0"
      empty-text="No hay productos que coincidan."
      @retry="catalog.run"
    >
      <ul class="pgrid">
        <li v-for="product in catalog.data.value?.data ?? []" :key="product.id" class="producto-card">
          <div class="card-img-wrapper"><AuthImage :product-id="product.id" :alt="product.nombre" /></div>
          <div class="card-body">
            <span class="card-subcat">{{ product.categoria ?? 'Producto' }}</span>
            <h3 class="card-title">{{ product.nombre }}</h3>
            <div class="card-price">
              <span class="price-amount">{{ formatMoney(product.precio_venta) }}</span>
              <span v-if="product.agotado" class="stk out">Agotado</span>
              <span v-else class="stk" :class="{ low: product.disponible <= 5 }">Disponibles: {{ product.disponible }}</span>
            </div>
            <p v-if="product.es_licor" class="stk">Producto con licor: se pide su cédula al pagar.</p>
            <form v-if="!product.agotado" class="card-price" @submit.prevent="reserveProduct(product.id)">
              <span class="qty">
                <button type="button" aria-label="Menos" @click="change(product.id, -1, product.disponible)">−</button>
                <input
                  v-model.number="quantities[product.id]"
                  type="number"
                  min="1"
                  :max="product.disponible"
                  :aria-label="`Cantidad de ${product.nombre}`"
                  placeholder="1"
                />
                <button type="button" aria-label="Más" @click="change(product.id, 1, product.disponible)">+</button>
              </span>
              <button class="primary sm" type="submit" :disabled="busy === product.id">
                {{ busy === product.id ? 'Agregando…' : 'Comprar' }}
              </button>
            </form>
            <p v-if="messages[product.id]" class="msg" role="status">{{ messages[product.id] }}</p>
          </div>
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
  </div>
</template>
