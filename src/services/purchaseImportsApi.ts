import type { ImportMappingPayload, PurchaseImport, PurchaseImportSummary, ShoppingList } from '../types.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

interface OcsData<T> {
	ocs: {
		data: T
	}
}

const baseUrl = '/apps/byebyemoneylist/api/purchase-imports'

export async function fetchPurchaseImports(status?: string): Promise<PurchaseImportSummary[]> {
	const { data } = await axios.get<OcsData<{ imports: PurchaseImportSummary[] }>>(
		generateOcsUrl(baseUrl),
		{ params: status ? { status } : {} },
	)
	return data.ocs.data.imports
}

export async function fetchPurchaseImport(id: string): Promise<PurchaseImport> {
	const { data } = await axios.get<OcsData<{ import: PurchaseImport }>>(generateOcsUrl(`${baseUrl}/${id}`))
	return data.ocs.data.import
}

export async function suggestImport(id: string): Promise<PurchaseImport> {
	const { data } = await axios.post<OcsData<{ import: PurchaseImport }>>(generateOcsUrl(`${baseUrl}/${id}/suggest`))
	return data.ocs.data.import
}

export async function suggestImportAi(id: string): Promise<PurchaseImport> {
	const { data } = await axios.post<OcsData<{ import: PurchaseImport }>>(generateOcsUrl(`${baseUrl}/${id}/suggest-ai`))
	return data.ocs.data.import
}

export async function saveImportMapping(id: string, payload: ImportMappingPayload): Promise<PurchaseImport> {
	const { data } = await axios.put<OcsData<{ import: PurchaseImport }>>(
		generateOcsUrl(`${baseUrl}/${id}/mapping`),
		payload,
	)
	return data.ocs.data.import
}

export async function approveImport(id: string): Promise<ShoppingList> {
	const { data } = await axios.post<OcsData<{ list: ShoppingList }>>(generateOcsUrl(`${baseUrl}/${id}/approve`))
	return data.ocs.data.list
}

export async function discardImport(id: string): Promise<PurchaseImport> {
	const { data } = await axios.delete<OcsData<{ import: PurchaseImport }>>(generateOcsUrl(`${baseUrl}/${id}`))
	return data.ocs.data.import
}
