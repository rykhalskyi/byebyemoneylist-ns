import type { Category } from '../../../src/types.ts'

import { describe, expect, it } from 'vitest'
import { CATEGORY_COLOR_PALETTE } from '../../../src/constants/categoryColors.ts'
import { buildBarOption, buildChildSlices, buildDonutOption, buildRootSlices } from '../../../src/utils/analyticsCharts.ts'

interface DonutShape {
	series: Array<{
		data: Array<{
			name: string
			value: number
			itemStyle: { color: string }
			label: { show: boolean }
		}>
	}>
}

interface BarShape {
	yAxis: { data: string[] }
	series: Array<{ data: Array<{ value: number, itemStyle: { color: string } }> }>
}

function category(overrides: Partial<Category> = {}): Category {
	return {
		id: 'cat',
		name: 'Cat',
		color: null,
		emoji: null,
		parentId: null,
		income: false,
		...overrides,
	}
}

const categories = [
	category({ id: 'food', name: 'Food', color: '#111111', emoji: '🍔' }),
	category({ id: 'fruit', name: 'Fruit', parentId: 'food' }),
	category({ id: 'apple', name: 'Apple', parentId: 'fruit' }),
	category({ id: 'veg', name: 'Veg', parentId: 'food' }),
	category({ id: 'travel', name: 'Travel', color: '#222222' }),
]

describe('buildRootSlices', () => {
	it('rolls child categories up to their top-level ancestor', () => {
		const slices = buildRootSlices([
			{ categoryId: 'apple', total: 10 },
			{ categoryId: 'veg', total: 5 },
			{ categoryId: 'travel', total: 20 },
		], categories)

		expect(slices.map((slice) => [slice.id, slice.value])).toEqual([
			['travel', 20],
			['food', 15],
		])
		expect(slices[1].emoji).toBe('🍔')
	})

	it('buckets null and unknown categories under the uncategorized label', () => {
		const slices = buildRootSlices([
			{ categoryId: null, total: 3 },
			{ categoryId: 'missing', total: 7 },
		], categories, 'Unknown')

		expect(slices).toEqual([
			{ id: null, label: 'Unknown', emoji: null, value: 10, color: CATEGORY_COLOR_PALETTE[0] },
		])
	})

	it('falls back to the palette when a category has no color', () => {
		const standalone = category({ id: 'misc', name: 'Misc' })
		const slices = buildRootSlices([{ categoryId: 'misc', total: 1 }], [standalone])

		expect(slices[0].color).toBe(CATEGORY_COLOR_PALETTE[0])
	})
})

describe('buildChildSlices', () => {
	it('groups descendants into their direct child of the root', () => {
		const slices = buildChildSlices([
			{ categoryId: 'apple', total: 10 },
			{ categoryId: 'veg', total: 5 },
			{ categoryId: 'food', total: 2 },
			{ categoryId: 'travel', total: 20 },
		], categories, 'food')

		expect(slices.map((slice) => [slice.id, slice.value])).toEqual([
			['fruit', 10],
			['veg', 5],
			['food', 2],
		])
	})

	it('returns nothing for an unknown root', () => {
		expect(buildChildSlices([{ categoryId: 'fruit', total: 10 }], categories, 'nope')).toEqual([])
	})
})

describe('buildDonutOption', () => {
	it('shows external emoji labels only above the 2% threshold', () => {
		const option = buildDonutOption([
			{ id: 'a', label: 'A', emoji: '🍎', value: 9, color: '#f00' },
			{ id: 'b', label: 'B', emoji: null, value: 0.9, color: '#0f0' },
			{ id: 'c', label: 'C', emoji: '🍏', value: 0.1, color: '#00f' },
		]) as unknown as DonutShape

		const data = option.series[0].data
		expect(data[0].label.show).toBe(true)
		expect(data[1].label.show).toBe(false)
		expect(data[2].label.show).toBe(false)
		expect(data[0].itemStyle.color).toBe('#f00')
	})
})

describe('buildBarOption', () => {
	it('maps rows to a horizontal bar series', () => {
		const option = buildBarOption([
			{ label: 'Store A', value: 10 },
			{ label: 'Store B', value: 5 },
		]) as unknown as BarShape

		expect(option.yAxis.data).toEqual(['Store A', 'Store B'])
		expect(option.series[0].data.map((entry) => entry.value)).toEqual([10, 5])
		expect(option.series[0].data[0].itemStyle.color).toBe(CATEGORY_COLOR_PALETTE[0])
	})
})
