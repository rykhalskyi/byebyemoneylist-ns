import type { PurchaseImport, PurchaseImportSummary, ShoppingList } from '../../../src/types.ts'

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
	approveImport,
	discardImport,
	fetchPurchaseImport,
	fetchPurchaseImports,
	saveImportMapping,
	suggestImport,
	suggestImportAi,
} from '../../../src/services/purchaseImportsApi.ts'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string) => `https://cloud.test${path}`,
}))

function summary(): PurchaseImportSummary {
	return {
		id: 'import-1',
		clientListId: 'list-1',
		clientId: null,
		name: 'Purchase',
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
	}
}

function purchaseImport(): PurchaseImport {
	return {
		id: 'import-1',
		clientListId: 'list-1',
		clientId: null,
		schemaVersion: 'bbml.purchase-upload/1',
		name: 'Purchase',
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
	}
}

function shoppingList(): ShoppingList {
	return {
		id: 'list-1',
		name: 'Purchase',
		storeId: null,
		categoryId: null,
		status: 'finished',
		finalTotal: 4.5,
		totalPrice: null,
		createdAt: null,
		isIncome: false,
		isSubscription: false,
		isRecurring: false,
		hasReceipt: false,
	}
}

describe('purchaseImportsApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('lists imports and fetches one import', async () => {
		vi.mocked(axios.get)
			.mockResolvedValueOnce({ data: { ocs: { data: { imports: [summary()] } } } } as never)
			.mockResolvedValueOnce({ data: { ocs: { data: { import: purchaseImport() } } } } as never)

		expect(await fetchPurchaseImports()).toHaveLength(1)
		expect(await fetchPurchaseImport('import-1')).toMatchObject({ id: 'import-1' })

		expect(vi.mocked(axios.get).mock.calls[0][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports')
		expect(vi.mocked(axios.get).mock.calls[1][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports/import-1')
	})

	it('runs deterministic and AI suggestions', async () => {
		vi.mocked(axios.post)
			.mockResolvedValueOnce({ data: { ocs: { data: { import: purchaseImport() } } } } as never)
			.mockResolvedValueOnce({ data: { ocs: { data: { import: purchaseImport() } } } } as never)

		await suggestImport('import-1')
		await suggestImportAi('import-1')

		expect(vi.mocked(axios.post).mock.calls[0][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports/import-1/suggest')
		expect(vi.mocked(axios.post).mock.calls[1][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports/import-1/suggest-ai')
	})

	it('saves a mapping then approves the import', async () => {
		vi.mocked(axios.put).mockResolvedValue({ data: { ocs: { data: { import: purchaseImport() } } } } as never)
		vi.mocked(axios.post).mockResolvedValue({ data: { ocs: { data: { list: shoppingList() } } } } as never)

		await saveImportMapping('import-1', { items: [{ id: 'item-1', action: 'skip' }] })
		const list = await approveImport('import-1')

		expect(list.id).toBe('list-1')
		expect(vi.mocked(axios.put).mock.calls[0][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports/import-1/mapping')
		expect(vi.mocked(axios.post).mock.calls[0][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports/import-1/approve')
	})

	it('discards an import', async () => {
		vi.mocked(axios.delete).mockResolvedValue({ data: { ocs: { data: { import: purchaseImport() } } } } as never)

		await discardImport('import-1')

		expect(vi.mocked(axios.delete).mock.calls[0][0]).toBe('https://cloud.test/apps/byebyemoneylist/api/purchase-imports/import-1')
	})
})
