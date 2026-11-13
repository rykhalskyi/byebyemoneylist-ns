import type { PurchaseImport, PurchaseImportSummary } from '../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ImportReview from '../../../src/components/imports/ImportReview.vue'
import Imports from '../../../src/views/Imports.vue'
import * as importsApi from '../../../src/services/purchaseImportsApi.ts'

vi.mock('../../../src/services/purchaseImportsApi.ts', () => ({
	fetchPurchaseImports: vi.fn(),
	fetchPurchaseImport: vi.fn(),
	discardImport: vi.fn(),
	approveImport: vi.fn(),
	saveImportMapping: vi.fn(),
	suggestImport: vi.fn(),
	suggestImportAi: vi.fn(),
}))

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchCategories: vi.fn().mockResolvedValue([]),
	fetchProducts: vi.fn().mockResolvedValue([]),
	fetchStores: vi.fn().mockResolvedValue([]),
}))

function summary(overrides: Partial<PurchaseImportSummary> = {}): PurchaseImportSummary {
	return {
		id: 'import-1',
		clientListId: 'list-1',
		clientId: null,
		name: 'Incoming purchase',
		status: 'pending',
		storeName: 'Lidl',
		currency: '€',
		finalTotal: 4.5,
		purchaseDate: null,
		createdAt: null,
		updatedAt: null,
		approvedListId: null,
		approvedAt: null,
		itemCount: 1,
		categoryCount: 1,
		...overrides,
	}
}

function purchaseImport(overrides: Partial<PurchaseImport> = {}): PurchaseImport {
	return {
		id: 'import-1',
		clientListId: 'list-1',
		clientId: null,
		schemaVersion: 'bbml.purchase-upload/1',
		name: 'Incoming purchase',
		status: 'pending',
		storeName: 'Lidl',
		storeAddress: null,
		storeReceiptName: null,
		currency: '€',
		finalTotal: 4.5,
		listCreatedAt: null,
		purchaseDate: null,
		createdAt: null,
		updatedAt: null,
		approvedListId: null,
		approvedAt: null,
		storeMapping: {
			mappedStoreId: null,
			action: 'pending',
			suggestedStoreId: null,
			suggestedStoreName: 'Lidl',
			source: null,
			confidence: null,
		},
		items: [],
		categories: [],
		...overrides,
	}
}

enableAutoUnmount(afterEach)

describe('Imports', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('shows only pending and in-review incoming lists', async () => {
		vi.mocked(importsApi.fetchPurchaseImports).mockResolvedValue([
			summary({ id: 'pending', name: 'Pending purchase' }),
			summary({ id: 'review', name: 'Review purchase', status: 'in_review' }),
			summary({ id: 'approved', name: 'Approved purchase', status: 'approved' }),
			summary({ id: 'discarded', name: 'Discarded purchase', status: 'discarded' }),
		])

		const wrapper = mount(Imports)
		await flushPromises()

		expect(wrapper.text()).toContain('Pending purchase')
		expect(wrapper.text()).toContain('Review purchase')
		expect(wrapper.text()).not.toContain('Approved purchase')
		expect(wrapper.text()).not.toContain('Discarded purchase')
	})

	it('removes an imported list from the incoming list', async () => {
		vi.mocked(importsApi.fetchPurchaseImports)
			.mockResolvedValueOnce([summary()])
			.mockResolvedValueOnce([summary({ status: 'approved' })])
		vi.mocked(importsApi.fetchPurchaseImport).mockResolvedValue(purchaseImport())

		const wrapper = mount(Imports)
		await flushPromises()

		await wrapper.findAll('button').find((button) => button.text() === 'Review')!.trigger('click')
		await flushPromises()

		wrapper.findComponent(ImportReview).vm.$emit('approved')
		await flushPromises()

		expect(wrapper.text()).toContain('No incoming lists')
		expect(wrapper.text()).not.toContain('Incoming purchase')
	})

	it('shows a profile hint when the AI profile is missing', async () => {
		vi.mocked(importsApi.fetchPurchaseImports).mockResolvedValue([summary()])
		vi.mocked(importsApi.fetchPurchaseImport).mockResolvedValue(purchaseImport())
		vi.mocked(importsApi.suggestImportAi).mockRejectedValue({
			response: { status: 409, data: { message: 'No active LLM profile' } },
		})

		const wrapper = mount(Imports)
		await flushPromises()
		await wrapper.findAll('button').find((button) => button.text() === 'Review')!.trigger('click')
		await flushPromises()
		await wrapper.findAll('button').find((button) => button.text() === 'AI suggest')!.trigger('click')
		await flushPromises()

		expect(wrapper.text()).toContain('Add and activate an LLM profile in Settings to use AI suggestions.')
		expect(wrapper.findComponent(ImportReview).exists()).toBe(true)
	})

	it('closes the review when the AI suggestion reports an already imported list', async () => {
		vi.mocked(importsApi.fetchPurchaseImports)
			.mockResolvedValueOnce([summary()])
			.mockResolvedValueOnce([])
		vi.mocked(importsApi.fetchPurchaseImport).mockResolvedValue(purchaseImport())
		vi.mocked(importsApi.suggestImportAi).mockRejectedValue({
			response: { status: 409, data: { message: 'Import is already imported' } },
		})

		const wrapper = mount(Imports)
		await flushPromises()
		await wrapper.findAll('button').find((button) => button.text() === 'Review')!.trigger('click')
		await flushPromises()
		await wrapper.findAll('button').find((button) => button.text() === 'AI suggest')!.trigger('click')
		await flushPromises()

		expect(wrapper.findComponent(ImportReview).exists()).toBe(false)
	})

	it('emits close from the back button', async () => {
		vi.mocked(importsApi.fetchPurchaseImports).mockResolvedValue([])

		const wrapper = mount(Imports)
		await flushPromises()

		await wrapper.findAll('button').find((button) => button.text().includes('Back to lists'))!.trigger('click')

		expect(wrapper.emitted('close')).toHaveLength(1)
	})
})
