import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import ConfirmDialog from '../../../src/components/ConfirmDialog.vue'
import ReceiptViewDialog from '../../../src/components/ReceiptViewDialog.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchListReceipt: vi.fn(),
	deleteListReceipt: vi.fn(),
}))

async function render() {
	const wrapper = mount(ReceiptViewDialog, {
		props: { open: false, listId: 'l1', listName: 'Weekly groceries' },
		global: { stubs: { teleport: true } },
	})
	await wrapper.setProps({ open: true })
	await flushPromises()
	await nextTick()
	return wrapper
}

enableAutoUnmount(afterEach)

describe('ReceiptViewDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('loads and shows the receipt image', async () => {
		vi.mocked(api.fetchListReceipt).mockResolvedValue({ dataUrl: 'data:image/jpeg;base64,AA==', mime: 'image/jpeg' })
		const wrapper = await render()

		expect(api.fetchListReceipt).toHaveBeenCalledWith('l1')
		expect(wrapper.find('img').attributes('src')).toBe('data:image/jpeg;base64,AA==')
	})

	it('shows an empty state when the list has no receipt', async () => {
		vi.mocked(api.fetchListReceipt).mockResolvedValue(null)
		const wrapper = await render()

		expect(wrapper.text()).toContain('No receipt')
		expect(wrapper.find('img').exists()).toBe(false)
	})

	it('shows an error when the receipt cannot be loaded', async () => {
		vi.mocked(api.fetchListReceipt).mockRejectedValue(new Error('boom'))
		const wrapper = await render()

		expect(wrapper.text()).toContain('Failed to load the receipt')
	})

	it('deletes the receipt after confirmation', async () => {
		vi.mocked(api.fetchListReceipt).mockResolvedValue({ dataUrl: 'data:image/jpeg;base64,AA==', mime: 'image/jpeg' })
		vi.mocked(api.deleteListReceipt).mockResolvedValue()
		const wrapper = await render()

		const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Delete')
		expect(deleteButton).toBeDefined()
		await deleteButton!.trigger('click')
		await nextTick()

		expect(wrapper.findComponent(ConfirmDialog).props('open')).toBe(true)

		await wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')
		await flushPromises()

		expect(api.deleteListReceipt).toHaveBeenCalledWith('l1')
		expect(wrapper.emitted('deleted')?.[0]).toEqual(['l1'])
		expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
	})

	it('keeps the receipt when the confirmation is dismissed', async () => {
		vi.mocked(api.fetchListReceipt).mockResolvedValue({ dataUrl: 'data:image/jpeg;base64,AA==', mime: 'image/jpeg' })
		const wrapper = await render()

		const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Delete')!
		await deleteButton.trigger('click')
		await nextTick()
		await wrapper.findComponent(ConfirmDialog).vm.$emit('update:open', false)
		await nextTick()

		expect(api.deleteListReceipt).not.toHaveBeenCalled()
		expect(wrapper.find('img').exists()).toBe(true)
	})
})
