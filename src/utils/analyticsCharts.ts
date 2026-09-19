import type { EChartsOption } from 'echarts'
import type { AnalyticsCategoryTotal, Category } from '../types.ts'

import { CATEGORY_COLOR_PALETTE } from '../constants/categoryColors.ts'
import { formatTotal } from './format.ts'

export interface DonutSlice {
	id: string | null
	label: string
	emoji: string | null
	value: number
	color: string
}

export interface BarRow {
	label: string
	value: number
}

interface TooltipParam {
	marker: string
	name: string
	value: number
	percent?: number
}

interface DataParam {
	data: { emoji?: string | null }
}

const FALLBACK_COLOR = CATEGORY_COLOR_PALETTE[0]

export function buildCategoryIndex(categories: Category[]): Map<string, Category> {
	return new Map(categories.map((category) => [category.id, category]))
}

function rootOf(category: Category, byId: Map<string, Category>): Category {
	let current = category
	const seen = new Set<string>([current.id])
	while (current.parentId !== null) {
		const parent = byId.get(current.parentId)
		if (parent === undefined || seen.has(parent.id)) {
			break
		}
		seen.add(parent.id)
		current = parent
	}
	return current
}

function finalize(buckets: Map<string, { category: Category | null, value: number }>, uncategorizedLabel: string): DonutSlice[] {
	return [...buckets.values()]
		.sort((a, b) => b.value - a.value)
		.map((entry, index) => ({
			id: entry.category?.id ?? null,
			label: entry.category?.name ?? uncategorizedLabel,
			emoji: entry.category?.emoji ?? null,
			value: entry.value,
			color: entry.category?.color || CATEGORY_COLOR_PALETTE[index % CATEGORY_COLOR_PALETTE.length],
		}))
}

/**
 * Roll up per-category list totals to their top-level ancestor categories.
 *
 * @param totals Per-category list totals
 * @param categories All user categories
 * @param uncategorizedLabel Label for the null-category bucket
 */
export function buildRootSlices(
	totals: AnalyticsCategoryTotal[],
	categories: Category[],
	uncategorizedLabel = 'Uncategorized',
): DonutSlice[] {
	const byId = buildCategoryIndex(categories)
	const buckets = new Map<string, { category: Category | null, value: number }>()

	for (const { categoryId, total } of totals) {
		if (total <= 0) {
			continue
		}
		const category = categoryId !== null ? byId.get(categoryId) ?? null : null
		const root = category !== null ? rootOf(category, byId) : null
		const key = root?.id ?? ''
		const bucket = buckets.get(key)
		if (bucket !== undefined) {
			bucket.value += total
		} else {
			buckets.set(key, { category: root, value: total })
		}
	}

	return finalize(buckets, uncategorizedLabel)
}

/**
 * Break down the totals that belong to `rootId` into its direct child categories.
 *
 * @param totals Per-category list totals
 * @param categories All user categories
 * @param rootId Category whose direct children are shown
 * @param uncategorizedLabel Label for the null-category bucket
 */
export function buildChildSlices(
	totals: AnalyticsCategoryTotal[],
	categories: Category[],
	rootId: string,
	uncategorizedLabel = 'Uncategorized',
): DonutSlice[] {
	const byId = buildCategoryIndex(categories)
	const root = byId.get(rootId)
	if (root === undefined) {
		return []
	}

	const directChild = (category: Category): Category => {
		let current = category
		while (current.parentId !== null && current.parentId !== rootId) {
			const parent = byId.get(current.parentId)
			if (parent === undefined) {
				return root
			}
			current = parent
		}
		return current
	}

	const buckets = new Map<string, { category: Category | null, value: number }>()
	for (const { categoryId, total } of totals) {
		if (total <= 0 || categoryId === null) {
			continue
		}
		const category = byId.get(categoryId)
		if (category === undefined || rootOf(category, byId).id !== rootId) {
			continue
		}
		const child = directChild(category)
		const bucket = buckets.get(child.id)
		if (bucket !== undefined) {
			bucket.value += total
		} else {
			buckets.set(child.id, { category: child, value: total })
		}
	}

	return finalize(buckets, uncategorizedLabel)
}

/**
 * Donut option with category-colored slices and emoji-only external labels.
 *
 * @param slices Donut slices to render
 */
export function buildDonutOption(slices: DonutSlice[]): EChartsOption {
	const total = slices.reduce((sum, slice) => sum + slice.value, 0)

	return {
		tooltip: {
			trigger: 'item',
			formatter: (params: unknown) => {
				const param = params as TooltipParam
				return `${param.marker} ${param.name}<br/>${formatTotal(param.value)} (${Math.round(param.percent ?? 0)}%)`
			},
		},
		series: [
			{
				type: 'pie',
				radius: ['46%', '68%'],
				center: ['50%', '50%'],
				avoidLabelOverlap: true,
				itemStyle: { borderWidth: 2, borderColor: 'transparent', borderRadius: 3 },
				label: {
					show: true,
					position: 'outside',
					fontSize: 18,
					formatter: (params: unknown) => (params as DataParam).data.emoji ?? '',
				},
				labelLine: { show: true, smooth: true, length: 12, length2: 10 },
				emphasis: { scale: true, scaleSize: 8 },
				data: slices.map((slice) => ({
					name: slice.label,
					value: slice.value,
					id: slice.id ?? undefined,
					emoji: slice.emoji,
					itemStyle: { color: slice.color },
					label: { show: slice.emoji !== null && slice.emoji !== '' && total > 0 && (slice.value / total) * 100 > 2 },
				})),
			},
		],
	}
}

/**
 * Horizontal bar option; row labels double as the legend and the sum sits on the bar.
 *
 * @param rows Rows to plot as horizontal bars
 */
export function buildBarOption(rows: BarRow[]): EChartsOption {
	return {
		grid: { left: 8, right: 64, top: 8, bottom: 8, containLabel: true },
		tooltip: {
			trigger: 'item',
			formatter: (params: unknown) => {
				const param = params as TooltipParam
				return `${param.marker} ${param.name}<br/>${formatTotal(param.value)}`
			},
		},
		xAxis: {
			type: 'value',
			axisLabel: { formatter: (value: unknown) => formatTotal(Number(value)) },
		},
		yAxis: {
			type: 'category',
			inverse: true,
			data: rows.map((row) => row.label),
			axisTick: { show: false },
			axisLine: { show: false },
		},
		series: [
			{
				type: 'bar',
				barMaxWidth: 26,
				data: rows.map((row, index) => ({
					value: row.value,
					itemStyle: { color: CATEGORY_COLOR_PALETTE[index % CATEGORY_COLOR_PALETTE.length] ?? FALLBACK_COLOR },
				})),
				label: {
					show: true,
					position: 'right',
					formatter: (params: unknown) => formatTotal(Number((params as TooltipParam).value)),
				},
			},
		],
	}
}
