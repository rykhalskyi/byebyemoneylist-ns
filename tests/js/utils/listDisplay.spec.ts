import type { Category, ListItem, Product, ShoppingList } from '../../../src/types.ts'

import { mdiAutorenew, mdiCart, mdiCashPlus } from '@mdi/js'
import { describe, expect, it } from 'vitest'
import { formatDate, formatTotal } from '../../../src/utils/format.ts'
import { addItemLabel, categoryColor, checkedSum, itemDetails, itemSubname, listIcon, listMarkStyle, listSubname, listTotal, priceText, productCategory, productCategoryColor, statusLabel, statusVariant } from '../../../src/utils/listDisplay.ts'

function list(overrides: Partial<ShoppingList> = {}): ShoppingList {
	return {
		id: 'l1',
		name: 'Weekly groceries',
		storeId: null,
		categoryId: null,
		status: 'new',
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

function item(overrides: Partial<ListItem> = {}): ListItem {
	return {
		id: 'i1',
		listId: 'l1',
		productId: 'p1',
		productName: 'Milk',
		price: null,
		quantity: 1,
		isChecked: false,
		createdAt: null,
		...overrides,
	}
}

describe('listIcon', () => {
	it('uses a dedicated icon per list type', () => {
		expect(listIcon(list())).toBe(mdiCart)
		expect(listIcon(list({ isIncome: true }))).toBe(mdiCashPlus)
		expect(listIcon(list({ isSubscription: true }))).toBe(mdiAutorenew)
	})
})

describe('addItemLabel', () => {
	it('labels the add action per list type', () => {
		expect(addItemLabel(list())).toBe('Add product')
		expect(addItemLabel(list({ isIncome: true }))).toBe('Add income source')
		expect(addItemLabel(list({ isSubscription: true }))).toBe('Add subscription')
	})
})

describe('statusLabel and statusVariant', () => {
	it('maps statuses to labels', () => {
		expect(statusLabel('new')).toBe('New')
		expect(statusLabel('finished')).toBe('Finished')
		expect(statusLabel('archived')).toBe('Archived')
	})

	it('maps statuses to chip variants', () => {
		expect(statusVariant('new')).toBe('secondary')
		expect(statusVariant('finished')).toBe('success')
		expect(statusVariant('archived')).toBe('tertiary')
	})
})

describe('item formatting', () => {
	it('renders details from price and quantity', () => {
		expect(itemDetails(item({ price: 2, quantity: 3 }))).toBe(formatTotal(6))
		expect(itemDetails(item())).toBe('')
	})

	it('renders the quantity and unit price in the subname', () => {
		expect(itemSubname(item({ price: 2, quantity: 3 }))).toBe(`3 × ${formatTotal(2)}`)
		expect(itemSubname(item({ quantity: 3 }))).toBe('3')
	})
})

describe('checkedSum', () => {
	it('sums only priced items, scaled by quantity', () => {
		expect(checkedSum([item({ price: 2, quantity: 3 }), item({ id: 'i2', price: null, quantity: 5 }), item({ id: 'i3', price: 1.5, quantity: 2 })])).toBe(9)
	})

	it('returns zero for missing items', () => {
		expect(checkedSum(undefined)).toBe(0)
		expect(checkedSum([])).toBe(0)
	})
})

describe('listTotal and priceText', () => {
	it('prefers the stored final total', () => {
		expect(listTotal(list({ finalTotal: 12, totalPrice: 5 }), undefined)).toBe(12)
	})

	it('computes from loaded items when no final total exists', () => {
		expect(listTotal(list({ totalPrice: 5 }), [item({ price: 2, quantity: 3 })])).toBe(6)
	})

	it('falls back to the stored price while items are not loaded', () => {
		expect(listTotal(list({ totalPrice: 5 }), undefined)).toBe(5)
	})

	it('formats the resolved total', () => {
		expect(priceText(list({ finalTotal: 12 }), undefined)).toBe(formatTotal(12))
		expect(priceText(list(), undefined)).toBeNull()
	})
})

describe('listSubname', () => {
	it('joins store, category and date, skipping empty parts', () => {
		const iso = '2026-09-10T10:00:00Z'
		expect(listSubname(list({ createdAt: iso }), 'Lidl', 'Groceries')).toBe(`Lidl · Groceries · ${formatDate(iso)}`)
		expect(listSubname(list({ createdAt: iso }), '', 'Groceries')).toBe(`Groceries · ${formatDate(iso)}`)
		expect(listSubname(list(), '', '')).toBe('')
	})
})

describe('colors', () => {
	const categories: Category[] = [
		{ id: 'c1', name: 'Groceries', color: '#ff0000', emoji: null, parentId: null, income: false },
		{ id: 'c2', name: 'No color', color: null, emoji: null, parentId: null, income: false },
	]

	it('resolves a category color', () => {
		expect(categoryColor('c1', categories)).toBe('#ff0000')
		expect(categoryColor('c2', categories)).toBeNull()
		expect(categoryColor('missing', categories)).toBeNull()
	})

	it('builds a mark style only when a color exists', () => {
		expect(listMarkStyle('#ff0000')).toEqual({ 'border-inline-start': '3px solid #ff0000' })
		expect(listMarkStyle(null)).toEqual({})
	})

	it('resolves the product category color', () => {
		const products: Product[] = [
			{ id: 'p1', name: 'Milk', barcode: null, categoryId: 'c1', aliases: [], isFavorite: false, status: 'ok', isSubscription: false, isIncome: false, lastPrice: null, lastPriceDate: null, hasPicture: false },
		]
		expect(productCategoryColor(item(), products, categories)).toBe('#ff0000')
		expect(productCategoryColor(item({ productId: 'missing' }), products, categories)).toBeNull()
	})

	it('resolves the product category', () => {
		const products: Product[] = [
			{ id: 'p1', name: 'Milk', barcode: null, categoryId: 'c1', aliases: [], isFavorite: false, status: 'ok', isSubscription: false, isIncome: false, lastPrice: null, lastPriceDate: null, hasPicture: false },
			{ id: 'p2', name: 'Water', barcode: null, categoryId: null, aliases: [], isFavorite: false, status: 'ok', isSubscription: false, isIncome: false, lastPrice: null, lastPriceDate: null, hasPicture: false },
		]
		expect(productCategory(item(), products, categories)).toBe(categories[0])
		expect(productCategory(item({ productId: 'p2' }), products, categories)).toBeNull()
		expect(productCategory(item({ productId: 'missing' }), products, categories)).toBeNull()
	})
})
