import { describe, expect, it } from 'vitest'
import { groupByOwner } from '../../../src/utils/catalogGroups.ts'

interface Item {
	id: string
	shared?: boolean
	owner?: string
}

describe('groupByOwner', () => {
	it('separates own items from per-owner shared groups', () => {
		const items: Item[] = [
			{ id: 'a' },
			{ id: 'b', shared: true, owner: 'alice' },
			{ id: 'c', shared: true, owner: 'bob' },
			{ id: 'd', shared: true, owner: 'alice' },
		]

		const { mine, shared } = groupByOwner(items)

		expect(mine.map((item) => item.id)).toEqual(['a'])
		expect(shared.map((group) => group.owner)).toEqual(['alice', 'bob'])
		expect(shared[0].items.map((item) => item.id)).toEqual(['b', 'd'])
	})

	it('buckets a missing owner under the empty string', () => {
		const { shared } = groupByOwner([{ id: 'x', shared: true }])

		expect(shared).toHaveLength(1)
		expect(shared[0].owner).toBe('')
	})

	it('returns no shared groups when everything is owned', () => {
		const { mine, shared } = groupByOwner([{ id: 'a' }, { id: 'b' }])

		expect(mine).toHaveLength(2)
		expect(shared).toEqual([])
	})
})
