import type { ListItem, ShoppingList } from '../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ToBuy from '../../../src/views/ToBuy.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchActiveToBuyList: vi.fn(),
	fetchListItems: vi.fn(),
	createToBuyList: vi.fn(),
	addToBuyItem: vi.fn(),
	updateListItem: vi.fn(),
	deleteListItem: vi.fn(),
}))

function list(overrides: Partial<ShoppingList> = {}): ShoppingList {
	return {
		id: 'l1',
		name: 'To Buy 12.11.2026',
		storeId: null,
		categoryId: null,
		status: 'new',
		kind: 'NEED_TO_BUY',
		isActive: true,
		finalTotal: null,
		totalPrice: null,
		createdAt: null,
		isIncome: false,
		isSubscription: false,
		isRecurring: false,
		hasReceipt: false,
		...overrides,
	}
}

function item(id: string, name: string, overrides: Partial<ListItem> = {}): ListItem {
	return {
		id,
		listId: 'l1',
		productId: null,
		productName: name,
		customName: name,
		price: null,
		quantity: 1,
		isChecked: false,
		position: 0,
		discount: null,
		createdAt: null,
		updatedAt: null,
		...overrides,
	}
}

async function render() {
	const wrapper = mount(ToBuy, { global: { stubs: { teleport: true } } })
	await flushPromises()
	await flushPromises()
	return wrapper
}

enableAutoUnmount(afterEach)

describe('ToBuy', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(api.fetchActiveToBuyList).mockResolvedValue(list())
		vi.mocked(api.fetchListItems).mockResolvedValue([item('i1', 'Milk'), item('i2', 'Bread', { isChecked: true, position: 1 })])
		vi.mocked(api.createToBuyList).mockResolvedValue(list())
		vi.mocked(api.addToBuyItem).mockResolvedValue(item('i3', 'Cheese', { position: 2 }))
		vi.mocked(api.updateListItem).mockResolvedValue(item('i1', 'Milk', { isChecked: true }))
		vi.mocked(api.deleteListItem).mockResolvedValue()
	})

	it('renders the active list and its items with progress', async () => {
		const wrapper = await render()

		expect(wrapper.text()).toContain('To Buy 12.11.2026')
		expect(wrapper.text()).toContain('Milk')
		expect(wrapper.text()).toContain('Bread')
		expect(wrapper.text()).toContain('1 of 2 done')
	})

	it('creates a list when none is active', async () => {
		vi.mocked(api.fetchActiveToBuyList).mockResolvedValue(null)
		vi.mocked(api.fetchListItems).mockResolvedValue([])
		const wrapper = await render()

		expect(wrapper.text()).toContain('No active To Buy list')
		await wrapper.findAll('button').find((button) => button.text() === 'Create To Buy list')!.trigger('click')
		await flushPromises()

		expect(api.createToBuyList).toHaveBeenCalledWith('To Buy')
		expect(wrapper.text()).toContain('To Buy 12.11.2026')
	})

	it('adds an item from the quick-add row', async () => {
		const wrapper = await render()

		await wrapper.findComponent(NcTextField).find('input').setValue('Cheese')
		await wrapper.findAll('button').find((button) => button.text() === 'Add item')!.trigger('click')
		await flushPromises()

		expect(api.addToBuyItem).toHaveBeenCalledWith('l1', 'Cheese')
		expect(wrapper.text()).toContain('Cheese')
	})

	it('checks an item', async () => {
		const wrapper = await render()

		await wrapper.findAllComponents(NcCheckboxRadioSwitch)[0]!.vm.$emit('update:modelValue', true)
		await flushPromises()

		expect(api.updateListItem).toHaveBeenCalledWith('l1', 'i1', { isChecked: true })
	})

	it('deletes an item and restores it on undo', async () => {
		const wrapper = await render()

		await wrapper.find('button[aria-label="Delete Milk"]').trigger('click')
		await nextTick()
		expect(wrapper.text()).not.toContain('Milk')
		expect(wrapper.text()).toContain('Item deleted')

		await wrapper.findAll('button').find((button) => button.text() === 'Undo')!.trigger('click')
		await nextTick()
		expect(wrapper.text()).toContain('Milk')
	})

	it('emits close from the back button', async () => {
		const wrapper = await render()

		await wrapper.find('button[aria-label="Back"]').trigger('click')

		expect(wrapper.emitted('close')).toHaveLength(1)
	})
})
