<script setup lang="ts">
import type { AnimationItem } from 'lottie-web'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { aids } from '@/composables/useAppearance'

/**
 * A shopping cart (animation "cart" of LottieFiles, free license, drawn here in cream so it shows on the photo) that
 * rolls once across the panel, from one edge to the other, filling up on the way, and is gone. It is decoration: hidden from
 * screen readers, and with "Pausar animaciones" or the system's reduced motion it stands still and full.
 * The player is loaded only here, so the rest of the app does not carry it.
 */
const box = ref<HTMLDivElement | null>(null)
const still = ref(false)
let animation: AnimationItem | null = null

// The trip happens once, when the login opens: the cart enters empty, the products fall in on the way and it leaves full.
// Frame 20 is the empty cart already drawn, 20→52 the products falling in, 52 the full cart.
const EMPTY = 20
const FULL = 52
const FILL_AFTER_MS = 600
let fillTimer: ReturnType<typeof setTimeout> | undefined

function startTrip() {
  clearTimeout(fillTimer)
  if (!animation || still.value) return
  animation.goToAndStop(EMPTY, true)
  fillTimer = setTimeout(() => animation?.playSegments([EMPTY, FULL], true), FILL_AFTER_MS)
}

const reduced = () =>
  aids.still || (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches)

function applyMotion() {
  still.value = reduced()
  if (!animation) return
  if (still.value) {
    clearTimeout(fillTimer)
    animation.goToAndStop(FULL, true)
  } else startTrip()
}

onMounted(async () => {
  still.value = reduced()
  if (!box.value) return
  try {
    const { default: lottie } = await import('lottie-web/build/player/lottie_light')
    if (!box.value) return
    animation = lottie.loadAnimation({ container: box.value, renderer: 'svg', loop: false, autoplay: false, path: '/img/carrito.json' })
    animation.setSpeed(1)
    animation.addEventListener('DOMLoaded', applyMotion)
  } catch {
    /* without the animation the track stays empty; nothing else depends on it */
  }
})

watch(() => aids.still, applyMotion)
onBeforeUnmount(() => {
  clearTimeout(fillTimer)
  animation?.destroy()
})
</script>

<template>
  <div class="cart-track" :class="{ still }" aria-hidden="true">
    <div ref="box" class="cart-rider" />
  </div>
</template>

<style scoped>
.cart-track { position: relative; height: clamp(9rem, 17vw, 14rem); overflow: hidden; margin: 0 calc(-1 * var(--hero-pad)) 0.6rem; }
.cart-rider { position: absolute; bottom: 0; left: 0; height: 100%; aspect-ratio: 1; animation: cart-ride 3s linear 1 both; }
@keyframes cart-ride { from { left: 0; transform: translateX(-100%); } to { left: 100%; transform: translateX(0); } }
.still .cart-rider { animation: none; left: var(--hero-pad); }
@media (prefers-reduced-motion: reduce) { .cart-rider { animation: none; left: var(--hero-pad); } }
</style>
