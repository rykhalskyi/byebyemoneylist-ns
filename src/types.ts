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
	isIncome: boolean
	isSubscription: boolean
	isRecurring: boolean
	hasReceipt: boolean
}

export interface ReceiptPicture {
	dataUrl: string
	mime: string
}

export interface ScannedReceiptItem {
	name: string
	quantity: number
	price: number
	discount: number | null
	isCoupon: boolean
	productId: string | null
	categoryId: string | null
	categoryName: string | null
}

export interface ScannedReceipt {
	storeName: string | null
	storeAddress: string | null
	storeId: string | null
	totalSum: number | null
	items: ScannedReceiptItem[]
	profile: {
		id: string
		name: string
		provider: string
	}
}

export interface ReceiptCommitItem {
	productId?: string | null
	name: string
	quantity: number
	price: number
	discount?: number | null
	isCoupon?: boolean
	categoryId?: string | null
	categoryName?: string | null
}

export interface ReceiptCommitPayload {
	name: string
	storeName?: string | null
	storeAddress?: string | null
	finalTotal?: number | null
	purchaseDate?: string | null
	saveReceipt: boolean
	items: ReceiptCommitItem[]
}

export interface Store {
	id: string
	name: string
	address: string | null
	categoryIds: string[]
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
	categoryIds?: string[]
	finalTotal?: number | null
	purchaseDate?: string | null
	isFinished?: boolean
	isIncome?: boolean
	isSubscription?: boolean
	isRecurring?: boolean
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
	address?: string | null
	categoryIds?: string[]
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
	lastPrice: number | null
	lastPriceDate: string | null
	hasPicture: boolean
}

export interface ProductPicture {
	dataUrl: string
	mime: string
}

export interface ProductPrice {
	id: string
	productId: string
	storeId: string | null
	value: number
	date: string | null
	createdAt: string | null
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

export interface CategorySpending {
	categoryId: string | null
	total: number
}

export interface DashboardSpending {
	total: number
	byCategory: CategorySpending[]
}

export type LlmProvider = 'deepseek' | 'siliconflow' | 'gemini' | 'openai' | 'anthropic' | 'grok'

export interface LlmProfile {
	id: string
	name: string
	provider: LlmProvider
	apiKeyMasked: string
	model: string | null
	connectTimeoutSeconds: number
	readTimeoutSeconds: number
	maxTokens: number
	isActive: boolean
	createdAt: string
	updatedAt?: string | null
}

export interface LlmProfilePayload {
	name: string
	provider: LlmProvider
	apiKey?: string
	model?: string | null
	connectTimeoutSeconds?: number
	readTimeoutSeconds?: number
	maxTokens?: number
	isActive?: boolean
}
