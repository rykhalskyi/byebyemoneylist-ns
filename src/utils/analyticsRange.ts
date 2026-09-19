import type { DashboardRange } from './dashboardRange.ts'

import { getCanonicalLocale } from './l10n.ts'

export interface MonthCursor {
	year: number
	month: number
}

export function currentMonth(now: Date = new Date()): MonthCursor {
	return { year: now.getFullYear(), month: now.getMonth() }
}

export function shiftMonth(cursor: MonthCursor, delta: number): MonthCursor {
	const date = new Date(cursor.year, cursor.month + delta, 1)
	return { year: date.getFullYear(), month: date.getMonth() }
}

export function monthRangeFor(cursor: MonthCursor): DashboardRange {
	const from = new Date(cursor.year, cursor.month, 1)
	const to = new Date(cursor.year, cursor.month + 1, 1)
	return { from: from.toISOString(), to: to.toISOString() }
}

export function formatMonthLabel(cursor: MonthCursor): string {
	return new Date(cursor.year, cursor.month, 1).toLocaleDateString(getCanonicalLocale(), {
		month: 'long',
		year: 'numeric',
	})
}
