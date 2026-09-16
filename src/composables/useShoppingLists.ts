import type { Category, ListItem, Product, ShoppingList, Store } from '../types.ts'

import { computed, ref } from 'vue'
import { deleteList, deleteListItem, fetchCategories, fetchListItems, fetchLists, fetchProducts, fetchStores } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'
import { groupListsByMonth } from '../utils/listGroups.ts'

export function useShoppingLists() {
	const lists = ref<ShoppingList[]>([])
	const stores = ref<Store[]>([])
	const categories = ref<Category[]>([])
	const products = ref<Product[]>([])
	const loading = ref(true)
	const error = ref<string | null>(null)
	const expandedId = ref<string | null>(null)
	const itemsByList = ref<Record<string, ListItem[]>>({})
	const itemsLoading = ref<Record<string, boolean>>({})
	const itemsError = ref<Record<string, string>>({})
	const expandedYears = ref<Record<string, boolean>>({})
	const expandedMonths = ref<Record<string, boolean>>({})
	const pendingDelete = ref<ShoppingList | null>(null)
	const deleting = ref(false)
	const receiptList = ref<ShoppingList | null>(null)
	const addProductListId = ref<string | null>(null)

	const groups = computed(() => groupListsByMonth(lists.value))

	const addProductType = computed<'subscriptions' | 'income' | undefined>(() => {
		const list = lists.value.find((candidate) => candidate.id === addProductListId.value)
		if (list === undefined) {
			return undefined
		}
		if (list.isIncome) {
			return 'income'
		}
		if (list.isSubscription) {
			return 'subscriptions'
		}
		return undefined
	})

	const deleteMessage = computed(() => {
		const list = pendingDelete.value
		if (list === null) {
			return ''
		}
		return t('Delete "{name}"? This cannot be undone.', { name: list.name })
	})

	async function loadData() {
		loading.value = true
		error.value = null
		try {
			const [listData, storeData, categoryData, productData] = await Promise.all([
				fetchLists(),
				fetchStores(),
				fetchCategories(),
				fetchProducts(),
			])
			lists.value = listData
			stores.value = storeData
			categories.value = categoryData
			products.value = productData
			expandListGroup(new Date().toISOString())
		} catch {
			error.value = t('Failed to load your shopping lists.')
		} finally {
			loading.value = false
		}
	}

	function onCreated(list: ShoppingList) {
		lists.value = [list, ...lists.value]
		expandListGroup(list.createdAt)
	}

	function onPurchaseSaved(list: ShoppingList) {
		if (lists.value.some((candidate) => candidate.id === list.id)) {
			lists.value = lists.value.map((candidate) => (candidate.id === list.id ? list : candidate))
		} else {
			lists.value = [list, ...lists.value]
		}
		removeLoadedItems(list.id)
		expandListGroup(list.createdAt)
	}

	function expandListGroup(iso: string | null) {
		if (iso === null) {
			return
		}
		const date = new Date(iso)
		if (Number.isNaN(date.getTime())) {
			return
		}
		const yearKey = String(date.getFullYear())
		const monthKey = `${yearKey}-${date.getMonth() + 1}`
		expandedYears.value = { ...expandedYears.value, [yearKey]: true }
		expandedMonths.value = { ...expandedMonths.value, [monthKey]: true }
	}

	function toggleYear(key: string) {
		expandedYears.value = { ...expandedYears.value, [key]: !expandedYears.value[key] }
	}

	function toggleMonth(key: string) {
		expandedMonths.value = { ...expandedMonths.value, [key]: !expandedMonths.value[key] }
	}

	function toggleExpand(list: ShoppingList) {
		if (expandedId.value === list.id) {
			expandedId.value = null
			return
		}
		expandedId.value = list.id
		if (itemsByList.value[list.id] === undefined) {
			loadItems(list.id)
		}
	}

	async function loadItems(listId: string) {
		itemsLoading.value = { ...itemsLoading.value, [listId]: true }
		itemsError.value = { ...itemsError.value, [listId]: '' }
		try {
			itemsByList.value = { ...itemsByList.value, [listId]: await fetchListItems(listId) }
		} catch {
			itemsError.value = { ...itemsError.value, [listId]: t('Failed to load the items.') }
		} finally {
			itemsLoading.value = { ...itemsLoading.value, [listId]: false }
		}
	}

	function removeLoadedItems(listId: string) {
		if (itemsByList.value[listId] === undefined) {
			return
		}
		const items = { ...itemsByList.value }
		delete items[listId]
		itemsByList.value = items
	}

	function onItemAdded(item: ListItem) {
		const items = itemsByList.value[item.listId] ?? []
		itemsByList.value = { ...itemsByList.value, [item.listId]: [...items, item] }
		addProductListId.value = null
	}

	async function onDeleteItem(list: ShoppingList, item: ListItem) {
		const items = itemsByList.value[list.id] ?? []
		itemsByList.value = { ...itemsByList.value, [list.id]: items.filter((candidate) => candidate.id !== item.id) }
		try {
			await deleteListItem(list.id, item.id)
		} catch {
			await loadItems(list.id)
		}
	}

	function openAddProduct(list: ShoppingList) {
		addProductListId.value = list.id
	}

	function closeAddProduct() {
		addProductListId.value = null
	}

	function askDelete(list: ShoppingList) {
		pendingDelete.value = list
	}

	function closeConfirmDialog(open: boolean) {
		if (!open && !deleting.value) {
			pendingDelete.value = null
		}
	}

	async function onConfirmDelete() {
		const list = pendingDelete.value
		if (list === null) {
			return
		}
		deleting.value = true
		try {
			await deleteList(list.id)
			lists.value = lists.value.filter((candidate) => candidate.id !== list.id)
			if (expandedId.value === list.id) {
				expandedId.value = null
			}
			removeLoadedItems(list.id)
		} catch {
			await loadData()
		} finally {
			deleting.value = false
			pendingDelete.value = null
		}
	}

	function openReceipt(list: ShoppingList) {
		receiptList.value = list
	}

	function onReceiptDeleted(listId: string) {
		lists.value = lists.value.map((candidate) => (candidate.id === listId ? { ...candidate, hasReceipt: false } : candidate))
	}

	function closeReceipt(open: boolean) {
		if (!open) {
			receiptList.value = null
		}
	}

	return {
		lists,
		stores,
		categories,
		products,
		loading,
		error,
		groups,
		itemsByList,
		itemsLoading,
		itemsError,
		expandedId,
		expandedYears,
		expandedMonths,
		pendingDelete,
		deleting,
		receiptList,
		addProductListId,
		addProductType,
		deleteMessage,
		loadData,
		onCreated,
		onPurchaseSaved,
		toggleYear,
		toggleMonth,
		toggleExpand,
		onItemAdded,
		onDeleteItem,
		openAddProduct,
		closeAddProduct,
		askDelete,
		closeConfirmDialog,
		onConfirmDelete,
		openReceipt,
		onReceiptDeleted,
		closeReceipt,
	}
}
