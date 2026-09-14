import type { ShoppingList } from '../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PurchaseDialog from '../../../src/components/PurchaseDialog.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	createList: vi.fn(),
	updateList: vi.fn(),
	createStore: vi.fn(),
	deleteList: vi.fn(),
	fetchListItems: vi.fn(),
	uploadListReceipt: vi.fn(),
	fetchListReceipt: vi.fn(),
	deleteListReceipt: vi.fn(),
}))

function list(overrides: Partial<ShoppingList> = {}): ShoppingList {
	return {
		id: 'l1',
		name: 'Weekly groceries',
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
		...overrides,
	}
}

async function render(mode: 'manual' | 'scan', lists: ShoppingList[] = []) {
	const wrapper = mount(PurchaseDialog, {
		props: { open: false, lists, stores: [], categories: [], initialMode: mode },
		global: { stubs: { teleport: true } },
	})
	await wrapper.setProps({ open: true })
	await nextTick()
	return wrapper
}

async function attachFile(wrapper: Awaited<ReturnType<typeof render>>, file: File) {
	const input = wrapper.find('input[type="file"]')
	Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
	await input.trigger('change')
	await nextTick()
}

function imageFile(name = 'receipt.png', type = 'image/png'): File {
	return new File(['data'], name, { type })
}

enableAutoUnmount(afterEach)

describe('PurchaseDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		Object.defineProperty(URL, 'createObjectURL', { configurable: true, writable: true, value: vi.fn(() => 'blob:receipt') })
		Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, writable: true, value: vi.fn() })
	})

	it('hides the receipt field in manual input mode', async () => {
		const wrapper = await render('manual')
		expect(wrapper.find('input[type="file"]').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('Attach receipt')
	})

	it('shows the receipt field in scan receipt mode', async () => {
		const wrapper = await render('scan')
		expect(wrapper.find('input[type="file"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Attach receipt')
	})

	it('rejects unsupported file types', async () => {
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile('receipt.txt', 'text/plain'))

		expect(wrapper.text()).toContain('Unsupported image type')
		expect(wrapper.find('img').exists()).toBe(false)
	})

	it('rejects images over the size limit', async () => {
		const wrapper = await render('scan')
		const file = imageFile()
		Object.defineProperty(file, 'size', { value: 5 * 1024 * 1024 })
		await attachFile(wrapper, file)

		expect(wrapper.text()).toContain('The receipt exceeds the 4 MB limit.')
		expect(wrapper.find('img').exists()).toBe(false)
	})

	it('previews a valid receipt and allows removing it', async () => {
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile())

		expect(wrapper.find('img').attributes('src')).toBe('blob:receipt')
		expect(wrapper.text()).toContain('Replace')
		expect(wrapper.text()).toContain('Remove')

		const remove = wrapper.findAll('button').find((button) => button.text() === 'Remove')!
		await remove.trigger('click')
		await nextTick()

		expect(wrapper.find('img').exists()).toBe(false)
		expect(wrapper.text()).toContain('Attach receipt')
	})

	it('requires a list name before saving in scan mode', async () => {
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile())
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.uploadListReceipt).not.toHaveBeenCalled()
		expect(wrapper.emitted('saved')).toBeFalsy()
		expect(wrapper.text()).toContain('The list name is required.')
	})

	it('uploads the receipt to the selected list in scan mode', async () => {
		vi.mocked(api.uploadListReceipt).mockResolvedValue({ dataUrl: 'data:image/png;base64,AA==', mime: 'image/png' })
		const wrapper = await render('scan', [list()])

		await wrapper.findComponent(NcSelect).vm.$emit('update:modelValue', list())
		await nextTick()
		await attachFile(wrapper, imageFile())
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.uploadListReceipt).toHaveBeenCalledWith('l1', expect.any(File))
		expect(wrapper.emitted('saved')?.[0]?.[0]).toMatchObject({ id: 'l1', hasReceipt: true })
		expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
	})

	it('creates a list for a typed name before uploading the receipt', async () => {
		const created = list({ id: 'new-list', name: 'Groceries' })
		vi.mocked(api.createList).mockResolvedValue(created)
		vi.mocked(api.uploadListReceipt).mockResolvedValue({ dataUrl: 'data:image/png;base64,AA==', mime: 'image/png' })
		const wrapper = await render('scan')

		await wrapper.findComponent(NcSelect).vm.$emit('update:modelValue', 'Groceries')
		await nextTick()
		await attachFile(wrapper, imageFile())
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.createList).toHaveBeenCalledWith({ name: 'Groceries' })
		expect(api.uploadListReceipt).toHaveBeenCalledWith('new-list', expect.any(File))
	})

	it('rolls back a newly created list when the receipt upload fails', async () => {
		const created = list({ id: 'new-list', name: 'Groceries' })
		vi.mocked(api.createList).mockResolvedValue(created)
		vi.mocked(api.uploadListReceipt).mockRejectedValue(new Error('nope'))
		vi.mocked(api.deleteList).mockResolvedValue()
		const wrapper = await render('scan')

		await wrapper.findComponent(NcSelect).vm.$emit('update:modelValue', 'Groceries')
		await nextTick()
		await attachFile(wrapper, imageFile())
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.deleteList).toHaveBeenCalledWith('new-list')
		expect(wrapper.emitted('saved')).toBeFalsy()
		expect(wrapper.text()).toContain('Failed to upload the receipt')
	})
})
