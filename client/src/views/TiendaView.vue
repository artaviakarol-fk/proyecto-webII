<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import AuthImage from '@/components/AuthImage.vue'
import CategoryIcon from '@/components/CategoryIcon.vue'
import StateBox from '@/components/StateBox.vue'
import { useAsync } from '@/composables/useAsync'
import { useNow } from '@/composables/useNow'
import { listCatalog, listCatalogCategories } from '@/services/api'
import { useReservationsStore } from '@/stores/reservations'
import { secondsLeft } from '@/utils/countdown'
import { formatMoney } from '@/utils/money'

const reservations = useReservationsStore()
const now = useNow()

const search = ref('')
const category = ref<string | null>(null)
const page = ref(1)
const catalog = useAsync(() => listCatalog(search.value, page.value, category.value))
const categories = useAsync(listCatalogCategories)

// The message a card shows when adding or removing failed, and the card that is waiting for the API.
const messages = ref<Record<string, string>>({})
const busy = ref<string | null>(null)

/** Units of each product the cliente already holds. */
const inCart = computed(() => {
  const units: Record<string, number> = {}
  for (const item of reservations.items) units[item.producto_id] = (units[item.producto_id] ?? 0) + item.cantidad
  return units
})

function searchNow() {
  page.value = 1
  void catalog.run()
}

function chooseCategory(name: string | null) {
  category.value = name
  page.value = 1
  void catalog.run()
}

function goToPage(next: number) {
  page.value = next
  void catalog.run()
}

/** Runs one change of the cart for a product, shows its error on the card and refreshes the units. */
async function changeCart(productId: string, work: () => Promise<unknown>) {
  busy.value = productId
  messages.value[productId] = ''
  try {
    await work()
    await catalog.run()
  } catch (e) {
    // A 409 means someone else was faster; the API says how many units are left.
    messages.value[productId] = e instanceof Error ? e.message : 'No se pudo cambiar su compra'
  } finally {
    busy.value = null
  }
}

// The API keeps one reserva per product: reserving it again replaces it with the new quantity and a fresh 15 minutes.
const reservaOf = (productId: string) => reservations.items.find((item) => item.producto_id === productId)

/** "+" holds one more unit. */
function addOne(productId: string) {
  const units = (reservaOf(productId)?.cantidad ?? 0) + 1
  void changeCart(productId, () => reservations.reserve(productId, units))
}

/** "−" holds one unit less; at zero the product leaves the cart. */
function removeOne(productId: string) {
  const reserva = reservaOf(productId)
  if (!reserva) return
  void changeCart(productId, () =>
    reserva.cantidad > 1 ? reservations.reserve(productId, reserva.cantidad - 1) : reservations.release(reserva.id),
  )
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
  void categories.run()
  void reservations.load()
})
</script>

<template>
  <div>
    <div class="shop-head">
      <div>
        <h1>Minisúper Teresita</h1>
        <p>Agregue sus productos y pague en línea. Se los guardamos 15 minutos.</p>
      </div>
      <form class="shop-search" role="search" @submit.prevent="searchNow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7" />
          <path d="M20 20l-3.5-3.5" />
        </svg>
        <input v-model="search" type="search" placeholder="Buscar arroz, leche, pan…" aria-label="Buscar producto" />
        <button class="sm" type="submit">Buscar</button>
      </form>
    </div>

    <nav v-if="(categories.data.value?.length ?? 0) > 0" class="cats" aria-label="Categorías">
      <button type="button" class="cat" :class="{ on: category === null }" :aria-pressed="category === null" @click="chooseCategory(null)">
        <CategoryIcon :name="null" />Todo
      </button>
      <button
        v-for="name in categories.data.value"
        :key="name"
        type="button"
        class="cat"
        :class="{ on: category === name }"
        :aria-pressed="category === name"
        @click="chooseCategory(name)"
      >
        <CategoryIcon :name="name" />{{ name }}
      </button>
    </nav>

    <StateBox
      :loading="catalog.loading.value && !catalog.data.value"
      :error="catalog.error.value"
      :empty="catalog.data.value?.data.length === 0"
      empty-text="No hay productos que coincidan."
      @retry="catalog.run"
    >
      <ul class="pgrid">
        <li v-for="product in catalog.data.value?.data ?? []" :key="product.id" class="pcard">
          <div class="ptile">
            <AuthImage :product-id="product.id" :alt="product.nombre" />
            <span v-if="product.agotado" class="flag out">Agotado</span>
            <span v-else-if="product.disponible <= 5" class="flag low">Quedan {{ product.disponible }}</span>
            <button
              v-if="!product.agotado && !inCart[product.id]"
              class="addbtn"
              type="button"
              :aria-label="`Agregar ${product.nombre}`"
              :disabled="busy === product.id"
              @click="addOne(product.id)"
            >
              +
            </button>
          </div>
          <span class="pprice">{{ formatMoney(product.precio_venta) }}</span>
          <h3 class="pname">{{ product.nombre }}</h3>
          <p class="pmeta">{{ product.categoria ?? 'Producto' }}<template v-if="product.es_licor"> · se pide cédula al pagar</template></p>
          <div v-if="inCart[product.id]" class="stepper" role="group" :aria-label="`Cantidad de ${product.nombre} en su compra`">
            <button type="button" :aria-label="`Quitar una unidad de ${product.nombre}`" :disabled="busy === product.id" @click="removeOne(product.id)">−</button>
            <span aria-live="polite">{{ inCart[product.id] }}</span>
            <button
              type="button"
              :aria-label="`Agregar una unidad de ${product.nombre}`"
              :disabled="busy === product.id || product.disponible === 0"
              @click="addOne(product.id)"
            >
              +
            </button>
          </div>
          <p v-if="messages[product.id]" class="pmsg" role="alert">{{ messages[product.id] }}</p>
        </li>
      </ul>
      <nav v-if="(catalog.data.value?.meta.ultima_pagina ?? 1) > 1" class="pager" aria-label="Páginas">
        <button type="button" :disabled="page <= 1" @click="goToPage(page - 1)">Anterior</button>
        <span class="muted">Página {{ page }} de {{ catalog.data.value?.meta.ultima_pagina }}</span>
        <button type="button" :disabled="page >= (catalog.data.value?.meta.ultima_pagina ?? 1)" @click="goToPage(page + 1)">
          Siguiente
        </button>
      </nav>
    </StateBox>
  </div>
</template>
