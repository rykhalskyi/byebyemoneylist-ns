import type { ListItem, ShoppingList } from '../../../src/types.ts'

import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useShoppingLists } from '../../../src/composables/useShoppingLists.ts'
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
		hasReceipt: false,
		...overrides,
	}
}

function item(overrides: Partial<ListItem> = {}): ListItem {
	return {
		id: 'i1',
		listId: 'l1',
		productId: 'p1',
		productName: 'Milk',
		price: 1.5,
		quantity: 2,
		isChecked: false,
		createdAt: null,
		...overrides,
	}
}

describe('useShoppingLists', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(api.fetchLists).mockResolvedValue([list()])
		vi.mocked(api.fetchStores).mockResolvedValue([])
		vi.mocked(api.fetchCategories).mockResolvedValue([])
		vi.mocked(api.fetchProducts).mockResolvedValue([])
		vi.mocked(api.fetchListItems).mockResolvedValue([])
	})

	it('loads data and expands the current period', async () => {
		const store = useShoppingLists()
		await store.loadData()

		expect(store.lists.value).toHaveLength(1)
		expect(store.error.value).toBeNull()
		expect(store.loading.value).toBe(false)
		const year = String(new Date().getFullYear())
		expect(store.expandedYears.value[year]).toBe(true)
	})

	it('surfaces a load failure', async () => {
		vi.mocked(api.fetchLists).mockRejectedValue(new Error('nope'))
		const store = useShoppingLists()
		await store.loadData()

		expect(store.error.value).toBe('Failed to load your shopping lists.')
	})

	it('toggles year and month groups', () => {
		const store = useShoppingLists()
		store.toggleYear('2026')
		expect(store.expandedYears.value['2026']).toBe(true)
		store.toggleYear('2026')
		expect(store.expandedYears.value['2026']).toBe(false)

		store.toggleMonth('2026-9')
		expect(store.expandedMonths.value['2026-9']).toBe(true)
	})

	it('loads items lazily when a list is expanded', async () => {
		const store = useShoppingLists()
		const target = list()
		vi.mocked(api.fetchListItems).mockResolvedValue([item()])

		store.toggleExpand(target)
		expect(store.expandedId.value).toBe('l1')
		await flushPromises()

		expect(api.fetchListItems).toHaveBeenCalledWith('l1')
		expect(store.itemsByList.value.l1).toHaveLength(1)
		expect(store.itemsLoading.value.l1).toBe(false)
	})

	it('collapses an expanded list', () => {
		const store = useShoppingLists()
		const target = list()
		store.toggleExpand(target)
		store.toggleExpand(target)
		expect(store.expandedId.value).toBeNull()
	})

	it('removes a deleted item optimistically', async () => {
		vi.mocked(api.deleteListItem).mockResolvedValue()
		const store = useShoppingLists()
		store.itemsByList.value = { l1: [item()] }

		await store.onDeleteItem(list(), item())

		expect(api.deleteListItem).toHaveBeenCalledWith('l1', 'i1')
		expect(store.itemsByList.value.l1).toHaveLength(0)
	})

	it('adds a created item and closes the add dialog', async () => {
		const store = useShoppingLists()
		store.itemsByList.value = { l1: [] }
		store.openAddProduct(list())
		expect(store.addProductListId.value).toBe('l1')

		store.onItemAdded(item())

		expect(store.itemsByList.value.l1).toHaveLength(1)
		expect(store.addProductListId.value).toBeNull()
	})

	it('derives the add product type from the open list', () => {
		const store = useShoppingLists()
		store.lists.value = [list({ id: 'i1', isIncome: true }), list({ id: 's1', isSubscription: true }), list({ id: 'n1' })]

		store.openAddProduct(store.lists.value[0])
		expect(store.addProductType.value).toBe('income')
		store.openAddProduct(store.lists.value[1])
		expect(store.addProductType.value).toBe('subscriptions')
		store.openAddProduct(store.lists.value[2])
		expect(store.addProductType.value).toBeUndefined()
	})

	it('replaces a saved purchase and clears its cached items', async () => {
		const store = useShoppingLists()
		const original = list()
		store.lists.value = [original]
		store.itemsByList.value = { l1: [item()] }
		const updated = { ...original, finalTotal: 12 }

		store.onPurchaseSaved(updated)

		expect(store.lists.value[0].finalTotal).toBe(12)
		expect(store.itemsByList.value.l1).toBeUndefined()
	})

	it('deletes a list after confirmation', async () => {
		vi.mocked(api.deleteList).mockResolvedValue()
		const store = useShoppingLists()
		store.lists.value = [list()]
		store.askDelete(list())
		expect(store.deleteMessage.value).toContain('Weekly groceries')

		await store.onConfirmDelete()

		expect(api.deleteList).toHaveBeenCalledWith('l1')
		expect(store.lists.value).toHaveLength(0)
		expect(store.pendingDelete.value).toBeNull()
	})

	it('keeps the list when the confirmation is dismissed', () => {
		const store = useShoppingLists()
		store.askDelete(list())
		store.closeConfirmDialog(false)
		expect(store.pendingDelete.value).toBeNull()
	})

	it('clears the receipt flag after the receipt is deleted', () => {
		const store = useShoppingLists()
		store.lists.value = [list({ hasReceipt: true })]
		store.onReceiptDeleted('l1')
		expect(store.lists.value[0].hasReceipt).toBe(false)
	})
})
