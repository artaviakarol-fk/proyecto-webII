<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

import { catalogPhoto } from '@/services/api'

const props = defineProps<{ productId: string; alt: string }>()

const src = ref<string | null>(null)

// An <img> cannot send the token, so the photo is downloaded with it and shown from a temporary local URL.
onMounted(async () => {
  try {
    src.value = URL.createObjectURL(await catalogPhoto(props.productId))
  } catch {
    src.value = null
  }
})
onBeforeUnmount(() => {
  if (src.value) URL.revokeObjectURL(src.value)
})
</script>

<template>
  <img v-if="src" :src="src" :alt="alt" />
  <span v-else class="placeholder" aria-hidden="true">🛒</span>
</template>

<style scoped>
.placeholder { font-size: 2.2rem; }
</style>
