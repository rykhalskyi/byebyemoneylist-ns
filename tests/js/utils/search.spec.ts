import type { Product, Store } from '../../../src/types.ts'
import type { CategorySearchItem } from '../../../src/utils/search.ts'

import { describe, expect, it } from 'vitest'
import { createCategoryFuse, createProductFuse, createStoreFuse, search } from '../../../src/utils/search.ts'

function product(name: string, overrides: Partial<Product> = {}): Product {
	return {
		id: name.toLowerCase().replace(/\s+/g, '-'),
		name,
		barcode: null,
		categoryId: null,
		aliases: [],
		isFavorite: false,
		status: 'reviewed',
		isSubscription: false,
		isIncome: false,
		lastPrice: null,
		lastPriceDate: null,
		hasPicture: false,
		...overrides,
	}
}

function category(id: string, name: string, parentName = ''): CategorySearchItem {
	return {
		id,
		name,
		color: null,
		emoji: null,
		parentId: parentName === '' ? null : 'parent',
		income: false,
		status: 'confirmed',
		parentName,
	}
}

function store(id: string, name: string, address: string | null = null): Store {
	return { id, name, address, categoryIds: [] }
}

describe('product search', () => {
	const products = [
		product('Milk'),
		product('Zebra cookies'),
		product('Bread', { aliases: ['loaf'], barcode: '4001234567890' }),
		product('Chocolate', { aliases: ['milk chocolate'] }),
	]

	const fuse = createProductFuse(products)

	it('returns all items for an empty query', () => {
		expect(search(fuse, products, '')).toEqual(products)
	})

	it('matches a case-insensitive substring', () => {
		const names = search(fuse, products, 'milk').map((item) => item.name)
		expect(names).toContain('Milk')
	})

	it('matches with a typo (fuzzy)', () => {
		const names = search(fuse, products, 'zbra').map((item) => item.name)
		expect(names).toContain('Zebra cookies')
	})

	it('matches aliases', () => {
		const names = search(fuse, products, 'loaf').map((item) => item.name)
		expect(names).toContain('Bread')
	})

	it('matches barcodes', () => {
		const names = search(fuse, products, '4001234567890').map((item) => item.name)
		expect(names).toContain('Bread')
	})

	it('ranks a name match above an alias-only match', () => {
		const names = search(fuse, products, 'milk').map((item) => item.name)
		expect(names.indexOf('Milk')).toBeLessThan(names.indexOf('Chocolate'))
	})
})

describe('category search', () => {
	const categories = [category('1', 'Food'), category('2', 'Dairy', 'Food'), category('3', 'Household')]
	const fuse = createCategoryFuse(categories)

	it('matches by name', () => {
		expect(search(fuse, categories, 'dairy').map((item) => item.id)).toEqual(['2'])
	})

	it('matches by parent name', () => {
		const ids = search(fuse, categories, 'food').map((item) => item.id)
		expect(ids).toContain('2')
	})
})

describe('store search', () => {
	const stores = [store('1', 'Aldi', 'Hauptstraße 1'), store('2', 'Rewe', null)]
	const fuse = createStoreFuse(stores)

	it('matches by name', () => {
		expect(search(fuse, stores, 'aldi').map((item) => item.id)).toEqual(['1'])
	})

	it('matches by address', () => {
		expect(search(fuse, stores, 'haupt').map((item) => item.id)).toEqual(['1'])
	})
})
