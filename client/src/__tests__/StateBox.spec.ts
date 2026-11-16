import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import StateBox from '@/components/StateBox.vue'

describe('StateBox', () => {
  it('shows loading', () => {
    const w = mount(StateBox, { props: { loading: true } })
    expect(w.text()).toContain('Cargando')
  })

  it('shows the error and emits retry', async () => {
    const w = mount(StateBox, { props: { error: 'Falló' } })
    expect(w.text()).toContain('Falló')
    await w.find('button').trigger('click')
    expect(w.emitted('retry')).toHaveLength(1)
  })

  it('shows the empty text', () => {
    const w = mount(StateBox, { props: { empty: true, emptyText: 'Sin datos' } })
    expect(w.text()).toContain('Sin datos')
  })

  it('renders the slot when everything is fine', () => {
    const w = mount(StateBox, { slots: { default: '<p>contenido</p>' } })
    expect(w.text()).toContain('contenido')
  })
})
