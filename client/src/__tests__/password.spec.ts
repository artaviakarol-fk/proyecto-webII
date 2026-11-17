import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PasswordInput from '@/components/PasswordInput.vue'

describe('PasswordInput', () => {
  it('oculta la contraseña y permite mostrarla', async () => {
    const w = mount(PasswordInput, { props: { modelValue: 'secreta123' } })
    expect(w.get('input').attributes('type')).toBe('password')
    await w.get('button').trigger('click')
    expect(w.get('input').attributes('type')).toBe('text')
    expect(w.get('button').attributes('aria-label')).toBe('Ocultar contraseña')
    await w.get('button').trigger('click')
    expect(w.get('input').attributes('type')).toBe('password')
  })
})
