import type { ReceiptCommitPayload, ScannedReceipt, ShoppingList } from '../../../src/types.ts'

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { commitReceipt, scanReceipt } from '../../../src/services/receiptApi.ts'

vi.mock('@nextcloud/axios', () => ({
	default: { post: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string) => `https://cloud.test${path}`,
}))

function scan(overrides: Partial<ScannedReceipt> = {}): ScannedReceipt {
	return {
		storeName: 'Aldi',
		storeAddress: null,
		storeId: null,
		totalSum: 3,
		items: [],
		profile: { id: 'p1', name: 'DeepSeek', provider: 'deepseek' },
		...overrides,
	}
}

function shoppingList(): ShoppingList {
	return {
		id: 'l1',
		name: 'Aldi',
		storeId: null,
		categoryId: null,
		status: 'finished',
		finalTotal: 3,
		totalPrice: null,
		createdAt: null,
		isIncome: false,
		isSubscription: false,
		isRecurring: false,
		hasReceipt: true,
	}
}

function payload(overrides: Partial<ReceiptCommitPayload> = {}): ReceiptCommitPayload {
	return {
		name: 'Aldi',
		storeName: 'Aldi',
		finalTotal: 3,
		saveReceipt: true,
		items: [{ name: 'Milk', quantity: 2, price: 1.5 }],
		...overrides,
	}
}

describe('receiptApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('posts the receipt image to the scan endpoint', async () => {
		vi.mocked(axios.post).mockResolvedValue({ data: { ocs: { data: { scan: scan() } } } } as never)

		const result = await scanReceipt(new Blob(['jpeg'], { type: 'image/jpeg' }))

		expect(result.storeName).toBe('Aldi')
		expect(axios.post).toHaveBeenCalledOnce()
		const [url, body] = vi.mocked(axios.post).mock.calls[0]
		expect(url).toBe('https://cloud.test/apps/byebyemoneylist/api/receipts/scan')
		expect(body).toBeInstanceOf(FormData)
		expect((body as FormData).get('receipt')).toBeInstanceOf(Blob)
	})

	it('commits the payload and attaches the image when saveReceipt is true', async () => {
		vi.mocked(axios.post).mockResolvedValue({ data: { ocs: { data: { list: shoppingList() } } } } as never)

		const result = await commitReceipt(payload(), new Blob(['jpeg'], { type: 'image/jpeg' }))

		expect(result.id).toBe('l1')
		const [url, body] = vi.mocked(axios.post).mock.calls[0]
		expect(url).toBe('https://cloud.test/apps/byebyemoneylist/api/receipts/commit')
		const formData = body as FormData
		expect(JSON.parse(String(formData.get('payload')))).toMatchObject({ name: 'Aldi', saveReceipt: true })
		expect(formData.get('receipt')).toBeInstanceOf(Blob)
	})

	it('does not attach an image when saveReceipt is false', async () => {
		vi.mocked(axios.post).mockResolvedValue({ data: { ocs: { data: { list: shoppingList() } } } } as never)

		await commitReceipt(payload({ saveReceipt: false }), new Blob(['jpeg'], { type: 'image/jpeg' }))

		const [, body] = vi.mocked(axios.post).mock.calls[0]
		expect((body as FormData).get('receipt')).toBeNull()
	})
})
