import type { Category } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AddWidgetDialog from '../../../src/components/dashboard/AddWidgetDialog.vue'
import Dashboard from '../../../src/views/Dashboard.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchCategories: vi.fn(),
}))

function category(overrides: Partial<Category> = {}): Category {
	return {
		id: 'cat-1',
		name: 'Groceries',
		color: null,
		emoji: null,
		parentId: null,
		income: false,
		...overrides,
	}
}

describe('Dashboard', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		window.localStorage.clear()
	})

	it('offers only expense categories to the add-widget dialog', async () => {
		vi.mocked(api.fetchCategories).mockResolvedValue([
			category({ id: 'cat-1', name: 'Groceries' }),
			category({ id: 'cat-2', name: 'Salary', income: true }),
		])

		const wrapper = mount(Dashboard)
		await flushPromises()

		const options = wrapper.findComponent(AddWidgetDialog).props('categories')
		expect(options.map((option: Category) => option.id)).toEqual(['cat-1'])
	})
})
