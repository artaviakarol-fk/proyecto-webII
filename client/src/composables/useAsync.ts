import { ref, shallowRef } from 'vue'

export function useAsync<T>(fn: () => Promise<T>) {
  const data = shallowRef<T | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function run(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      data.value = await fn()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ocurrió un error inesperado'
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, run }
}
