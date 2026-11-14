import type { LlmProfile, PurchaseImport, PurchaseImportItem } from '../../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ImportReview from '../../../../src/components/imports/ImportReview.vue'
import * as llmApi from '../../../../src/services/llmApi.ts'
import * as importsApi from '../../../../src/services/purchaseImportsApi.ts'

vi.mock('../../../../src/services/purchaseImportsApi.ts', () => ({
	approveImport: vi.fn(),
	saveImportMapping: vi.fn(),
	suggestImport: vi.fn(),
	suggestImportAi: vi.fn(),
}))

vi.mock('../../../../src/services/listsApi.ts', () => ({
	fetchCategories: vi.fn().mockResolvedValue([]),
	fetchProducts: vi.fn().mockResolvedValue([]),
	fetchStores: vi.fn().mockResolvedValue([]),
}))

vi.mock('../../../../src/services/llmApi.ts', () => ({
	fetchLlmProfiles: vi.fn().mockResolvedValue([]),
}))

function llmProfile(overrides: Partial<LlmProfile> = {}): LlmProfile {
	return {
		id: 'profile-1',
		name: 'Test profile',
		provider: 'openai',
		apiKeyMasked: '…',
		model: null,
		connectTimeoutSeconds: 30,
		readTimeoutSeconds: 60,
		maxTokens: 2048,
		isActive: true,
		createdAt: '2026-01-01T00:00:00Z',
		...overrides,
	}
}

function item(overrides: Partial<PurchaseImportItem> = {}): PurchaseImportItem {
	return {
		id: 'item-1',
		position: 0,
		lineId: '1',
		rawName: 'Milk',
		quantity: 1,
		unitPrice: 1.5,
		discount: null,
		isCoupon: false,
		barcode: null,
		categoryHint: null,
		action: 'pending',
		mappedProductId: null,
		mappedCategoryId: null,
		newProductName: null,
		suggestion: {
			productId: null,
			productName: null,
			categoryId: null,
			source: null,
			confidence: null,
		},
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
		storeName: null,
		storeAddress: null,
		storeReceiptName: null,
		currency: '€',
		finalTotal: 1.5,
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
			suggestedStoreName: null,
			source: null,
			confidence: null,
		},
		items: [item()],
		categories: [],
		...overrides,
	}
}

function inputValue(wrapper: ReturnType<typeof mount>): string {
	const field = wrapper
		.findAllComponents(NcTextField)
		.find((component) => component.props('label') === 'New product name')
	if (field === undefined) {
		return ''
	}
	return (field.find('input').element as HTMLInputElement).value
}

function findSuggestButton(wrapper: ReturnType<typeof mount>) {
	const button = wrapper.findAll('button').find((entry) => entry.text() === 'Suggest')
	if (button === undefined) {
		throw new Error('Suggest button not found')
	}
	return button
}

enableAutoUnmount(afterEach)

describe('ImportReview', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([])
	})

	it('runs AI matching automatically for unresolved products', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([llmProfile()])
		const importRow = purchaseImport()
		vi.mocked(importsApi.suggestImportAi).mockResolvedValue(purchaseImport({
			status: 'in_review',
			items: [item({
				suggestion: {
					productId: null,
					productName: 'Milk 1L',
					categoryId: null,
					source: 'ai',
					confidence: 0.9,
				},
			})],
		}))

		const wrapper = mount(ImportReview, { props: { purchaseImport: importRow } })
		await flushPromises()

		expect(importsApi.suggestImportAi).toHaveBeenCalledWith('import-1')
		expect(wrapper.text()).toContain('Suggested: ai')
		expect(inputValue(wrapper)).toBe('Milk 1L')
	})

	it('does not run AI matching when every product is already AI-suggested', async () => {
		const importRow = purchaseImport({
			items: [item({
				suggestion: {
					productId: null,
					productName: 'Milk 1L',
					categoryId: null,
					source: 'ai',
					confidence: 0.9,
				},
			})],
		})

		mount(ImportReview, { props: { purchaseImport: importRow } })
		await flushPromises()

		expect(importsApi.suggestImportAi).not.toHaveBeenCalled()
	})

	it('keeps the raw name prefilled when AI matching is unavailable', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([llmProfile()])
		vi.mocked(importsApi.suggestImportAi).mockRejectedValue({
			response: { status: 409, data: { message: 'No active LLM profile' } },
		})

		const wrapper = mount(ImportReview, { props: { purchaseImport: purchaseImport() } })
		await flushPromises()

		expect(importsApi.suggestImportAi).toHaveBeenCalledWith('import-1')
		expect(inputValue(wrapper)).toBe('Milk')
		expect(wrapper.text()).not.toContain('Add and activate an LLM profile')
	})

	it('uses deterministic suggestions when no LLM profile is configured', async () => {
		const importRow = purchaseImport({
			items: [item({
				suggestion: {
					productId: 'product-1',
					productName: 'Milk',
					categoryId: null,
					source: 'exact',
					confidence: 1,
				},
			})],
		})
		vi.mocked(importsApi.suggestImport).mockResolvedValue(importRow)

		const wrapper = mount(ImportReview, { props: { purchaseImport: importRow } })
		await flushPromises()

		await findSuggestButton(wrapper).trigger('click')
		await flushPromises()

		expect(importsApi.suggestImport).toHaveBeenCalledWith('import-1')
		expect(importsApi.suggestImportAi).not.toHaveBeenCalled()
	})

	it('uses AI suggestions when an LLM profile is configured', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([llmProfile()])
		const importRow = purchaseImport({
			items: [item({
				suggestion: {
					productId: 'product-1',
					productName: 'Milk',
					categoryId: null,
					source: 'exact',
					confidence: 1,
				},
			})],
		})
		vi.mocked(importsApi.suggestImportAi).mockResolvedValue(importRow)

		const wrapper = mount(ImportReview, { props: { purchaseImport: importRow } })
		await flushPromises()

		await findSuggestButton(wrapper).trigger('click')
		await flushPromises()

		expect(importsApi.suggestImportAi).toHaveBeenCalledWith('import-1')
		expect(importsApi.suggestImport).not.toHaveBeenCalled()
	})
})
