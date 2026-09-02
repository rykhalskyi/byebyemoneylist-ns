import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type { Category, CategoryPayload, ListItem, ListItemPayload, ListItemUpdatePayload, ListPayload, Product, ProductPayload, ShoppingList, Store, StorePayload } from '../types'

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

export async function createCategory(payload: CategoryPayload): Promise<Category> {
	const { data } = await axios.post<OcsData<{ category: Category }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/categories'),
		payload,
	)
	return data.ocs.data.category
}

export async function updateCategory(id: string, payload: CategoryPayload): Promise<Category> {
	const { data } = await axios.put<OcsData<{ category: Category }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/categories/${id}`),
		payload,
	)
	return data.ocs.data.category
}

export async function deleteCategory(id: string): Promise<void> {
	await axios.delete(generateOcsUrl(`/apps/byebyemoneylist/api/categories/${id}`))
}

export async function createStore(payload: StorePayload): Promise<Store> {
	const { data } = await axios.post<OcsData<{ store: Store }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/stores'),
		payload,
	)
	return data.ocs.data.store
}

export async function updateStore(id: string, payload: StorePayload): Promise<Store> {
	const { data } = await axios.put<OcsData<{ store: Store }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/stores/${id}`),
		payload,
	)
	return data.ocs.data.store
}

export async function deleteStore(id: string): Promise<void> {
	await axios.delete(generateOcsUrl(`/apps/byebyemoneylist/api/stores/${id}`))
}

export async function fetchProducts(type?: 'normal' | 'subscriptions' | 'income' | 'all'): Promise<Product[]> {
	const { data } = await axios.get<OcsData<{ products: Product[] }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/products'),
		{ params: type ? { type } : {} },
	)
	return data.ocs.data.products
}

export async function createProduct(payload: ProductPayload): Promise<Product> {
	const { data } = await axios.post<OcsData<{ product: Product }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/products'),
		payload,
	)
	return data.ocs.data.product
}

export async function updateProduct(id: string, payload: ProductPayload): Promise<Product> {
	const { data } = await axios.put<OcsData<{ product: Product }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/products/${id}`),
		payload,
	)
	return data.ocs.data.product
}

export async function deleteProduct(id: string): Promise<void> {
	await axios.delete(generateOcsUrl(`/apps/byebyemoneylist/api/products/${id}`))
}

export async function fetchListItems(listId: string): Promise<ListItem[]> {
	const { data } = await axios.get<OcsData<{ items: ListItem[] }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/lists/${listId}/items`),
	)
	return data.ocs.data.items
}

export async function addListItem(listId: string, payload: ListItemPayload): Promise<ListItem> {
	const { data } = await axios.post<OcsData<{ item: ListItem }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/lists/${listId}/items`),
		payload,
	)
	return data.ocs.data.item
}

export async function updateListItem(listId: string, itemId: string, payload: ListItemUpdatePayload): Promise<ListItem> {
	const { data } = await axios.put<OcsData<{ item: ListItem }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/lists/${listId}/items/${itemId}`),
		payload,
	)
	return data.ocs.data.item
}

export async function deleteListItem(listId: string, itemId: string): Promise<void> {
	await axios.delete(generateOcsUrl(`/apps/byebyemoneylist/api/lists/${listId}/items/${itemId}`))
}
