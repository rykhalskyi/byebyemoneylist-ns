import type { ListItem, ShoppingList } from '../types.ts'

import { getCanonicalLocale } from './l10n.ts'

/**
 * Build a default list name from the store name and the current date, e.g.
 * "Aldi 9/12/26". Used when the purchase dialog is submitted without a list
 * name.
 *
 * @param storeName the chosen store name
 * @param date the date to append (defaults to now)
 * @return the generated list name
 */
export function defaultListName(storeName: string, date: Date = new Date()): string {
	const formatted = new Intl.DateTimeFormat(getCanonicalLocale(), { dateStyle: 'short' }).format(date)
	return `${storeName.trim()} ${formatted}`
}

/**
 * Parse a user-entered price into a number. Accepts both comma and dot as the
 * decimal separator. Returns null for blank or non-numeric input.
 *
 * @param text the raw input value
 * @return the parsed amount, or null when invalid
 */
export function parsePrice(text: string): number | null {
	const normalized = text.trim().replace(',', '.')
	if (normalized === '') {
		return null
	}
	const value = Number.parseFloat(normalized)
	return Number.isFinite(value) ? value : null
}

/**
 * Sum the total of all priced items (price × quantity). Items without a price
 * are ignored. The server-side `totalPrice` only covers checked items, so the
 * dialog computes the total from every item instead.
 *
 * @param items the list items to sum
 * @return the summed total (0 when there is nothing to sum)
 */
export function itemsTotal(items: readonly ListItem[]): number {
	return items.reduce((sum, item) => {
		if (item.price === null) {
			return sum
		}
		return sum + item.price * item.quantity
	}, 0)
}

/**
 * Find an unfinished list by name, ignoring case and surrounding whitespace.
 * This decides whether saving a purchase updates an existing list or creates a
 * new one.
 *
 * @param lists the candidate lists
 * @param name the name typed or selected by the user
 * @return the matching list, or null when none matches
 */
export function findNewListByName(lists: readonly ShoppingList[], name: string): ShoppingList | null {
	const normalized = name.trim().toLowerCase()
	if (normalized === '') {
		return null
	}
	return lists.find((list) => list.status === 'new' && list.name.trim().toLowerCase() === normalized) ?? null
}
