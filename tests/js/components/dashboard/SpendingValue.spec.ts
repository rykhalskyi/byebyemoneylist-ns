import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SpendingValue from '../../../../src/components/dashboard/widgets/SpendingValue.vue'
import * as api from '../../../../src/services/dashboardApi.ts'

vi.mock('../../../../src/services/dashboardApi.ts', () => ({
	fetchSpending: vi.fn(),
}))

describe('SpendingValue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('renders the formatted total for the given range', async () => {
		vi.mocked(api.fetchSpending).mockResolvedValue({ total: 12.5, byCategory: [] })

		const wrapper = mount(SpendingValue, { props: { from: 'from-iso', to: 'to-iso' } })
		await flushPromises()

		expect(wrapper.text()).toContain('12.50')
		expect(api.fetchSpending).toHaveBeenCalledWith({ from: 'from-iso', to: 'to-iso', categoryId: null })
	})

	it('shows an error and retries', async () => {
		vi.mocked(api.fetchSpending).mockRejectedValueOnce(new Error('offline'))

		const wrapper = mount(SpendingValue, { props: { from: 'a', to: 'b' } })
		await flushPromises()
		expect(wrapper.text()).toContain('Failed to load the total.')

		vi.mocked(api.fetchSpending).mockResolvedValueOnce({ total: 3, byCategory: [] })
		await wrapper.findAll('button').find((button) => button.text() === 'Try again')!.trigger('click')
		await flushPromises()

		expect(wrapper.text()).toContain('3.00')
	})
})
