import type { ListStatus, ShoppingList } from '../../../src/types.ts'

import { describe, expect, it } from 'vitest'
import { finishedTotal, groupListsByMonth, UNKNOWN_DATE_KEY } from '../../../src/utils/listGroups.ts'

const BASE: ShoppingList = {
	id: 'list',
	name: 'List',
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
}

function list(id: string, createdAt: string | null, overrides: Partial<ShoppingList> = {}): ShoppingList {
	return { ...BASE, id, createdAt, ...overrides }
}

function status(status: ListStatus, finalTotal: number | null): Partial<ShoppingList> {
	return { status, finalTotal }
}

describe('finishedTotal', () => {
	it('sums only the final total of finished lists', () => {
		const lists = [
			list('a', '2026-09-01T10:00:00Z', status('finished', 10.5)),
			list('b', '2026-09-02T10:00:00Z', status('finished', 4.5)),
			list('c', '2026-09-03T10:00:00Z', status('new', null)),
		]
		expect(finishedTotal(lists)).toBe(15)
	})

	it('ignores finished lists without a stored final total', () => {
		const lists = [
			list('a', '2026-09-01T10:00:00Z', status('finished', null)),
			list('b', '2026-09-02T10:00:00Z', status('finished', 7)),
		]
		expect(finishedTotal(lists)).toBe(7)
	})

	it('returns null when nothing qualifies', () => {
		expect(finishedTotal([list('a', '2026-09-01T10:00:00Z')])).toBeNull()
		expect(finishedTotal([])).toBeNull()
	})
})

describe('groupListsByMonth', () => {
	it('groups lists by year and month, newest first', () => {
		const groups = groupListsByMonth([
			list('old', '2025-03-05T10:00:00Z'),
			list('new', '2026-09-10T10:00:00Z'),
			list('mid', '2026-01-20T10:00:00Z'),
		])

		expect(groups.map((group) => group.year)).toEqual([2026, 2025])
		expect(groups[0].months.map((month) => month.month)).toEqual([9, 1])
		expect(groups[0].months[0].lists.map((item) => item.id)).toEqual(['new'])
		expect(groups[0].months[1].lists.map((item) => item.id)).toEqual(['mid'])
	})

	it('sorts lists within a month by date descending', () => {
		const groups = groupListsByMonth([
			list('first', '2026-09-01T10:00:00Z'),
			list('third', '2026-09-20T10:00:00Z'),
			list('second', '2026-09-10T10:00:00Z'),
		])
		expect(groups[0].months[0].lists.map((item) => item.id)).toEqual(['third', 'second', 'first'])
	})

	it('sums finished final totals per month and per year', () => {
		const groups = groupListsByMonth([
			list('a', '2026-09-01T10:00:00Z', status('finished', 10)),
			list('b', '2026-09-02T10:00:00Z', status('finished', 5)),
			list('c', '2026-08-01T10:00:00Z', status('finished', 20)),
			list('d', '2026-08-02T10:00:00Z', status('new', null)),
		])

		expect(groups[0].months[0].total).toBe(15)
		expect(groups[0].months[1].total).toBe(20)
		expect(groups[0].total).toBe(35)
	})

	it('returns a null total when a group has no finished priced list', () => {
		const groups = groupListsByMonth([list('a', '2026-09-01T10:00:00Z')])
		expect(groups[0].total).toBeNull()
		expect(groups[0].months[0].total).toBeNull()
	})

	it('places invalid and missing dates in a single unknown group, sorted last', () => {
		const groups = groupListsByMonth([
			list('undated', null),
			list('bad', 'not-a-date'),
			list('dated', '2026-09-01T10:00:00Z'),
		])

		expect(groups.map((group) => group.key)).toEqual(['2026', UNKNOWN_DATE_KEY])
		const unknown = groups[1]
		expect(unknown.year).toBeNull()
		expect(unknown.months).toHaveLength(1)
		expect(unknown.months[0].month).toBeNull()
		expect(unknown.months[0].lists.map((item) => item.id)).toEqual(['undated', 'bad'])
	})
})
