import type { IFuseOptions } from 'fuse.js'
import type { Category, Product, Store } from '../types.ts'

import Fuse from 'fuse.js'

export interface CategorySearchItem extends Category {
	parentName: string
}

const BASE_OPTIONS = {
	threshold: 0.4,
	ignoreLocation: true,
	minMatchCharLength: 1,
	includeScore: true,
} satisfies Partial<IFuseOptions<unknown>>

const PRODUCT_OPTIONS: IFuseOptions<Product> = {
	...BASE_OPTIONS,
	keys: [
		{ name: 'name', weight: 3 },
		{ name: 'aliases', weight: 2 },
		{ name: 'barcode', weight: 2 },
	],
}

const CATEGORY_OPTIONS: IFuseOptions<CategorySearchItem> = {
	...BASE_OPTIONS,
	keys: [
		{ name: 'name', weight: 2 },
		{ name: 'parentName', weight: 1 },
	],
}

const STORE_OPTIONS: IFuseOptions<Store> = {
	...BASE_OPTIONS,
	keys: [
		{ name: 'name', weight: 2 },
		{ name: 'address', weight: 1 },
	],
}

export function createProductFuse(products: ReadonlyArray<Product>): Fuse<Product> {
	return new Fuse(products, PRODUCT_OPTIONS)
}

export function createCategoryFuse(categories: ReadonlyArray<CategorySearchItem>): Fuse<CategorySearchItem> {
	return new Fuse(categories, CATEGORY_OPTIONS)
}

export function createStoreFuse(stores: ReadonlyArray<Store>): Fuse<Store> {
	return new Fuse(stores, STORE_OPTIONS)
}

/**
 * Filter and rank items with the given Fuse index. An empty query returns the
 * items unchanged (original order, no ranking).
 *
 * @param fuse the Fuse index, or null when no index is available
 * @param items the items to return when there is no query
 * @param query the raw search query
 * @return the matching items ranked by relevance
 */
export function search<T>(fuse: Fuse<T> | null, items: ReadonlyArray<T>, query: string): T[] {
	const trimmed = query.trim()
	if (trimmed === '' || fuse === null) {
		return [...items]
	}
	return fuse.search(trimmed).map((result) => result.item)
}
