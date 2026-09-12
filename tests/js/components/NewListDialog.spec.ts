import type { Category, Store } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NewListDialog from '../../../src/components/NewListDialog.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchStores: vi.fn(),
	fetchCategories: vi.fn(),
	createList: vi.fn(),
}))

const store: Store = { id: 's1', name: 'Aldi', address: null, categoryIds: [] }
const categories: Category[] = [
	{ id: 'c1', name: 'Salary', color: null, emoji: null, parentId: null, income: true },
	{ id: 'c2', name: 'Food', color: null, emoji: null, parentId: null, income: false },
]

async function render(isIncome: boolean) {
	vi.mocked(api.fetchStores).mockResolvedValue([store])
	vi.mocked(api.fetchCategories).mockResolvedValue(categories)
	const wrapper = mount(NewListDialog, {
		props: { open: true, isIncome },
		global: { stubs: { teleport: true } },
	})
	await flushPromises()
	await flushPromises()
	return wrapper
}

describe('NewListDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('hides the store selector and subscription toggle for income lists', async () => {
		const wrapper = await render(true)
		expect(wrapper.text()).not.toContain('Store')
		expect(wrapper.text()).not.toContain('Subscription')
		expect(wrapper.text()).toContain('Recurring')
	})

	it('shows the store selector and subscription toggle for regular lists', async () => {
		const wrapper = await render(false)
		expect(wrapper.text()).toContain('Store')
		expect(wrapper.text()).toContain('Subscription')
	})
})
