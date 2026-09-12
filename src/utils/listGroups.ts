import type { ShoppingList } from '../types.ts'

export interface MonthGroup {
	key: string
	year: number | null
	month: number | null
	lists: ShoppingList[]
	total: number | null
}

export interface YearGroup {
	key: string
	year: number | null
	months: MonthGroup[]
	total: number | null
}

export const UNKNOWN_DATE_KEY = 'unknown'

/**
 * Sum the final price of finished lists. Returns null when the group contains
 * no finished list with a stored final total, so empty groups render no amount.
 *
 * @param lists the lists to sum
 * @return the summed final total, or null when there is nothing to sum
 */
export function finishedTotal(lists: readonly ShoppingList[]): number | null {
	let total = 0
	let hasFinal = false
	for (const list of lists) {
		if (list.status === 'finished' && list.finalTotal !== null) {
			total += list.finalTotal
			hasFinal = true
		}
	}
	return hasFinal ? total : null
}

function parseTime(iso: string | null): number | null {
	if (iso === null) {
		return null
	}
	const time = new Date(iso).getTime()
	return Number.isNaN(time) ? null : time
}

function compareDesc(a: number | null, b: number | null): number {
	if (a === null && b === null) {
		return 0
	}
	if (a === null) {
		return 1
	}
	if (b === null) {
		return -1
	}
	return b - a
}

function sortLists(lists: readonly ShoppingList[]): ShoppingList[] {
	return [...lists].sort((a, b) => compareDesc(parseTime(a.createdAt), parseTime(b.createdAt)))
}

/**
 * Group lists into years and months based on their creation date, newest
 * first. Lists without a valid date land in a single "unknown" group that is
 * always sorted last.
 *
 * @param lists the lists to group
 * @return the year groups, each containing its month groups
 */
export function groupListsByMonth(lists: readonly ShoppingList[]): YearGroup[] {
	interface MutableMonth {
		key: string
		year: number | null
		month: number | null
		lists: ShoppingList[]
	}

	interface MutableYear {
		key: string
		year: number | null
		months: Map<string, MutableMonth>
		lists: ShoppingList[]
	}

	const years = new Map<string, MutableYear>()

	for (const list of lists) {
		const time = parseTime(list.createdAt)
		const date = time === null ? null : new Date(time)
		const year = date === null ? null : date.getFullYear()
		const month = date === null ? null : date.getMonth() + 1
		const yearKey = year === null ? UNKNOWN_DATE_KEY : String(year)
		const monthKey = month === null ? UNKNOWN_DATE_KEY : String(month)

		let yearGroup = years.get(yearKey)
		if (yearGroup === undefined) {
			yearGroup = { key: yearKey, year, months: new Map(), lists: [] }
			years.set(yearKey, yearGroup)
		}
		yearGroup.lists.push(list)

		let monthGroup = yearGroup.months.get(monthKey)
		if (monthGroup === undefined) {
			monthGroup = { key: `${yearKey}-${monthKey}`, year, month, lists: [] }
			yearGroup.months.set(monthKey, monthGroup)
		}
		monthGroup.lists.push(list)
	}

	return [...years.values()]
		.map((yearGroup): YearGroup => ({
			key: yearGroup.key,
			year: yearGroup.year,
			months: [...yearGroup.months.values()]
				.map((monthGroup): MonthGroup => ({
					key: monthGroup.key,
					year: monthGroup.year,
					month: monthGroup.month,
					lists: sortLists(monthGroup.lists),
					total: finishedTotal(monthGroup.lists),
				}))
				.sort((a, b) => compareDesc(a.month, b.month)),
			total: finishedTotal(yearGroup.lists),
		}))
		.sort((a, b) => compareDesc(a.year, b.year))
}
