import type { AnalyticsOverview, Category, Store } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AccountStateCard from '../../../src/components/analytics/AccountStateCard.vue'
import MonthPicker from '../../../src/components/analytics/MonthPicker.vue'
import TopBarChart from '../../../src/components/analytics/TopBarChart.vue'
import Analytics from '../../../src/views/Analytics.vue'
import { fetchAnalyticsOverview } from '../../../src/services/analyticsApi.ts'
import { fetchCategories, fetchStores } from '../../../src/services/listsApi.ts'

vi.mock('vue-echarts', () => ({
	default: { name: 'VChart', props: ['option'], template: '<div />' },
}))
vi.mock('../../../src/services/analyticsApi.ts', () => ({ fetchAnalyticsOverview: vi.fn() }))
vi.mock('../../../src/services/listsApi.ts', () => ({ fetchCategories: vi.fn(), fetchStores: vi.fn() }))

function category(overrides: Partial<Category> = {}): Category {
	return {
		id: 'food',
		name: 'Food',
		color: '#ff0000',
		emoji: null,
		parentId: null,
		income: false,
		...overrides,
	}
}

function store(overrides: Partial<Store> = {}): Store {
	return { id: 'store-1', name: 'Aldi', address: null, categoryIds: [], ...overrides }
}

function overview(overrides: Partial<AnalyticsOverview> = {}): AnalyticsOverview {
	return {
		totalSpent: 30,
		totalIncome: 100,
		byCategory: [
			{ categoryId: 'food', total: 20 },
			{ categoryId: null, total: 10 },
		],
		byStore: [{ storeId: 'store-1', total: 30 }],
		byList: [{ listId: 'list-1', name: 'Weekly', total: 30 }],
		...overrides,
	}
}

describe('Analytics.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('renders the balance card, category legend and top breakdowns', async () => {
		vi.mocked(fetchCategories).mockResolvedValue([category()])
		vi.mocked(fetchStores).mockResolvedValue([store()])
		vi.mocked(fetchAnalyticsOverview).mockResolvedValue(overview())

		const wrapper = mount(Analytics)
		await flushPromises()

		expect(wrapper.findComponent(AccountStateCard).props('spent')).toBe(30)
		expect(wrapper.findComponent(AccountStateCard).props('income')).toBe(100)
		expect(wrapper.text()).toContain('Food')

		const charts = wrapper.findAllComponents(TopBarChart)
		expect(charts).toHaveLength(2)
		expect(charts[0].props('rows')[0]).toEqual({ label: 'Aldi', value: 30 })
		expect(charts[1].props('rows')[0]).toEqual({ label: 'Weekly', value: 30 })
	})

	it('shows the empty state when there are no expenses', async () => {
		vi.mocked(fetchCategories).mockResolvedValue([])
		vi.mocked(fetchStores).mockResolvedValue([])
		vi.mocked(fetchAnalyticsOverview).mockResolvedValue(overview({
			totalSpent: 0,
			byCategory: [],
			byStore: [],
			byList: [],
		}))

		const wrapper = mount(Analytics)
		await flushPromises()

		expect(wrapper.text()).toContain('No expenses this month')
	})

	it('reloads when navigating to the previous month', async () => {
		vi.mocked(fetchCategories).mockResolvedValue([category()])
		vi.mocked(fetchStores).mockResolvedValue([store()])
		vi.mocked(fetchAnalyticsOverview).mockResolvedValue(overview())

		const wrapper = mount(Analytics)
		await flushPromises()
		expect(fetchAnalyticsOverview).toHaveBeenCalledTimes(2)

		wrapper.findComponent(MonthPicker).vm.$emit('previous')
		await flushPromises()

		expect(fetchAnalyticsOverview).toHaveBeenCalledTimes(4)
	})
})
