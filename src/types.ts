export type ListStatus = 'new' | 'finished'

export type ListKind = 'NEED_TO_BUY' | 'PURCHASE' | 'INCOME' | 'SUBSCRIPTION'

export type ShareMode = 'readonly' | 'readwrite'

export interface ShoppingList {
	id: string
	name: string
	storeId: string | null
	categoryId: string | null
	status: ListStatus
	kind: ListKind
	isActive: boolean
	finalTotal: number | null
	totalPrice: number | null
	createdAt: string | null
	isIncome: boolean
	isSubscription: boolean
	isRecurring: boolean
	hasReceipt: boolean
	sharedBy?: string | null
	shareMode?: ShareMode | null
	revoked?: boolean
	hasShares?: boolean
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
	categoryIds?: string[]
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
	owner?: string
	shared?: boolean
}

export interface Category {
	id: string
	name: string
	color: string | null
	emoji: string | null
	parentId: string | null
	income: boolean
	status?: string
	owner?: string
	shared?: boolean
}

export interface ListShare {
	id: string
	listId: string
	owner: string
	sharedWith: string
	mode: ShareMode
	status: string
	revoked: boolean
	listName: string | null
	createdAt: string | null
	updatedAt: string | null
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
	recurringPeriod?: string
	isForwardEmpty?: boolean
}

export interface CategoryPayload {
	name: string
	color?: string | null
	emoji?: string | null
	parentId?: string | null
	income?: boolean
}

export interface CategoryBatchItem extends CategoryPayload {
	tempId?: string
	status?: string
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
	owner?: string
	shared?: boolean
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

export interface ProductMergePayload {
	primaryId: string
	secondaryId: string
	name: string
	categoryId?: string | null
	barcode?: string | null
	isFavorite?: boolean
	isSubscription?: boolean
	isIncome?: boolean
	pictureFrom?: 'primary' | 'secondary' | 'none'
}

export interface ListItem {
	id: string
	listId: string
	productId: string | null
	productName: string
	customName: string | null
	price: number | null
	quantity: number
	isChecked: boolean
	position: number
	discount: number | null
	createdAt: string | null
	updatedAt: string | null
}

export interface ListItemPayload {
	productId?: string | null
	customName?: string | null
	price?: number | null
	quantity?: number
	publishToOwner?: boolean
}

export interface ListItemUpdatePayload {
	isChecked?: boolean
	price?: number | null
	quantity?: number
	customName?: string | null
}

export interface CategorySpending {
	categoryId: string | null
	total: number
}

export interface DashboardSpending {
	total: number
	byCategory: CategorySpending[]
}

export interface AnalyticsCategoryTotal {
	categoryId: string | null
	total: number
}

export interface AnalyticsStoreTotal {
	storeId: string | null
	total: number
}

export interface AnalyticsListTotal {
	listId: string
	name: string
	total: number
}

export interface AnalyticsOverview {
	totalSpent: number
	totalIncome: number
	byCategory: AnalyticsCategoryTotal[]
	byStore: AnalyticsStoreTotal[]
	byList: AnalyticsListTotal[]
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

export type ImportStatus = 'pending' | 'in_review' | 'approved' | 'discarded'

export type ImportAction = 'pending' | 'map_existing' | 'create_new' | 'skip'

export type ImportMatchSource = 'barcode' | 'exact' | 'alias' | 'fuzzy' | 'ai'

export interface ImportSuggestion {
	productId?: string | null
	productName?: string | null
	categoryId?: string | null
	source?: ImportMatchSource | null
	confidence?: number | null
}

export interface ImportStoreMapping {
	mappedStoreId: string | null
	action: ImportAction
	suggestedStoreId: string | null
	suggestedStoreName: string | null
	source: ImportMatchSource | null
	confidence: number | null
}

export interface PurchaseImportItem {
	id: string
	position: number
	lineId: string | null
	rawName: string
	quantity: number | null
	unitPrice: number | null
	discount: number | null
	isCoupon: boolean
	barcode: string | null
	categoryHint: string | null
	action: ImportAction
	mappedProductId: string | null
	mappedCategoryId: string | null
	newProductName: string | null
	suggestion: ImportSuggestion
}

export interface PurchaseImportCategory {
	id: string
	rawName: string
	isIncome: boolean
	action: ImportAction
	mappedCategoryId: string | null
	suggestion: {
		categoryId?: string | null
		source?: ImportMatchSource | null
		confidence?: number | null
	}
}

export interface PurchaseImport {
	id: string
	clientListId: string
	clientId: string | null
	schemaVersion: string
	name: string
	status: ImportStatus
	storeName: string | null
	storeAddress: string | null
	storeReceiptName: string | null
	currency: string | null
	finalTotal: number | null
	listCreatedAt: string | null
	purchaseDate: string | null
	createdAt: string | null
	updatedAt: string | null
	approvedListId: string | null
	approvedAt: string | null
	storeMapping: ImportStoreMapping
	items: PurchaseImportItem[]
	categories: PurchaseImportCategory[]
}

export interface PurchaseImportSummary {
	id: string
	clientListId: string
	clientId: string | null
	name: string
	status: ImportStatus
	storeName: string | null
	currency: string | null
	finalTotal: number | null
	purchaseDate: string | null
	createdAt: string | null
	updatedAt: string | null
	approvedListId: string | null
	approvedAt: string | null
	itemCount: number
	categoryCount: number
}

export interface ImportStorePayload {
	action: ImportAction
	mappedStoreId?: string | null
	name?: string | null
}

export interface ImportCategoryPayload {
	id: string
	action: ImportAction
	mappedCategoryId?: string | null
}

export interface ImportItemPayload {
	id: string
	action: ImportAction
	mappedProductId?: string | null
	mappedCategoryId?: string | null
	newProductName?: string | null
}

export interface ImportMappingPayload {
	store?: ImportStorePayload
	categories?: ImportCategoryPayload[]
	items?: ImportItemPayload[]
}
