import type { ReceiptCommitPayload, ScannedReceipt, ShoppingList } from '../types.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

interface OcsData<T> {
	ocs: {
		data: T
	}
}

/**
 * Send a receipt image to the server and return the parsed, product- and
 * category-matched scan result (uses the active LLM profile server-side).
 *
 * @param image the normalized JPEG receipt image
 */
export async function scanReceipt(image: Blob): Promise<ScannedReceipt> {
	const formData = new FormData()
	formData.append('receipt', image, 'receipt.jpg')
	const { data } = await axios.post<OcsData<{ scan: ScannedReceipt }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/receipts/scan'),
		formData,
	)
	return data.ocs.data.scan
}

/**
 * Save a scanned receipt as a finished list. When `payload.saveReceipt` is true the
 * normalized image is uploaded and stored on the list.
 *
 * @param payload the reviewed receipt payload
 * @param image the normalized JPEG receipt image (stored when saveReceipt is true)
 */
export async function commitReceipt(payload: ReceiptCommitPayload, image: Blob | null = null): Promise<ShoppingList> {
	const formData = new FormData()
	formData.append('payload', JSON.stringify(payload))
	if (payload.saveReceipt && image !== null) {
		formData.append('receipt', image, 'receipt.jpg')
	}
	const { data } = await axios.post<OcsData<{ list: ShoppingList }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/receipts/commit'),
		formData,
	)
	return data.ocs.data.list
}
