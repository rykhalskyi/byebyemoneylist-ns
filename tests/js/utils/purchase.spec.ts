import type { ListItem, ShoppingList } from '../../../src/types.ts'

import { describe, expect, it } from 'vitest'
import { defaultListName, findNewListByName, itemsTotal, parsePrice } from '../../../src/utils/purchase.ts'

function item(overrides: Partial<ListItem> = {}): ListItem {
	return {
		id: 'item',
		listId: 'list',
		productId: 'product',
		productName: 'Product',
		price: null,
		quantity: 1,
		isChecked: false,
		createdAt: null,
		...overrides,
	}
}

function list(id: string, name: string, overrides: Partial<ShoppingList> = {}): ShoppingList {
	return {
		id,
		name,
		storeId: null,
		categoryId: null,
		status: 'new',
		finalTotal: null,
		totalPrice: null,
		createdAt: null,
		isIncome: false,
		isSubscription: false,
		isRecurring: false,
		...overrides,
	}
}

describe('parsePrice', () => {
	it('parses dot and comma decimals', () => {
		expect(parsePrice('12.34')).toBe(12.34)
		expect(parsePrice('12,34')).toBe(12.34)
	})

	it('returns null for blank and invalid input', () => {
		expect(parsePrice('')).toBeNull()
		expect(parsePrice('   ')).toBeNull()
		expect(parsePrice('abc')).toBeNull()
	})
})

describe('itemsTotal', () => {
	it('sums price times quantity and ignores items without a price', () => {
		const items = [
			item({ price: 2, quantity: 3 }),
			item({ price: 1.5, quantity: 1 }),
			item({ price: null, quantity: 5 }),
		]
		expect(itemsTotal(items)).toBe(7.5)
	})

	it('returns zero for an empty list', () => {
		expect(itemsTotal([])).toBe(0)
	})
})

describe('defaultListName', () => {
	it('combines the store name with the date', () => {
		const name = defaultListName('Aldi', new Date('2026-09-12T10:00:00Z'))
		expect(name.startsWith('Aldi ')).toBe(true)
		expect(name.length).toBeGreaterThan('Aldi '.length)
	})
})

describe('findNewListByName', () => {
	it('matches a new list case-insensitively and trims whitespace', () => {
		const lists = [list('a', 'Weekly Groceries')]
		expect(findNewListByName(lists, '  weekly groceries ')?.id).toBe('a')
	})

	it('ignores finished lists', () => {
		const lists = [list('a', 'Weekly Groceries', { status: 'finished' })]
		expect(findNewListByName(lists, 'Weekly Groceries')).toBeNull()
	})

	it('returns null for a blank name', () => {
		expect(findNewListByName([list('a', 'Weekly')], '   ')).toBeNull()
	})
})
