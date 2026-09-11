import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CategoryBubble from '../../../../src/components/catalog/CategoryBubble.vue'

describe('CategoryBubble', () => {
	it('renders the emoji when provided', () => {
		const wrapper = mount(CategoryBubble, { props: { emoji: '🥛' } })
		expect(wrapper.text()).toContain('🥛')
		expect(wrapper.find('svg').exists()).toBe(false)
	})

	it('falls back to the tag icon without an emoji', () => {
		const wrapper = mount(CategoryBubble, { props: { emoji: null } })
		expect(wrapper.find('svg').exists()).toBe(true)
	})

	it('applies the category color as the background', () => {
		const wrapper = mount(CategoryBubble, { props: { color: '#ff0000' } })
		expect(wrapper.attributes('style')).toContain('background-color: rgb(255, 0, 0)')
	})
})
