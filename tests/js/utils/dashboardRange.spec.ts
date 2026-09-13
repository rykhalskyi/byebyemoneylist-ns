import { describe, expect, it } from 'vitest'
import { monthRange, todayRange } from '../../../src/utils/dashboardRange.ts'

describe('dashboardRange', () => {
	it('spans the local day', () => {
		const { from, to } = todayRange(new Date(2026, 8, 13, 15, 30, 45))

		expect(new Date(from).getDate()).toBe(13)
		expect(new Date(from).getHours()).toBe(0)
		expect(new Date(from).getMinutes()).toBe(0)
		expect(new Date(to).getDate()).toBe(14)
		expect(new Date(to).getHours()).toBe(0)
	})

	it('spans the local month', () => {
		const { from, to } = monthRange(new Date(2026, 8, 13, 15, 30))

		expect(new Date(from).getMonth()).toBe(8)
		expect(new Date(from).getDate()).toBe(1)
		expect(new Date(to).getMonth()).toBe(9)
		expect(new Date(to).getDate()).toBe(1)
	})

	it('rolls the month over into the next year', () => {
		const { to } = monthRange(new Date(2026, 11, 31, 23, 59))

		expect(new Date(to).getFullYear()).toBe(2027)
		expect(new Date(to).getMonth()).toBe(0)
		expect(new Date(to).getDate()).toBe(1)
	})
})
