import { describe, expect, it } from 'vitest'
import { currentMonth, formatMonthLabel, monthRangeFor, shiftMonth } from '../../../src/utils/analyticsRange.ts'

describe('analyticsRange', () => {
	it('shifts months across year boundaries', () => {
		expect(shiftMonth({ year: 2026, month: 0 }, -1)).toEqual({ year: 2025, month: 11 })
		expect(shiftMonth({ year: 2026, month: 11 }, 1)).toEqual({ year: 2027, month: 0 })
		expect(shiftMonth({ year: 2026, month: 5 }, 0)).toEqual({ year: 2026, month: 5 })
	})

	it('returns the local month range as ISO instants', () => {
		const from = new Date(2026, 8, 1)
		const to = new Date(2026, 9, 1)

		expect(monthRangeFor({ year: 2026, month: 8 })).toEqual({
			from: from.toISOString(),
			to: to.toISOString(),
		})
	})

	it('labels the month with its year', () => {
		expect(formatMonthLabel({ year: 2026, month: 8 })).toContain('2026')
	})

	it('derives the current month from the given date', () => {
		expect(currentMonth(new Date(2026, 0, 15))).toEqual({ year: 2026, month: 0 })
	})
})
