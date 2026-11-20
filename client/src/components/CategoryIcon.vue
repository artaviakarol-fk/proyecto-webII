<script setup lang="ts">
import { computed } from 'vue'

/** A small line icon for a category of the shop; categories it does not know get a tag. */
const props = defineProps<{ name: string | null }>()

const PATHS: Record<string, string[]> = {
  todo: ['M4 5h7v7H4z', 'M13 5h7v7h-7z', 'M4 14h7v5H4z', 'M13 14h7v5h-7z'],
  abarrotes: ['M6 8h12l-1 12H7z', 'M9 8V6a3 3 0 0 1 6 0v2'],
  lacteos: ['M8 3h8', 'M9 3v3l-2 3v12h10V9l-2-3V3', 'M7 13h10'],
  panaderia: ['M5 11a7 4 0 0 1 14 0v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z', 'M9 9v3', 'M12 8v4', 'M15 9v3'],
  bebidas: ['M10 3h4', 'M10 3v4l-2 3v11h8V10l-2-3V3', 'M8 14h8'],
  licores: ['M7 4h10l-1 7a4 4 0 0 1-8 0z', 'M12 15v5', 'M9 20h6'],
  limpieza: ['M9 9h6v12H9z', 'M10 9V6h4l3-2', 'M14 6h2'],
  snacks: ['M12 3a9 9 0 1 0 9 9 3 3 0 0 1-3-3 3 3 0 0 1-3-3 3 3 0 0 1-3-3', 'M9 13h.01', 'M14 16h.01', 'M9 17h.01'],
  frescos: ['M12 7c-4 0-6 3-6 7s3 7 6 7 6-3 6-7-2-7-6-7z', 'M12 7c0-2 1-4 3-4'],
  carnes: ['M15 4a5 5 0 0 1 5 5c0 5-6 8-10 8l-4 4-2-2 4-4c0-4 3-11 7-11z'],
  otro: ['M4 4h8l8 8-8 8-8-8z', 'M8 8h.01'],
}

const KEYWORDS: [string, RegExp][] = [
  ['abarrotes', /abarrot|granos|arroz/],
  ['lacteos', /lact|leche|queso/],
  ['panaderia', /pan|reposter/],
  ['licores', /licor|cerveza|vino/],
  ['bebidas', /bebida|refresco|jugo/],
  ['limpieza', /limpi|hogar|aseo/],
  ['snacks', /snack|galleta|confit|dulce/],
  ['frescos', /fresc|fruta|verdura/],
  ['carnes', /carne|pollo|embutid/],
]

const paths = computed(() => {
  if (props.name === null) return PATHS.todo!
  const name = props.name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '')
  const match = KEYWORDS.find(([, pattern]) => pattern.test(name))
  return PATHS[match?.[0] ?? 'otro']!
})
</script>

<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path v-for="d in paths" :key="d" :d="d" />
  </svg>
</template>
