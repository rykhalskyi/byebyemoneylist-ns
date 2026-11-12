import type { Category, ListItem, ListKind, ListStatus, Product, ShoppingList } from '../types.ts'

import { mdiAutorenew, mdiCart, mdiCashPlus } from '@mdi/js'
import { formatDate, formatTotal } from './format.ts'
import { getCanonicalLocale, t } from './l10n.ts'

export type StatusVariant = 'secondary' | 'success' | 'tertiary'

/**
 * Resolve the list kind, falling back to the legacy flags for older rows.
 *
 * @param list the list to inspect
 */
export function deriveKind(list: ShoppingList): ListKind {
	if (list.kind !== undefined && list.kind !== null) {
		return list.kind
	}
	if (list.isIncome) {
		return 'INCOME'
	}
	if (list.isSubscription) {
		return 'SUBSCRIPTION'
	}
	if (list.status === 'finished') {
		return 'PURCHASE'
	}
	return 'NEED_TO_BUY'
}

export function isToBuy(list: ShoppingList): boolean {
	return deriveKind(list) === 'NEED_TO_BUY'
}

export function isToBuyActive(list: ShoppingList): boolean {
	return isToBuy(list) && list.isActive === true
}

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
	if (isToBuy(list)) {
		return t('Add item')
	}
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
		default:
			return t('New')
	}
}

export function statusVariant(status: ListStatus): StatusVariant {
	if (status === 'finished') {
		return 'success'
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

export function itemName(item: ListItem): string {
	return item.customName ?? item.productName
}

export function itemDetails(item: ListItem): string {
	if (item.productId === null || item.price === null) {
		return ''
	}
	return formatTotal(item.price * item.quantity)
}

export function itemSubname(item: ListItem): string {
	if (item.productId === null) {
		return ''
	}
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
	return color === null ? {} : { 'border-inline-start': `3px solid ${color}` }
}

export function categoryColor(categoryId: string | null, categories: readonly Category[]): string | null {
	return categories.find((category) => category.id === categoryId)?.color ?? null
}

export function isSharedList(list: ShoppingList): boolean {
	return list.sharedBy !== null && list.sharedBy !== undefined
}

export function isRevokedList(list: ShoppingList): boolean {
	return list.revoked === true
}

/**
 * An owned list that the current user has shared with other users. Lists shared
 * with the user are handled separately by {@link isSharedList}.
 *
 * @param list the list to inspect
 */
export function hasActiveShares(list: ShoppingList): boolean {
	return list.hasShares === true
}

/**
 * A shared list is editable only in read/write mode. Revoked shares are never
 * editable (they are a name-only placeholder).
 *
 * @param list the list to inspect
 */
export function isReadOnlyList(list: ShoppingList): boolean {
	if (isRevokedList(list)) {
		return true
	}
	return isSharedList(list) && list.shareMode !== 'readwrite'
}

export function sharedByLabel(list: ShoppingList): string {
	return t('Shared by {user}', { user: list.sharedBy ?? '' })
}

export function isSharedEntity(entity: { shared?: boolean }): boolean {
	return entity.shared === true
}

export function productCategory(item: ListItem, products: readonly Product[], categories: readonly Category[]): Category | null {
	const product = products.find((candidate) => candidate.id === item.productId)
	if (!product?.categoryId) {
		return null
	}
	return categories.find((category) => category.id === product.categoryId) ?? null
}

export function productCategoryColor(item: ListItem, products: readonly Product[], categories: readonly Category[]): string | null {
	return productCategory(item, products, categories)?.color ?? null
}
