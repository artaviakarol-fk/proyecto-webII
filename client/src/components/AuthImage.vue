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
  <img v-if="src" :src="src" :alt="alt" class="photo" />
  <div v-else class="photo empty" aria-hidden="true">🛒</div>
</template>

<style scoped>
.photo { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; background: var(--verde-suave); }
.empty { display: grid; place-items: center; font-size: 2rem; }
</style>
