import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CatalogSearch from '../../../../src/components/catalog/CatalogSearch.vue'

describe('CatalogSearch', () => {
	it('emits update:modelValue while typing', async () => {
		const wrapper = mount(CatalogSearch, { props: { modelValue: '' } })
		await wrapper.find('input').setValue('milk')
		expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['milk'])
	})

	it('clears the value through the trailing button', async () => {
		const wrapper = mount(CatalogSearch, { props: { modelValue: 'milk' } })
		const clear = wrapper.find('.input-field__trailing-button')
		expect(clear.exists()).toBe(true)
		await clear.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
	})

	it('does not render the clear button when empty', () => {
		const wrapper = mount(CatalogSearch, { props: { modelValue: '' } })
		expect(wrapper.find('.input-field__trailing-button').exists()).toBe(false)
	})

	it('sets a search-specific placeholder', () => {
		const wrapper = mount(CatalogSearch, {
			props: { modelValue: '', placeholder: 'Search products…' },
		})
		expect(wrapper.find('input').attributes('placeholder')).toBe('Search products…')
	})
})
