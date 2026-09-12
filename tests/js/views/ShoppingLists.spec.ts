import type { Category, Product, ShoppingList, Store } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ConfirmDialog from '../../../src/components/ConfirmDialog.vue'
import ShoppingLists from '../../../src/views/ShoppingLists.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchLists: vi.fn(),
	fetchStores: vi.fn(),
	fetchCategories: vi.fn(),
	fetchProducts: vi.fn(),
	fetchListItems: vi.fn(),
	deleteListItem: vi.fn(),
	deleteList: vi.fn(),
}))

function list(overrides: Partial<ShoppingList> = {}): ShoppingList {
	return {
		id: 'l1',
		name: 'Weekly groceries',
		storeId: null,
		categoryId: null,
		status: 'new',
		finalTotal: null,
		totalPrice: null,
		createdAt: new Date().toISOString(),
		...overrides,
	}
}

async function render(lists: ShoppingList[] = [list()]) {
	vi.mocked(api.fetchLists).mockResolvedValue(lists)
	vi.mocked(api.fetchStores).mockResolvedValue([] as Store[])
	vi.mocked(api.fetchCategories).mockResolvedValue([] as Category[])
	vi.mocked(api.fetchProducts).mockResolvedValue([] as Product[])
	vi.mocked(api.fetchListItems).mockResolvedValue([])
	const wrapper = mount(ShoppingLists, { attachTo: document.body })
	await flushPromises()
	await nextTick()
	return wrapper
}

describe('ShoppingLists', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('renders a delete button for each list', async () => {
		const wrapper = await render([list(), list({ id: 'l2', name: 'Weekend' })])
		expect(wrapper.find('button[aria-label="Delete Weekly groceries"]').exists()).toBe(true)
		expect(wrapper.find('button[aria-label="Delete Weekend"]').exists()).toBe(true)
	})

	it('asks for confirmation before deleting', async () => {
		const wrapper = await render()
		await wrapper.find('button[aria-label="Delete Weekly groceries"]').trigger('click')
		await nextTick()

		const dialog = wrapper.findComponent(ConfirmDialog)
		expect(dialog.props('open')).toBe(true)
		expect(dialog.props('title')).toBe('Delete list')
		expect(dialog.props('message')).toContain('Weekly groceries')
		expect(api.deleteList).not.toHaveBeenCalled()
	})

	it('offers a labeled delete action in the expanded list', async () => {
		const wrapper = await render()
		await wrapper.findComponent(NcListItem).find('.list-item__anchor').trigger('click')
		await flushPromises()

		const button = wrapper.findAll('button').find((candidate) => candidate.text() === 'Delete list')
		expect(button).toBeDefined()
		await button!.trigger('click')
		await nextTick()

		expect(wrapper.findComponent(ConfirmDialog).props('open')).toBe(true)
	})

	it('deletes the list after confirmation', async () => {
		vi.mocked(api.deleteList).mockResolvedValue()
		const wrapper = await render()

		await wrapper.find('button[aria-label="Delete Weekly groceries"]').trigger('click')
		await nextTick()
		await wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')
		await flushPromises()

		expect(api.deleteList).toHaveBeenCalledWith('l1')
		expect(wrapper.text()).not.toContain('Weekly groceries')
	})

	it('keeps the list when the dialog is closed without confirming', async () => {
		const wrapper = await render()

		await wrapper.find('button[aria-label="Delete Weekly groceries"]').trigger('click')
		await nextTick()
		await wrapper.findComponent(ConfirmDialog).vm.$emit('update:open', false)
		await nextTick()

		expect(api.deleteList).not.toHaveBeenCalled()
		expect(wrapper.text()).toContain('Weekly groceries')
	})
})
