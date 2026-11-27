import { computed, ref, watch } from 'vue'

/** The sizes a person can choose from in every list that shows a few rows at a time. */
export const PAGE_SIZES = [5, 10, 25, 50]

/** Cuts a long list in pages: the rows to show, the page and how many rows each page has. */
export function usePager<T>(items: () => T[], initialSize = 10) {
  const pageSize = ref(initialSize)
  const page = ref(1)
  const total = computed(() => items().length)
  const pageCount = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)))
  const visible = computed(() =>
    items().slice((page.value - 1) * pageSize.value, page.value * pageSize.value),
  )
  const first = computed(() => (total.value === 0 ? 0 : (page.value - 1) * pageSize.value + 1))
  const last = computed(() => Math.min(page.value * pageSize.value, total.value))

  watch(pageSize, () => (page.value = 1))
  // A search or a reload can leave fewer pages than the one being shown.
  watch(pageCount, (count) => {
    if (page.value > count) page.value = count
  })

  const setPage = (value: number) => (page.value = Math.min(Math.max(1, value), pageCount.value))
  const setPageSize = (value: number) => (pageSize.value = value)

  return { pageSize, page, pageCount, total, visible, first, last, setPage, setPageSize }
}

export type Pager = ReturnType<typeof usePager>
