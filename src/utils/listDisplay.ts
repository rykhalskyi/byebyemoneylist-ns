import type { Category, ListItem, ListStatus, Product, ShoppingList } from '../types.ts'

import { mdiAutorenew, mdiCart, mdiCashPlus } from '@mdi/js'
import { formatDate, formatTotal } from './format.ts'
import { getCanonicalLocale, t } from './l10n.ts'

export type StatusVariant = 'secondary' | 'success' | 'tertiary'

export function listIcon(list: ShoppingList): string {
	if (list.isIncome) {
		return mdiCashPlus
	}
	if (list.isSubscription) {
		return mdiAutorenew
	}
	return mdiCart
}

export function addItemLabel(list: ShoppingList): string {
	if (list.isIncome) {
		return t('Add income source')
	}
	if (list.isSubscription) {
		return t('Add subscription')
	}
	return t('Add product')
}

export function statusLabel(status: ListStatus): string {
	switch (status) {
		case 'finished':
			return t('Finished')
		case 'archived':
			return t('Archived')
		default:
			return t('New')
	}
}

export function statusVariant(status: ListStatus): StatusVariant {
	if (status === 'finished') {
		return 'success'
	}
	if (status === 'archived') {
		return 'tertiary'
	}
	return 'secondary'
}

export function formatQuantity(quantity: number): string {
	if (Number.isInteger(quantity)) {
		return String(quantity)
	}
	return new Intl.NumberFormat(getCanonicalLocale(), {
		maximumFractionDigits: 2,
	}).format(quantity)
}

export function itemDetails(item: ListItem): string {
	if (item.price === null) {
		return ''
	}
	return formatTotal(item.price * item.quantity)
}

export function itemSubname(item: ListItem): string {
	const quantity = formatQuantity(item.quantity)
	if (item.price === null) {
		return quantity
	}
	return `${quantity} × ${formatTotal(item.price)}`
}

export function checkedSum(items: readonly ListItem[] | undefined): number {
	if (items === undefined) {
		return 0
	}
	return items
		.filter((item) => item.price !== null)
		.reduce((sum, item) => sum + (item.price ?? 0) * item.quantity, 0)
}

export function listTotal(list: ShoppingList, items: readonly ListItem[] | undefined): number | null {
	if (list.finalTotal !== null) {
		return list.finalTotal
	}
	if (items !== undefined) {
		return checkedSum(items)
	}
	return list.totalPrice
}

export function priceText(list: ShoppingList, items: readonly ListItem[] | undefined): string | null {
	const total = listTotal(list, items)
	return total === null ? null : formatTotal(total)
}

export function listSubname(list: ShoppingList, storeName: string, categoryName: string): string {
	const parts = [storeName, categoryName].filter(Boolean)
	const date = formatDate(list.createdAt)
	return [parts.join(' · '), date].filter(Boolean).join(' · ')
}

export function listMarkStyle(color: string | null): Record<string, string> {
	return color === null ? {} : { 'border-inline-start': `6px solid ${color}`}
}

export function categoryColor(categoryId: string | null, categories: readonly Category[]): string | null {
	return categories.find((category) => category.id === categoryId)?.color ?? null
}

export function productCategoryColor(item: ListItem, products: readonly Product[], categories: readonly Category[]): string | null {
	const product = products.find((candidate) => candidate.id === item.productId)
	return product?.categoryId ? categoryColor(product.categoryId, categories) : null
}
