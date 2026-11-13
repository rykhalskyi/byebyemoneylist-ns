import type { Category, ListItem, ShoppingList } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddListDialog from '../../../src/components/AddListDialog.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	createList: vi.fn(),
	createToBuyList: vi.fn(),
	fetchActiveToBuyList: vi.fn(),
	fetchListItems: vi.fn(),
}))

const categories: Category[] = [
	{ id: 'c1', name: 'Salary', color: null, emoji: null, parentId: null, income: true },
	{ id: 'c2', name: 'Food', color: null, emoji: null, parentId: null, income: false },
]

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

function item(id: string, name: string): ListItem {
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
	}
}

async function render() {
	const wrapper = mount(AddListDialog, {
		props: { open: true, categories },
		global: { stubs: { teleport: true } },
	})
	await flushPromises()
	await flushPromises()
	return wrapper
}

function button(wrapper: Awaited<ReturnType<typeof render>>, label: string) {
	return wrapper.findAll('button').find((candidate) => candidate.text() === label)
}

describe('AddListDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(api.fetchActiveToBuyList).mockResolvedValue(null)
		vi.mocked(api.fetchListItems).mockResolvedValue([])
		vi.mocked(api.createToBuyList).mockResolvedValue(list())
		vi.mocked(api.createList).mockResolvedValue(list({ kind: 'SUBSCRIPTION' }))
	})

	it('offers the three list types and defaults to To Buy', async () => {
		const wrapper = await render()

		expect(button(wrapper, 'To Buy')).toBeDefined()
		expect(button(wrapper, 'Subscription')).toBeDefined()
		expect(button(wrapper, 'Income')).toBeDefined()
		expect(wrapper.findComponent(NcTextField).exists()).toBe(false)
		expect(wrapper.text()).toContain('plain checklist')
	})

	it('creates a To Buy list without fields', async () => {
		const wrapper = await render()

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.createToBuyList).toHaveBeenCalledWith('To Buy', [])
		expect(wrapper.emitted('created')).toHaveLength(1)
	})

	it('carries over only the checked pending items', async () => {
		vi.mocked(api.fetchActiveToBuyList).mockResolvedValue(list())
		vi.mocked(api.fetchListItems).mockResolvedValue([item('i1', 'Milk'), item('i2', 'Bread')])
		const wrapper = await render()

		const switches = wrapper.findAllComponents(NcCheckboxRadioSwitch)
		expect(switches).toHaveLength(2)
		await switches[0]!.vm.$emit('update:modelValue', true)

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.createToBuyList).toHaveBeenCalledWith('To Buy', ['Milk'])
	})

	it('creates a subscription with a required name and a fixed interval', async () => {
		const wrapper = await render()
		await button(wrapper, 'Subscription')!.trigger('click')

		await wrapper.findComponent(NcTextField).find('input').setValue('Netflix')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.createList).toHaveBeenCalledWith(expect.objectContaining({
			name: 'Netflix',
			isSubscription: true,
			isIncome: false,
			isRecurring: true,
			isForwardEmpty: false,
			recurringPeriod: 'MONTH',
		}))
	})

	it('creates income lists with the start-empty option', async () => {
		const wrapper = await render()
		await button(wrapper, 'Income')!.trigger('click')

		await wrapper.findComponent(NcTextField).find('input').setValue('Salary')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.createList).toHaveBeenCalledWith(expect.objectContaining({
			name: 'Salary',
			isIncome: true,
			isSubscription: false,
			isForwardEmpty: true,
		}))
	})
})
