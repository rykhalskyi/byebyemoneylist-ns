import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type { Category, ListPayload, ShoppingList, Store } from '../types'

interface OcsData<T> {
	ocs: {
		data: T
	}
}

export async function fetchLists(): Promise<ShoppingList[]> {
	const { data } = await axios.get<OcsData<{ lists: ShoppingList[] }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/lists'),
	)
	return data.ocs.data.lists
}

export async function createList(payload: ListPayload): Promise<ShoppingList> {
	const { data } = await axios.post<OcsData<{ list: ShoppingList }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/lists'),
		payload,
	)
	return data.ocs.data.list
}

export async function fetchStores(): Promise<Store[]> {
	const { data } = await axios.get<OcsData<{ stores: Store[] }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/stores'),
	)
	return data.ocs.data.stores
}

export async function fetchCategories(): Promise<Category[]> {
	const { data } = await axios.get<OcsData<{ categories: Category[] }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/categories'),
	)
	return data.ocs.data.categories
}
