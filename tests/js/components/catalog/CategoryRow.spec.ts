import type { Category } from '../../../../src/types.ts'

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import CategoryRow from '../../../../src/components/catalog/CategoryRow.vue'

function category(overrides: Partial<Category> = {}): Category {
	return {
		id: 'c1',
		name: 'Dairy',
		color: '#ff0000',
		emoji: '🥛',
		parentId: null,
		income: false,
		status: 'confirmed',
		...overrides,
	}
}

async function render(props: { category: Category, parentName?: string, depth?: number, search?: string }) {
	const wrapper = mount(CategoryRow, { props })
	await nextTick()
	return wrapper
}

describe('CategoryRow', () => {
	it('renders the name and the parent name', async () => {
		const wrapper = await render({ category: category(), parentName: 'Food' })
		expect(wrapper.text()).toContain('Dairy')
		expect(wrapper.text()).toContain('Food')
	})

	it('shows the pending review chip and approve button', async () => {
		const wrapper = await render({ category: category({ status: 'pending_review' }) })
		expect(wrapper.text()).toContain('Pending Review')
		expect(wrapper.find('button[aria-label="Approve Dairy"]').exists()).toBe(true)
	})

	it('emits edit', async () => {
		const value = category()
		const wrapper = await render({ category: value })
		await wrapper.find('button[aria-label="Edit Dairy"]').trigger('click')
		expect(wrapper.emitted('edit')?.[0]).toEqual([value])
	})

	it('emits delete', async () => {
		const value = category()
		const wrapper = await render({ category: value })
		await wrapper.find('button[aria-label="Delete Dairy"]').trigger('click')
		expect(wrapper.emitted('delete')?.[0]).toEqual([value])
	})

	it('emits confirm for pending categories', async () => {
		const value = category({ status: 'pending_review' })
		const wrapper = await render({ category: value })
		await wrapper.find('button[aria-label="Approve Dairy"]').trigger('click')
		expect(wrapper.emitted('confirm')?.[0]).toEqual([value])
	})

	it('indents by depth', async () => {
		const wrapper = await render({ category: category(), depth: 2 })
		expect(wrapper.attributes('style')).toContain('padding-left: 48px')
	})
})
