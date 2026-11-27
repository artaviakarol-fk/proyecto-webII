<script setup lang="ts">
import { PAGE_SIZES, type Pager } from '@/composables/usePager'

// The pager is a bag of refs made by usePager; the template unwraps them through this prop.
defineProps<{ pager: Pager; noun: string }>()
</script>

<template>
  <div v-if="pager.total.value > PAGE_SIZES[0]!" class="pager">
    <label>
      Mostrar
      <select
        :value="pager.pageSize.value"
        :aria-label="`${noun} por página`"
        @change="pager.setPageSize(Number(($event.target as HTMLSelectElement).value))"
      >
        <option v-for="size in PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
      </select>
      por página
    </label>
    <span class="muted"
      >{{ pager.first.value }}–{{ pager.last.value }} de {{ pager.total.value }}</span
    >
    <button
      type="button"
      class="sm"
      :disabled="pager.page.value <= 1"
      @click="pager.setPage(pager.page.value - 1)"
    >
      Anterior
    </button>
    <button
      type="button"
      class="sm"
      :disabled="pager.page.value >= pager.pageCount.value"
      @click="pager.setPage(pager.page.value + 1)"
    >
      Siguiente
    </button>
  </div>
</template>

<style scoped>
.pager {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.75rem;
}
.pager label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
</style>
