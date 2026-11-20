<script setup lang="ts">
import { ref } from 'vue'

import BrandLogo from '@/components/BrandLogo.vue'
import CartAnimation from '@/components/CartAnimation.vue'
import TypedWord from '@/components/TypedWord.vue'

const heroImage = '/img/carrito.webp'

// Each word with the ending that agrees with it: "su arroz, listo"; "su leche, lista".
const WORDS = [
  { word: 'arroz', ready: 'listo' },
  { word: 'café', ready: 'listo' },
  { word: 'leche', ready: 'lista' },
  { word: 'pan', ready: 'listo' },
  { word: 'compra', ready: 'lista' },
]
const ready = ref('listo')
</script>

<template>
  <div class="login">
    <div class="lhero">
      <img :src="heroImage" alt="" />
      <div class="lhero-text">
        <CartAnimation />
        <h1>
          <span class="sr-only">Su compra, lista para recoger.</span>
          <span aria-hidden="true" class="lhero-line">
            Su <TypedWord :words="WORDS.map((w) => w.word)" still="compra" @word="(i) => (ready = WORDS[i]?.ready ?? 'lista')" />,<br />{{ ready }}
            para recoger.
          </span>
        </h1>
        <p class="lhero-sub">Pida en línea y páselo a traer sin hacer fila.</p>
      </div>
    </div>
    <div class="lside">
      <div class="lcard">
        <BrandLogo class="lbrand" :size="40" tagline="Caja, inventario y pedidos" />
        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped>
.lbrand { margin-bottom: 0.6rem; }
</style>
