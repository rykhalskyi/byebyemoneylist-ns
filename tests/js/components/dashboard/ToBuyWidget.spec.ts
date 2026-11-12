import type { ListItem, ShoppingList } from '../../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ToBuyWidget from '../../../../src/components/dashboard/widgets/ToBuyWidget.vue'
import * as api from '../../../../src/services/listsApi.ts'

vi.mock('../../../../src/services/listsApi.ts', () => ({
	fetchActiveToBuyList: vi.fn(),
	fetchListItems: vi.fn(),
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

function item(id: string, name: string, position: number, checked = false): ListItem {
	return {
		id,
		listId: 'l1',
		productId: null,
		productName: name,
		customName: name,
		price: null,
		quantity: 1,
		isChecked: checked,
		position,
		discount: null,
		createdAt: null,
		updatedAt: null,
	}
}

async function render() {
	const wrapper = mount(ToBuyWidget)
	await flushPromises()
	await flushPromises()
	return wrapper
}

describe('ToBuyWidget', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(api.fetchActiveToBuyList).mockResolvedValue(list())
	})

	it('shows the list title, progress and the first three pending items', async () => {
		vi.mocked(api.fetchListItems).mockResolvedValue([
			item('i1', 'Milk', 0),
			item('i2', 'Bread', 1, true),
			item('i3', 'Cheese', 2),
			item('i4', 'Eggs', 3),
			item('i5', 'Water', 4),
		])
		const wrapper = await render()

		expect(wrapper.text()).toContain('To Buy 12.11.2026')
		expect(wrapper.text()).toContain('1 of 5 done')
		expect(wrapper.text()).toContain('Milk')
		expect(wrapper.text()).toContain('Cheese')
		expect(wrapper.text()).toContain('Eggs')
		expect(wrapper.text()).not.toContain('Water')
	})

	it('opens the To Buy screen from the card', async () => {
		vi.mocked(api.fetchListItems).mockResolvedValue([item('i1', 'Milk', 0)])
		const wrapper = await render()

		await wrapper.find('button[aria-label="Open To Buy list"]').trigger('click')

		expect(wrapper.emitted('open')).toHaveLength(1)
	})

	it('shows the create affordance when no list is active', async () => {
		vi.mocked(api.fetchActiveToBuyList).mockResolvedValue(null)
		const wrapper = await render()

		expect(wrapper.text()).toContain('No active To Buy list')
		await wrapper.findAll('button').find((button) => button.text() === 'Create To Buy list')!.trigger('click')

		expect(wrapper.emitted('open')).toHaveLength(1)
	})

	it('celebrates when everything is checked', async () => {
		vi.mocked(api.fetchListItems).mockResolvedValue([item('i1', 'Milk', 0, true)])
		const wrapper = await render()

		expect(wrapper.text()).toContain('All done')
	})
})
