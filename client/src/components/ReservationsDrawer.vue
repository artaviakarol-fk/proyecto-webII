<script setup lang="ts">
import { useRouter } from 'vue-router'

import StateBox from '@/components/StateBox.vue'
import { useNow } from '@/composables/useNow'
import { useReservationsStore } from '@/stores/reservations'
import { formatCountdown, secondsLeft } from '@/utils/countdown'
import { formatMoney } from '@/utils/money'

const reservations = useReservationsStore()
const router = useRouter()
const now = useNow()

async function goToPay() {
  reservations.drawerOpen = false
  await router.push({ name: 'pago' })
}
</script>

<template>
  <div v-if="reservations.drawerOpen" class="drawer-ov" @click.self="reservations.drawerOpen = false">
    <aside class="drawer" aria-label="Mi compra">
      <div class="dh">
        <h3>Mi compra</h3>
        <button class="hbtn" type="button" @click="reservations.drawerOpen = false">Cerrar ✕</button>
      </div>
      <div class="db">
        <StateBox
          :loading="reservations.loading && reservations.items.length === 0"
          :error="reservations.error"
          :empty="reservations.items.length === 0"
          empty-text="Aún no ha agregado nada."
          @retry="reservations.load"
        >
          <div v-for="item in reservations.items" :key="item.id" class="line">
            <div>
              <div class="nm">{{ item.nombre }} × {{ item.cantidad }}</div>
              <div class="un">{{ formatMoney(item.subtotal) }}</div>
              <div class="timer" :class="{ low: secondsLeft(item.expira_en, now) < 60 }">
                Vence en {{ formatCountdown(secondsLeft(item.expira_en, now)) }}
              </div>
            </div>
            <button class="sm" type="button" :aria-label="`Quitar ${item.nombre}`" @click="reservations.release(item.id)">
              Quitar
            </button>
          </div>
        </StateBox>
      </div>
      <div class="df">
        <div class="big"><span>Total</span><span>{{ formatMoney(reservations.total) }}</span></div>
        <p class="muted">Las unidades se guardan 15 minutos; después vuelven a la tienda.</p>
        <button class="primary full lg" type="button" :disabled="reservations.items.length === 0" @click="goToPay">
          Continuar al pago
        </button>
      </div>
    </aside>
  </div>
</template>
