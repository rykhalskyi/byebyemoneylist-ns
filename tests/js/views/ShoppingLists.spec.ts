import type { Category, Product, ShoppingList, Store } from '../../../src/types.ts'

import { mdiAutorenew, mdiCart, mdiCashPlus, mdiDotsVertical } from '@mdi/js'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ConfirmDialog from '../../../src/components/ConfirmDialog.vue'
import NewListDialog from '../../../src/components/NewListDialog.vue'
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
		isIncome: false,
		isSubscription: false,
		isRecurring: false,
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

async function openListMenu(wrapper: Awaited<ReturnType<typeof render>>, index = 0) {
	await wrapper.findAll('.action-item__menutoggle')[index].trigger('click')
	await flushPromises()
	await nextTick()
}

async function clickDeleteMenuItem(wrapper: Awaited<ReturnType<typeof render>>) {
	await wrapper.findComponent(NcActionButton).find('button').trigger('click')
	await flushPromises()
	await nextTick()
}

describe('ShoppingLists', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('renders a three-dot menu with a delete action for each list', async () => {
		const wrapper = await render([list(), list({ id: 'l2', name: 'Weekend' })])
		expect(wrapper.findAll('.action-item__menutoggle')).toHaveLength(2)
		expect(wrapper.find('.action-item__menutoggle').findComponent(NcIconSvgWrapper).props('path')).toBe(mdiDotsVertical)

		await openListMenu(wrapper)
		expect(wrapper.findComponent(NcActionButton).text()).toBe('Delete')
	})

	it('asks for confirmation before deleting', async () => {
		const wrapper = await render()
		await openListMenu(wrapper)
		await clickDeleteMenuItem(wrapper)

		const dialog = wrapper.findComponent(ConfirmDialog)
		expect(dialog.props('open')).toBe(true)
		expect(dialog.props('title')).toBe('Delete list')
		expect(dialog.props('message')).toContain('Weekly groceries')
		expect(api.deleteList).not.toHaveBeenCalled()
	})

	it('deletes the list after confirmation', async () => {
		vi.mocked(api.deleteList).mockResolvedValue()
		const wrapper = await render()

		await openListMenu(wrapper)
		await clickDeleteMenuItem(wrapper)
		await wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')
		await flushPromises()

		expect(api.deleteList).toHaveBeenCalledWith('l1')
		expect(wrapper.text()).not.toContain('Weekly groceries')
	})

	it('keeps the list when the dialog is closed without confirming', async () => {
		const wrapper = await render()

		await openListMenu(wrapper)
		await clickDeleteMenuItem(wrapper)
		await wrapper.findComponent(ConfirmDialog).vm.$emit('update:open', false)
		await nextTick()

		expect(api.deleteList).not.toHaveBeenCalled()
		expect(wrapper.text()).toContain('Weekly groceries')
	})

	it('renders an Add income button', async () => {
		const wrapper = await render()
		const button = wrapper.findAll('button').find((candidate) => candidate.text() === 'Add income')
		expect(button).toBeDefined()
	})

	it('opens the new list dialog preset for income', async () => {
		const wrapper = await render()

		await wrapper.findAll('button').find((candidate) => candidate.text() === 'Add income')!.trigger('click')
		await nextTick()

		expect(wrapper.findComponent(NewListDialog).props('isIncome')).toBe(true)
	})

	it('uses a distinct icon for income and subscription lists', async () => {
		const paths = (wrapper: Awaited<ReturnType<typeof render>>) => wrapper
			.findAllComponents(NcIconSvgWrapper)
			.map((icon) => icon.props('path'))

		const income = await render([list({ id: 'i1', name: 'Salary', isIncome: true })])
		expect(paths(income).filter((path) => path === mdiCashPlus)).toHaveLength(2)

		const subscription = await render([list({ id: 's1', name: 'Netflix', isSubscription: true })])
		expect(paths(subscription)).toContain(mdiAutorenew)

		const normal = await render([list({ id: 'n1', name: 'Groceries' })])
		expect(paths(normal)).toContain(mdiCart)
	})

	it('labels the add button based on the list type', async () => {
		const subscription = await render([list({ id: 's1', name: 'Netflix', isSubscription: true })])
		await subscription.findComponent(NcListItem).find('.list-item__anchor').trigger('click')
		await flushPromises()
		expect(subscription.findAll('button').some((candidate) => candidate.text() === 'Add subscription')).toBe(true)

		const income = await render([list({ id: 'i1', name: 'Salary', isIncome: true })])
		await income.findComponent(NcListItem).find('.list-item__anchor').trigger('click')
		await flushPromises()
		expect(income.findAll('button').some((candidate) => candidate.text() === 'Add income source')).toBe(true)
	})
})
