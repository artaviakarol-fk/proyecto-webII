<script setup lang="ts">
defineProps<{
  loading?: boolean
  error?: string | null
  empty?: boolean
  emptyText?: string
}>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <p v-if="loading" class="state muted" role="status">Cargando…</p>
  <div v-else-if="error" class="state" role="alert">
    <p class="error-text">{{ error }}</p>
    <button type="button" @click="$emit('retry')">Reintentar</button>
  </div>
  <p v-else-if="empty" class="state muted">{{ emptyText ?? 'No hay datos para mostrar.' }}</p>
  <slot v-else />
</template>

<style scoped>
.state { padding: 1rem; text-align: center; }
</style>
