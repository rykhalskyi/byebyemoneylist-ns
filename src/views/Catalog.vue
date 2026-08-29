<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { mdiAlertCircle, mdiDelete, mdiPackageVariant, mdiPackageVariantClosed, mdiPencil, mdiPlus, mdiStar, mdiStore, mdiStoreOff, mdiTagMultiple, mdiTagOff } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NewCategoryDialog from '../components/NewCategoryDialog.vue'
import NewProductDialog from '../components/NewProductDialog.vue'
import NewStoreDialog from '../components/NewStoreDialog.vue'
import { deleteCategory, deleteProduct, deleteStore, fetchCategories, fetchProducts, fetchStores } from '../services/listsApi'
import type { Category, Product, Store } from '../types'

type TabId = 'categories' | 'stores' | 'products'

interface FlatCategory {
	category: Category
	depth: number
}

const tabs: { id: TabId; label: string }[] = [
	{ id: 'categories', label: 'Categories' },
	{ id: 'stores', label: 'Stores' },
	{ id: 'products', label: 'Products' },
]

const activeTab = ref<TabId>('categories')
const categories = ref<Category[]>([])
const stores = ref<Store[]>([])
const products = ref<Product[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const showCategoryDialog = ref(false)
const showStoreDialog = ref(false)
const showProductDialog = ref(false)
const editingCategory = ref<Category | null>(null)
const editingStore = ref<Store | null>(null)
const editingProduct = ref<Product | null>(null)

const addButtonLabel = computed(() => {
	switch (activeTab.value) {
	case 'categories':
		return 'Add category'
	case 'stores':
		return 'Add store'
	default:
		return 'Add product'
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

onMounted(loadData)

async function loadData() {
	loading.value = true
	error.value = null
	try {
		const [categoryData, storeData, productData] = await Promise.all([fetchCategories(), fetchStores(), fetchProducts()])
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

function categoryName(product: Product): string {
	return categories.value.find((candidate) => candidate.id === product.categoryId)?.name ?? ''
}

function categoryForProduct(product: Product): Category | null {
	return categories.value.find((candidate) => candidate.id === product.categoryId) ?? null
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

			<div v-else :class="$style.list">
				<div
					v-for="node in flattenedCategories"
					:key="node.category.id"
					:class="$style['tree-item']"
					:style="{ paddingLeft: `${node.depth * 24}px` }">
					<NcListItem :name="node.category.name" one-line>
						<template #icon>
							<span
								:class="$style['category-bubble']"
								:style="node.category.color ? { backgroundColor: node.category.color } : {}">
								<span v-if="node.category.emoji">{{ node.category.emoji }}</span>
								<NcIconSvgWrapper
									v-else
									:path="mdiTagMultiple"
									:size="16" />
							</span>
						</template>
						<template #subname>
							<div :class="$style.subname">
								<span v-if="parentName(node.category)">
									{{ parentName(node.category) }}
								</span>
								<NcChip
									v-if="node.category.income"
									text="Income"
									variant="success"
									no-close />
							</div>
						</template>
						<template #extra-actions>
							<NcButton
								type="button"
								:aria-label="`Edit ${node.category.name}`"
								@click="editingCategory = node.category">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPencil" :size="20" />
								</template>
							</NcButton>
							<NcButton
								type="button"
								:aria-label="`Delete ${node.category.name}`"
								@click="onDeleteCategory(node.category)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiDelete" :size="20" />
								</template>
							</NcButton>
						</template>
					</NcListItem>
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

			<div v-else :class="$style.list">
				<NcListItem
					v-for="store in stores"
					:key="store.id"
					:name="store.name"
					one-line>
					<template #icon>
						<NcIconSvgWrapper :path="mdiStore" :size="20" />
					</template>
					<template #extra-actions>
						<NcButton
							type="button"
							:aria-label="`Edit ${store.name}`"
							@click="editingStore = store">
							<template #icon>
								<NcIconSvgWrapper :path="mdiPencil" :size="20" />
							</template>
						</NcButton>
						<NcButton
							type="button"
							:aria-label="`Delete ${store.name}`"
							@click="onDeleteStore(store)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
			</div>
		</template>

		<template v-else>
			<NcEmptyContent
				v-if="products.length === 0"
				name="No products yet"
				description="Create your first product to build up your shopping catalog.">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPackageVariantClosed" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" variant="primary" @click="showProductDialog = true">
						Add product
					</NcButton>
				</template>
			</NcEmptyContent>

			<div v-else :class="$style.list">
				<div
					v-for="product in products"
					:key="product.id"
					:class="$style['product-row']">
					<NcListItem
						:name="product.name"
						one-line>
						<template #icon>
							<span
								v-if="categoryForProduct(product)"
								:class="$style['category-bubble']"
								:style="{ backgroundColor: categoryForProduct(product)?.color ?? undefined }">
								<span v-if="categoryForProduct(product)?.emoji">{{ categoryForProduct(product)?.emoji }}</span>
								<NcIconSvgWrapper
									v-else
									:path="mdiTagMultiple"
									:size="16" />
							</span>
							<NcIconSvgWrapper
								v-else
								:path="mdiPackageVariant"
								:size="20" />
						</template>
						<template #subname>
							<div :class="$style.subname">
								<span v-if="categoryName(product)">
									{{ categoryName(product) }}
								</span>
								<NcChip
									v-if="product.barcode"
									:text="product.barcode"
									no-close />
							<NcIconSvgWrapper
								v-if="product.isFavorite"
								:path="mdiStar"
								:size="20"
								:class="$style.favorite" />
							</div>
						</template>
						<template #extra-actions>
							<NcButton
								type="button"
								:aria-label="`Edit ${product.name}`"
								@click="editingProduct = product">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPencil" :size="20" />
								</template>
							</NcButton>
							<NcButton
								type="button"
								:aria-label="`Delete ${product.name}`"
								@click="onDeleteProduct(product)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiDelete" :size="20" />
								</template>
							</NcButton>
						</template>
					</NcListItem>
				</div>
			</div>
		</template>

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
			@update:open="closeProductDialog"
			@created="onProductCreated"
			@updated="onProductUpdated" />
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

.center {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.list {
	margin: 16px 0 0;
}

.tree-item {
	width: 100%;
}

.product-row {
	border-inline-start: 3px solid transparent;
	padding-inline-start: 8px;
}

.category-bubble {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	font-size: 16px;
	line-height: 1;
	background-color: var(--color-background-darker);
	color: var(--color-main-background);
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
	width: 100%;
}

.add-button {
	margin-top: 6px;
}

.favorite {
	color: var(--color-warning);
}
</style>
