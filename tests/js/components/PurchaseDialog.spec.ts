import type { LlmProfile, ScannedReceipt, ShoppingList } from '../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PurchaseDialog from '../../../src/components/PurchaseDialog.vue'
import * as llmApi from '../../../src/services/llmApi.ts'
import * as receiptApi from '../../../src/services/receiptApi.ts'
import { normalizeReceiptImage } from '../../../src/utils/image.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	createList: vi.fn(),
	updateList: vi.fn(),
	createStore: vi.fn(),
	fetchListItems: vi.fn(),
}))

vi.mock('../../../src/services/llmApi.ts', () => ({
	fetchLlmProfiles: vi.fn(),
}))

vi.mock('../../../src/services/receiptApi.ts', () => ({
	scanReceipt: vi.fn(),
	commitReceipt: vi.fn(),
}))

vi.mock('../../../src/utils/image.ts', () => ({
	normalizeReceiptImage: vi.fn(),
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

function scanResult(overrides: Partial<ScannedReceipt> = {}): ScannedReceipt {
	return {
		storeName: 'Aldi',
		storeAddress: 'Main St 1',
		storeId: null,
		totalSum: 3,
		items: [
			{
				name: 'Milk',
				quantity: 2,
				price: 1.5,
				discount: null,
				isCoupon: false,
				productId: null,
				categoryId: null,
				categoryName: 'Dairy',
			},
		],
		profile: { id: 'p1', name: 'DeepSeek', provider: 'deepseek' },
		...overrides,
	}
}

function axiosError(status: number): Error {
	return Object.assign(new Error('request failed'), { isAxiosError: true, response: { status } })
}

function profile(isActive: boolean): LlmProfile {
	return {
		id: 'p1',
		name: 'DeepSeek',
		provider: 'deepseek',
		apiKeyMasked: '••••••••1234',
		model: null,
		connectTimeoutSeconds: 30,
		readTimeoutSeconds: 60,
		maxTokens: 2048,
		isActive,
		createdAt: '2026-09-14T00:00:00+00:00',
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

async function clickButton(wrapper: Awaited<ReturnType<typeof render>>, label: string) {
	const button = wrapper.findAll('button').find((candidate) => candidate.text() === label)
	expect(button, `button "${label}"`).toBeDefined()
	await button!.trigger('click')
	await flushPromises()
}

async function selectListName(wrapper: Awaited<ReturnType<typeof render>>, name: string) {
	await wrapper.findComponent(NcSelect).vm.$emit('update:modelValue', name)
	await nextTick()
}

enableAutoUnmount(afterEach)

describe('PurchaseDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		Object.defineProperty(URL, 'createObjectURL', { configurable: true, writable: true, value: vi.fn(() => 'blob:receipt') })
		Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, writable: true, value: vi.fn() })
		vi.mocked(normalizeReceiptImage).mockResolvedValue(new Blob(['jpeg'], { type: 'image/jpeg' }))
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([profile(true)])
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
		await clickButton(wrapper, 'Remove')

		expect(wrapper.find('img').exists()).toBe(false)
		expect(wrapper.text()).toContain('Attach receipt')
	})

	it('scans the receipt and shows the result', async () => {
		vi.mocked(receiptApi.scanReceipt).mockResolvedValue(scanResult())
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile())

		await clickButton(wrapper, 'Scan receipt')

		expect(normalizeReceiptImage).toHaveBeenCalledWith(expect.any(File))
		expect(receiptApi.scanReceipt).toHaveBeenCalledWith(expect.any(Blob))
		expect(wrapper.text()).toContain('Scanned receipt')
		expect(wrapper.text()).toContain('Aldi')
		expect(wrapper.text()).toContain('Milk')
	})

	it('shows a hint when there is no active LLM profile', async () => {
		vi.mocked(receiptApi.scanReceipt).mockRejectedValue(axiosError(409))
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile())

		await clickButton(wrapper, 'Scan receipt')

		expect(wrapper.text()).toContain('No active LLM profile')
	})

	it('shows a generic error when scanning fails', async () => {
		vi.mocked(receiptApi.scanReceipt).mockRejectedValue(new Error('boom'))
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile())

		await clickButton(wrapper, 'Scan receipt')

		expect(wrapper.text()).toContain('Failed to scan the receipt')
	})

	it('requires a scan before saving', async () => {
		const wrapper = await render('scan')
		await attachFile(wrapper, imageFile())
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(receiptApi.commitReceipt).not.toHaveBeenCalled()
		expect(wrapper.emitted('saved')).toBeFalsy()
		expect(wrapper.text()).toContain('Scan the receipt first.')
	})

	it('commits the scanned receipt with its image', async () => {
		vi.mocked(receiptApi.scanReceipt).mockResolvedValue(scanResult())
		vi.mocked(receiptApi.commitReceipt).mockResolvedValue(list({ id: 'saved', name: 'Groceries', hasReceipt: true }))
		const wrapper = await render('scan')

		await selectListName(wrapper, 'Groceries')
		await attachFile(wrapper, imageFile())
		await clickButton(wrapper, 'Scan receipt')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(receiptApi.commitReceipt).toHaveBeenCalledWith(
			expect.objectContaining({ name: 'Groceries', storeName: 'Aldi', saveReceipt: true }),
			expect.any(Blob),
		)
		expect(wrapper.emitted('saved')?.[0]?.[0]).toMatchObject({ id: 'saved' })
		expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
	})

	it('does not upload the image when Save receipt is unchecked', async () => {
		vi.mocked(receiptApi.scanReceipt).mockResolvedValue(scanResult())
		vi.mocked(receiptApi.commitReceipt).mockResolvedValue(list({ id: 'saved' }))
		const wrapper = await render('scan')

		await selectListName(wrapper, 'Groceries')
		await attachFile(wrapper, imageFile())
		await clickButton(wrapper, 'Scan receipt')
		wrapper.findComponent(NcCheckboxRadioSwitch).vm.$emit('update:modelValue', false)
		await nextTick()
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(receiptApi.commitReceipt).toHaveBeenCalledWith(
			expect.objectContaining({ saveReceipt: false }),
			null,
		)
	})

	it('disables the scan tab and shows a hint without an active LLM profile', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([profile(false)])
		const wrapper = await render('manual')
		await flushPromises()

		const scanButton = wrapper.findAllComponents(NcRadioGroupButton).find((button) => button.props('value') === 'scan')
		expect(scanButton?.props('disabled')).toBe(true)
		expect(wrapper.text()).toContain('Receipt scanning needs an LLM profile')
	})

	it('keeps the scan tab enabled when an active profile exists', async () => {
		const wrapper = await render('manual')
		await flushPromises()

		const scanButton = wrapper.findAllComponents(NcRadioGroupButton).find((button) => button.props('value') === 'scan')
		expect(scanButton?.props('disabled')).toBe(false)
		expect(wrapper.text()).not.toContain('Receipt scanning needs an LLM profile')
	})

	it('falls back to manual input when opened on scan without an active profile', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([])
		const wrapper = await render('scan')
		await flushPromises()

		expect(wrapper.find('input[type="file"]').exists()).toBe(false)
	})
})
