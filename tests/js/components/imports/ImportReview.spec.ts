import type { PurchaseImport, PurchaseImportItem } from '../../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ImportReview from '../../../../src/components/imports/ImportReview.vue'
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

enableAutoUnmount(afterEach)

describe('ImportReview', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('runs AI matching automatically for unresolved products', async () => {
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
		vi.mocked(importsApi.suggestImportAi).mockRejectedValue({
			response: { status: 409, data: { message: 'No active LLM profile' } },
		})

		const wrapper = mount(ImportReview, { props: { purchaseImport: purchaseImport() } })
		await flushPromises()

		expect(importsApi.suggestImportAi).toHaveBeenCalledWith('import-1')
		expect(inputValue(wrapper)).toBe('Milk')
		expect(wrapper.text()).not.toContain('Add and activate an LLM profile')
	})
})
