<script setup lang="ts">
import type Fuse from 'fuse.js'
import type { Category, Product, Store } from '../types.ts'
import type { CategorySearchItem } from '../utils/search.ts'

import { mdiAlertCircle, mdiArrowUp, mdiCalendarMonth, mdiMagnify, mdiPackageVariantClosed, mdiPlus, mdiStoreOff, mdiTagOff } from '@mdi/js'
import { computed, onMounted, ref, shallowRef, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import CatalogSearch from '../components/catalog/CatalogSearch.vue'
import CategoryRow from '../components/catalog/CategoryRow.vue'
import ProductRow from '../components/catalog/ProductRow.vue'
import StoreRow from '../components/catalog/StoreRow.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import NewCategoryDialog from '../components/NewCategoryDialog.vue'
import NewProductDialog from '../components/NewProductDialog.vue'
import NewStoreDialog from '../components/NewStoreDialog.vue'
import ProductInfoDialog from '../components/ProductInfoDialog.vue'
import { usePagedList } from '../composables/usePagedList.ts'
import { confirmAllCategories, confirmCategory, deleteCategory, deleteProduct, deleteStore, fetchCategories, fetchProducts, fetchStores } from '../services/listsApi.ts'
import { createCategoryFuse, createProductFuse, createStoreFuse, search } from '../utils/search.ts'

type TabId = 'categories' | 'stores' | 'products' | 'subscriptions' | 'income'

interface FlatCategory {
	category: Category
	depth: number
}

const PAGE_SIZE = 50

const tabs: { id: TabId, label: string }[] = [
	{ id: 'categories', label: 'Categories' },
	{ id: 'stores', label: 'Stores' },
	{ id: 'products', label: 'Products' },
	{ id: 'subscriptions', label: 'Subscriptions' },
	{ id: 'income', label: 'Income' },
]

const activeTab = ref<TabId>('categories')
const categories = ref<Category[]>([])
const stores = ref<Store[]>([])
const products = ref<Product[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const query = ref('')
const showCategoryDialog = ref(false)
const showStoreDialog = ref(false)
const showProductDialog = ref(false)
const editingCategory = ref<Category | null>(null)
const editingStore = ref<Store | null>(null)
const editingProduct = ref<Product | null>(null)
const infoProduct = ref<Product | null>(null)

interface DeleteTargetCategory {
	type: 'category'
	entity: Category
}

interface DeleteTargetStore {
	type: 'store'
	entity: Store
}

interface DeleteTargetProduct {
	type: 'product'
	entity: Product
}

type DeleteTarget = DeleteTargetCategory | DeleteTargetStore | DeleteTargetProduct

const pendingDelete = ref<DeleteTarget | null>(null)
const deleting = ref(false)

const addButtonLabel = computed(() => {
	switch (activeTab.value) {
		case 'categories':
			return 'Add category'
		case 'stores':
			return 'Add store'
		case 'subscriptions':
			return 'Add subscription'
		case 'income':
			return 'Add income source'
		default:
			return 'Add product'
	}
})

const normalProducts = computed(() => products.value.filter((product) => !product.isSubscription && !product.isIncome))
const subscriptionProducts = computed(() => products.value.filter((product) => product.isSubscription))
const incomeProducts = computed(() => products.value.filter((product) => product.isIncome))

const isProductTab = computed(() => activeTab.value === 'products' || activeTab.value === 'subscriptions' || activeTab.value === 'income')

const activeTabProducts = computed<Product[]>(() => {
	switch (activeTab.value) {
		case 'subscriptions':
			return subscriptionProducts.value
		case 'income':
			return incomeProducts.value
		default:
			return normalProducts.value
	}
})

const emptyState = computed<{ title: string, description: string, icon: string }>(() => {
	switch (activeTab.value) {
		case 'subscriptions':
			return {
				title: 'No subscriptions yet',
				description: 'Mark products as subscriptions to track recurring costs.',
				icon: mdiCalendarMonth,
			}
		case 'income':
			return {
				title: 'No income sources yet',
				description: 'Mark products as income to track your earnings.',
				icon: mdiArrowUp,
			}
		default:
			return {
				title: 'No products yet',
				description: 'Create your first product to build up your shopping catalog.',
				icon: mdiPackageVariantClosed,
			}
	}
})

const flattenedCategories = computed<FlatCategory[]>(() => {
	const children = new Map<string | null, Category[]>()
	for (const category of categories.value) {
		const siblings = children.get(category.parentId) ?? []
		siblings.push(category)
		children.set(category.parentId, siblings)
	}

	const roots = (children.get(null) ?? []).slice().sort(byName)
	const flattened: FlatCategory[] = []
	const visited = new Set<string>()

	function visit(category: Category, depth: number) {
		if (visited.has(category.id)) {
			return
		}
		visited.add(category.id)
		flattened.push({ category, depth })
		for (const child of (children.get(category.id) ?? []).slice().sort(byName)) {
			visit(child, depth + 1)
		}
	}

	for (const root of roots) {
		visit(root, 0)
	}

	return flattened
})

const categorySearchItems = computed<CategorySearchItem[]>(() => categories.value.map((category) => ({
	...category,
	parentName: parentName(category),
})))

const categoryFuse = shallowRef<Fuse<CategorySearchItem> | null>(null)
const storeFuse = shallowRef<Fuse<Store> | null>(null)
const productFuse = shallowRef<Fuse<Product> | null>(null)

watch(categorySearchItems, (items) => {
	categoryFuse.value = createCategoryFuse(items)
}, { immediate: true })

watch(stores, (value) => {
	storeFuse.value = createStoreFuse(value)
}, { immediate: true })

watch(activeTabProducts, (value) => {
	productFuse.value = createProductFuse(value)
}, { immediate: true })

const hasSearch = computed(() => query.value.trim() !== '')

const filteredCategories = computed(() => search(categoryFuse.value, categorySearchItems.value, query.value))
const filteredStores = computed(() => search(storeFuse.value, stores.value, query.value))
const filteredProducts = computed(() => search(productFuse.value, activeTabProducts.value, query.value))

const {
	visible: visibleCategories,
	hasMore: hasMoreCategories,
	remaining: remainingCategories,
	loadMore: loadMoreCategories,
	reset: resetCategories,
} = usePagedList(filteredCategories, PAGE_SIZE)

const {
	visible: visibleStores,
	hasMore: hasMoreStores,
	remaining: remainingStores,
	loadMore: loadMoreStores,
	reset: resetStores,
} = usePagedList(filteredStores, PAGE_SIZE)

const {
	visible: visibleProducts,
	hasMore: hasMoreProducts,
	remaining: remainingProducts,
	loadMore: loadMoreProducts,
	reset: resetProducts,
} = usePagedList(filteredProducts, PAGE_SIZE)

const searchPlaceholder = computed(() => {
	switch (activeTab.value) {
		case 'categories':
			return 'Search categories…'
		case 'stores':
			return 'Search stores…'
		case 'subscriptions':
			return 'Search subscriptions…'
		case 'income':
			return 'Search income…'
		default:
			return 'Search products…'
	}
})

const activeTabHasItems = computed(() => {
	switch (activeTab.value) {
		case 'categories':
			return categories.value.length > 0
		case 'stores':
			return stores.value.length > 0
		default:
			return activeTabProducts.value.length > 0
	}
})

const activeFilteredCount = computed(() => {
	switch (activeTab.value) {
		case 'categories':
			return filteredCategories.value.length
		case 'stores':
			return filteredStores.value.length
		default:
			return filteredProducts.value.length
	}
})

const activeVisibleCount = computed(() => {
	switch (activeTab.value) {
		case 'categories':
			return visibleCategories.value.length
		case 'stores':
			return visibleStores.value.length
		default:
			return visibleProducts.value.length
	}
})

const searchSummary = computed(() => {
	if (!hasSearch.value) {
		return ''
	}
	const noun = activeTab.value === 'categories' ? 'categories' : activeTab.value === 'stores' ? 'stores' : 'products'
	return `Showing ${activeVisibleCount.value} of ${activeFilteredCount.value} matching ${noun}`
})

const deleteTitle = computed(() => {
	switch (pendingDelete.value?.type) {
		case 'category':
			return 'Delete category'
		case 'store':
			return 'Delete store'
		default:
			return 'Delete product'
	}
})

const deleteMessage = computed(() => {
	const target = pendingDelete.value
	if (target === null) {
		return ''
	}
	return `Delete "${target.entity.name}"? This cannot be undone.`
})

watch(activeTab, () => {
	query.value = ''
})

watch([activeTab, query], () => {
	resetCategories()
	resetStores()
	resetProducts()
})

onMounted(loadData)

async function loadData() {
	loading.value = true
	error.value = null
	try {
		const [categoryData, storeData, productData] = await Promise.all([fetchCategories(), fetchStores(), fetchProducts('all')])
		categories.value = categoryData
		stores.value = storeData
		products.value = productData
	} catch {
		error.value = 'Failed to load your catalog.'
	} finally {
		loading.value = false
	}
}

function byName(a: Category, b: Category): number {
	return a.name.localeCompare(b.name)
}

function parentName(category: Category): string {
	return categories.value.find((candidate) => candidate.id === category.parentId)?.name ?? ''
}

function categoryForProduct(product: Product): Category | null {
	return categories.value.find((candidate) => candidate.id === product.categoryId) ?? null
}

function storeAccentColor(store: Store): string | null {
	const category = store.categoryIds
		.map((id) => categories.value.find((candidate) => candidate.id === id))
		.find((candidate): candidate is Category => candidate !== undefined)
	return category?.color ?? null
}

function onAdd() {
	if (activeTab.value === 'categories') {
		showCategoryDialog.value = true
	} else if (activeTab.value === 'stores') {
		showStoreDialog.value = true
	} else {
		showProductDialog.value = true
	}
}

function onCategoryCreated(category: Category) {
	categories.value = [...categories.value, category].sort(byName)
}

function onStoreCreated(store: Store) {
	stores.value = [...stores.value, store].sort((a, b) => a.name.localeCompare(b.name))
}

function onProductCreated(product: Product) {
	products.value = [...products.value, product].sort((a, b) => a.name.localeCompare(b.name))
}

function onCategoryUpdated(category: Category) {
	categories.value = categories.value.map((candidate) => (candidate.id === category.id ? category : candidate)).sort(byName)
	editingCategory.value = null
}

function onStoreUpdated(store: Store) {
	stores.value = stores.value.map((candidate) => (candidate.id === store.id ? store : candidate)).sort((a, b) => a.name.localeCompare(b.name))
	editingStore.value = null
}

function onProductUpdated(product: Product) {
	products.value = products.value.map((candidate) => (candidate.id === product.id ? product : candidate)).sort((a, b) => a.name.localeCompare(b.name))
	editingProduct.value = null
}

const pendingCategories = computed(() => categories.value.filter((cat) => cat.status === 'pending_review'))

async function onConfirmCategory(category: Category) {
	try {
		const updated = await confirmCategory(category.id)
		const index = categories.value.findIndex((candidate) => candidate.id === category.id)
		if (index !== -1) {
			categories.value[index] = updated
		}
	} catch {
		await loadData()
	}
}

async function onConfirmAll() {
	try {
		await confirmAllCategories()
		await loadData()
	} catch {
		await loadData()
	}
}

async function onDeleteCategory(category: Category) {
	categories.value = categories.value.filter((candidate) => candidate.id !== category.id)
	try {
		await deleteCategory(category.id)
	} catch {
		await loadData()
	}
}

async function onDeleteStore(store: Store) {
	stores.value = stores.value.filter((candidate) => candidate.id !== store.id)
	try {
		await deleteStore(store.id)
	} catch {
		await loadData()
	}
}

async function onDeleteProduct(product: Product) {
	products.value = products.value.filter((candidate) => candidate.id !== product.id)
	try {
		await deleteProduct(product.id)
	} catch {
		await loadData()
	}
}

function askDelete(target: DeleteTarget) {
	pendingDelete.value = target
}

function closeConfirmDialog(open: boolean) {
	if (!open && !deleting.value) {
		pendingDelete.value = null
	}
}

async function onConfirmDelete() {
	const target = pendingDelete.value
	if (target === null) {
		return
	}
	deleting.value = true
	try {
		if (target.type === 'category') {
			await onDeleteCategory(target.entity)
		} else if (target.type === 'store') {
			await onDeleteStore(target.entity)
		} else {
			await onDeleteProduct(target.entity)
		}
	} finally {
		deleting.value = false
		pendingDelete.value = null
	}
}

function closeCategoryDialog() {
	showCategoryDialog.value = false
	editingCategory.value = null
}

function closeStoreDialog() {
	showStoreDialog.value = false
	editingStore.value = null
}

function closeProductDialog() {
	showProductDialog.value = false
	editingProduct.value = null
}

function closeInfoDialog() {
	infoProduct.value = null
}
</script>

<template>
	<div :class="$style.wrapper">
		<div :class="$style.header">
			<h2>Catalog</h2>

			<NcButton
				:class="$style['add-button']"
				type="button"
				variant="primary"
				@click="onAdd">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" :size="20" />
				</template>
				{{ addButtonLabel }}
			</NcButton>
		</div>

		<div :class="$style.tabs" role="tablist">
			<button
				v-for="tab in tabs"
				:key="tab.id"
				type="button"
				role="tab"
				:class="[$style.tab, { [$style['tab-active']]: activeTab === tab.id }]"
				:aria-selected="activeTab === tab.id"
				@click="activeTab = tab.id">
				{{ tab.label }}
			</button>
		</div>

		<div v-if="!loading && !error && activeTabHasItems" :class="$style.search">
			<CatalogSearch
				v-model="query"
				:placeholder="searchPlaceholder" />
		</div>

		<div v-if="loading" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<NcEmptyContent
			v-else-if="error"
			name="Could not load catalog"
			:description="error">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAlertCircle" :size="64" />
			</template>
			<template #action>
				<NcButton type="button" @click="loadData">
					Try again
				</NcButton>
			</template>
		</NcEmptyContent>

		<template v-else-if="activeTab === 'categories'">
			<NcEmptyContent
				v-if="categories.length === 0"
				name="No categories yet"
				description="Create your first category to start organizing products.">
				<template #icon>
					<NcIconSvgWrapper :path="mdiTagOff" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" variant="primary" @click="showCategoryDialog = true">
						Add category
					</NcButton>
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="hasSearch && filteredCategories.length === 0"
				name="No categories found"
				:description="`Nothing matches “${query}”.`">
				<template #icon>
					<NcIconSvgWrapper :path="mdiMagnify" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" @click="query = ''">
						Clear search
					</NcButton>
				</template>
			</NcEmptyContent>

			<div v-else :class="$style.list">
				<div v-if="!hasSearch && pendingCategories.length > 0" :class="$style['pending-banner']">
					<span>{{ pendingCategories.length }} categories imported from client pending review</span>
					<NcButton type="button" variant="primary" @click="onConfirmAll">
						Approve all
					</NcButton>
				</div>

				<template v-if="!hasSearch">
					<CategoryRow
						v-for="node in flattenedCategories"
						:key="node.category.id"
						:category="node.category"
						:parentName="parentName(node.category)"
						:depth="node.depth"
						@edit="editingCategory = $event"
						@delete="askDelete({ type: 'category', entity: $event })"
						@confirm="onConfirmCategory" />
				</template>
				<template v-else>
					<CategoryRow
						v-for="category in visibleCategories"
						:key="category.id"
						:category="category"
						:parentName="category.parentName"
						:search="query"
						@edit="editingCategory = $event"
						@delete="askDelete({ type: 'category', entity: $event })"
						@confirm="onConfirmCategory" />
				</template>

				<div v-if="hasMoreCategories" :class="$style['load-more']">
					<NcButton type="button" @click="loadMoreCategories">
						Load more ({{ remainingCategories }} remaining)
					</NcButton>
				</div>
			</div>
		</template>

		<template v-else-if="activeTab === 'stores'">
			<NcEmptyContent
				v-if="stores.length === 0"
				name="No stores yet"
				description="Create your first store to start tracking where you shop.">
				<template #icon>
					<NcIconSvgWrapper :path="mdiStoreOff" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" variant="primary" @click="showStoreDialog = true">
						Add store
					</NcButton>
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="hasSearch && filteredStores.length === 0"
				name="No stores found"
				:description="`Nothing matches “${query}”.`">
				<template #icon>
					<NcIconSvgWrapper :path="mdiMagnify" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" @click="query = ''">
						Clear search
					</NcButton>
				</template>
			</NcEmptyContent>

			<div v-else :class="$style.list">
				<StoreRow
					v-for="store in visibleStores"
					:key="store.id"
					:store="store"
					:accentColor="storeAccentColor(store)"
					:search="query"
					@edit="editingStore = $event"
					@delete="askDelete({ type: 'store', entity: $event })" />

				<div v-if="hasMoreStores" :class="$style['load-more']">
					<NcButton type="button" @click="loadMoreStores">
						Load more ({{ remainingStores }} remaining)
					</NcButton>
				</div>
			</div>
		</template>

		<template v-else-if="isProductTab">
			<NcEmptyContent
				v-if="activeTabProducts.length === 0"
				:name="emptyState.title"
				:description="emptyState.description">
				<template #icon>
					<NcIconSvgWrapper :path="emptyState.icon" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" variant="primary" @click="showProductDialog = true">
						{{ addButtonLabel }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="hasSearch && filteredProducts.length === 0"
				name="No products found"
				:description="`Nothing matches “${query}”.`">
				<template #icon>
					<NcIconSvgWrapper :path="mdiMagnify" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" @click="query = ''">
						Clear search
					</NcButton>
				</template>
			</NcEmptyContent>

			<div v-else :class="$style.list">
				<ProductRow
					v-for="product in visibleProducts"
					:key="product.id"
					:product="product"
					:category="categoryForProduct(product)"
					:search="query"
					@open="infoProduct = $event"
					@edit="editingProduct = $event"
					@delete="askDelete({ type: 'product', entity: $event })" />

				<div v-if="hasMoreProducts" :class="$style['load-more']">
					<NcButton type="button" @click="loadMoreProducts">
						Load more ({{ remainingProducts }} remaining)
					</NcButton>
				</div>
			</div>
		</template>

		<p v-if="hasSearch && activeFilteredCount > 0" :class="$style['search-summary']" aria-live="polite">
			{{ searchSummary }}
		</p>

		<NewCategoryDialog
			:open="showCategoryDialog || editingCategory !== null"
			:entity="editingCategory ?? undefined"
			@update:open="closeCategoryDialog"
			@created="onCategoryCreated"
			@updated="onCategoryUpdated" />
		<NewStoreDialog
			:open="showStoreDialog || editingStore !== null"
			:entity="editingStore ?? undefined"
			@update:open="closeStoreDialog"
			@created="onStoreCreated"
			@updated="onStoreUpdated" />
		<NewProductDialog
			:open="showProductDialog || editingProduct !== null"
			:entity="editingProduct ?? undefined"
			:preset="activeTab === 'subscriptions' ? 'subscription' : activeTab === 'income' ? 'income' : undefined"
			@update:open="closeProductDialog"
			@created="onProductCreated"
			@updated="onProductUpdated" />
		<ProductInfoDialog
			:open="infoProduct !== null"
			:product="infoProduct ?? undefined"
			:categories="categories"
			:stores="stores"
			@update:open="closeInfoDialog"
			@updated="onProductUpdated" />
		<ConfirmDialog
			:open="pendingDelete !== null"
			:title="deleteTitle"
			:message="deleteMessage"
			:busy="deleting"
			@update:open="closeConfirmDialog"
			@confirm="onConfirmDelete" />
	</div>
</template>

<style module>
.wrapper {
	box-sizing: border-box;
	padding: 16px;
	width: 100%;
}

.header {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: center;
	gap: 16px;
}

.tabs {
	display: flex;
	gap: 8px;
	margin-top: 16px;
	border-bottom: 1px solid var(--color-border);
}

.tab {
	background: none;
	border: none;
	padding: 8px 12px;
	font-size: var(--default-font-size);
	color: var(--color-text-maxcontrast);
	cursor: pointer;
}

.tab-active {
	color: var(--color-primary-text);
	border-bottom: 2px solid var(--color-primary);
	margin-bottom: -1px;
}

.search {
	margin-top: 16px;
	max-width: 420px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.list {
	margin: 16px 0 0;
}

.load-more {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.search-summary {
	color: var(--color-text-maxcontrast);
	margin: 8px 0 0;
}

.add-button {
	margin-top: 6px;
}

.pending-banner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	background-color: var(--color-warning-light, #fef3c7);
	color: var(--color-text-maxcontrast);
	padding: 12px 16px;
	border-radius: var(--border-radius-large, 8px);
	margin-bottom: 16px;
}
</style>
