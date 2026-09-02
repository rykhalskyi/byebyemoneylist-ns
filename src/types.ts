export type ListStatus = 'new' | 'finished' | 'archived'

export interface ShoppingList {
	id: string
	name: string
	storeId: string | null
	categoryId: string | null
	status: ListStatus
	finalTotal: number | null
	totalPrice: number | null
	createdAt: string | null
}

export interface Store {
	id: string
	name: string
}

export interface Category {
	id: string
	name: string
	color: string | null
	emoji: string | null
	parentId: string | null
	income: boolean
	status?: string
}

export interface ListPayload {
	name: string
	storeId?: string | null
	categoryId?: string | null
}

export interface CategoryPayload {
	name: string
	color?: string | null
	emoji?: string | null
	parentId?: string | null
	income?: boolean
}

export interface StorePayload {
	name: string
}

export interface Product {
	id: string
	name: string
	barcode: string | null
	categoryId: string | null
	aliases: string[]
	isFavorite: boolean
	status: string
	isSubscription: boolean
	isIncome: boolean
}

export interface ProductPayload {
	name: string
	categoryId?: string | null
	barcode?: string | null
	aliases?: string[]
	isFavorite?: boolean
	isSubscription?: boolean
	isIncome?: boolean
}

export interface ListItem {
	id: string
	listId: string
	productId: string
	productName: string
	price: number | null
	quantity: number
	isChecked: boolean
	createdAt: string | null
}

export interface ListItemPayload {
	productId: string
	price?: number | null
	quantity?: number
}

export interface ListItemUpdatePayload {
	isChecked?: boolean
	price?: number | null
	quantity?: number
}
