import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'

import PagerBar from '@/components/PagerBar.vue'
import { usePager } from '@/composables/usePager'

const numbers = (count: number) => Array.from({ length: count }, (_, n) => n + 1)

describe('usePager', () => {
  it('cuts the list in pages and tells which rows are shown', () => {
    const items = ref(numbers(23))
    const pager = usePager(() => items.value, 10)
    expect(pager.visible.value).toHaveLength(10)
    expect(pager.pageCount.value).toBe(3)
    pager.setPage(3)
    expect(pager.visible.value).toEqual([21, 22, 23])
    expect([pager.first.value, pager.last.value]).toEqual([21, 23])
  })

  it('goes back to the first page when the size changes', async () => {
    const pager = usePager(() => numbers(23), 5)
    pager.setPage(4)
    pager.setPageSize(25)
    await Promise.resolve()
    expect(pager.page.value).toBe(1)
    expect(pager.visible.value).toHaveLength(23)
  })

  it('never stays on a page that no longer exists', async () => {
    const items = ref(numbers(23))
    const pager = usePager(() => items.value, 10)
    pager.setPage(3)
    items.value = numbers(4)
    await Promise.resolve()
    expect(pager.page.value).toBe(1)
  })

  it('keeps the page inside its limits', () => {
    const pager = usePager(() => numbers(12), 10)
    pager.setPage(9)
    expect(pager.page.value).toBe(2)
    pager.setPage(-4)
    expect(pager.page.value).toBe(1)
  })
})

describe('PagerBar', () => {
  it('is hidden when the list is short', () => {
    const w = mount(PagerBar, { props: { pager: usePager(() => numbers(5)), noun: 'Filas' } })
    expect(w.find('.pager').exists()).toBe(false)
  })

  it('moves between pages and changes the size', async () => {
    const pager = usePager(() => numbers(30), 10)
    const w = mount(PagerBar, { props: { pager, noun: 'Filas' } })
    expect(w.text()).toContain('1–10 de 30')
    expect(w.findAll('button')[0]!.attributes('disabled')).toBeDefined()

    await w.findAll('button')[1]!.trigger('click')
    expect(w.text()).toContain('11–20 de 30')

    await w.find('select[aria-label="Filas por página"]').setValue('25')
    expect(w.text()).toContain('1–25 de 30')
  })
})
