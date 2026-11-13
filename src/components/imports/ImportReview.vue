<script setup lang="ts">
import type {
	Category,
	ImportAction,
	ImportMappingPayload,
	Product,
	PurchaseImport,
	PurchaseImportCategory,
	PurchaseImportItem,
	Store,
} from '../../types.ts'

import { mdiArrowLeft } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { fetchCategories, fetchProducts, fetchStores } from '../../services/listsApi.ts'
import { approveImport, saveImportMapping, suggestImport, suggestImportAi } from '../../services/purchaseImportsApi.ts'
import { formatDate, formatTotal } from '../../utils/format.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{ purchaseImport: PurchaseImport }>()

const emit = defineEmits<{
	close: []
	approved: []
}>()

interface ActionOption {
	value: ImportAction
	label: string
}

interface CategoryRowState {
	row: PurchaseImportCategory
	action: ImportAction
	mapped: Category | null
}

interface ItemRowState {
	row: PurchaseImportItem
	action: ImportAction
	product: Product | null
	category: Category | null
	newProductName: string
}

const current = ref<PurchaseImport>(props.purchaseImport)
const stores = ref<Store[]>([])
const categories = ref<Category[]>([])
const products = ref<Product[]>([])
const loadingOptions = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const info = ref<string | null>(null)

const storeAction = ref<ImportAction>('skip')
const storeValue = ref<Store | null>(null)
const newStoreName = ref('')

const categoryRows = ref<CategoryRowState[]>([])
const itemRows = ref<ItemRowState[]>([])

const storeActionOptions = computed<ActionOption[]>(() => [
	{ value: 'map_existing', label: t('Map to existing store') },
	{ value: 'create_new', label: t('Create new store') },
	{ value: 'skip', label: t('Do not import a store') },
])

const categoryActionOptions = computed<ActionOption[]>(() => [
	{ value: 'map_existing', label: t('Map to existing category') },
	{ value: 'create_new', label: t('Create new category') },
	{ value: 'skip', label: t('Skip category') },
])

const itemActionOptions = computed<ActionOption[]>(() => [
	{ value: 'map_existing', label: t('Map to existing product') },
	{ value: 'create_new', label: t('Create new product') },
	{ value: 'skip', label: t('Skip this item') },
])

const isApproved = computed(() => current.value.status === 'approved')

const canSave = computed(() => {
	if (storeAction.value === 'map_existing' && storeValue.value === null) {
		return false
	}
	if (storeAction.value === 'create_new' && newStoreName.value.trim() === '') {
		return false
	}
	for (const row of categoryRows.value) {
		if (row.action === 'map_existing' && row.mapped === null) {
			return false
		}
	}
	for (const row of itemRows.value) {
		if (row.action === 'map_existing' && row.product === null) {
			return false
		}
		if (row.action === 'create_new' && row.newProductName.trim() === '') {
			return false
		}
	}
	return true
})

onMounted(async () => {
	loadingOptions.value = true
	try {
		const [storeList, categoryList, productList] = await Promise.all([
			fetchStores(),
			fetchCategories(),
			fetchProducts('all'),
		])
		stores.value = storeList
		categories.value = categoryList
		products.value = productList
	} catch {
		error.value = t('Could not load your catalog.')
	} finally {
		loadingOptions.value = false
		initFromImport(props.purchaseImport)
	}
})

function initFromImport(value: PurchaseImport) {
	current.value = value

	const mapping = value.storeMapping
	let action = mapping.action
	if (action === 'pending') {
		if (mapping.suggestedStoreId !== null) {
			action = 'map_existing'
		} else if ((mapping.suggestedStoreName ?? value.storeName ?? '').trim() !== '') {
			action = 'create_new'
		} else {
			action = 'skip'
		}
	}
	storeAction.value = action
	const storeId = mapping.mappedStoreId ?? mapping.suggestedStoreId
	storeValue.value = stores.value.find((store) => store.id === storeId) ?? null
	newStoreName.value = mapping.suggestedStoreName ?? value.storeName ?? ''

	categoryRows.value = value.categories.map((row) => {
		let rowAction = row.action
		const mappedId = row.mappedCategoryId ?? row.suggestion.categoryId ?? null
		if (rowAction === 'pending') {
			rowAction = mappedId !== null ? 'map_existing' : 'create_new'
		}
		return {
			row,
			action: rowAction,
			mapped: categories.value.find((category) => category.id === mappedId) ?? null,
		}
	})

	itemRows.value = value.items.map((row) => {
		let rowAction = row.action
		const productId = row.mappedProductId ?? row.suggestion.productId ?? null
		if (rowAction === 'pending') {
			if (row.isCoupon) {
				rowAction = 'skip'
			} else {
				rowAction = productId !== null ? 'map_existing' : 'create_new'
			}
		}
		return {
			row,
			action: rowAction,
			product: products.value.find((product) => product.id === productId) ?? null,
			category: categories.value.find((category) => category.id === (row.mappedCategoryId ?? row.suggestion.categoryId ?? null)) ?? null,
			newProductName: row.newProductName ?? row.suggestion.productName ?? row.rawName,
		}
	})
}

function buildPayload(): ImportMappingPayload {
	const store = { action: storeAction.value } as ImportMappingPayload['store']
	if (store !== undefined) {
		if (storeAction.value === 'map_existing') {
			store.mappedStoreId = storeValue.value?.id ?? null
		}
		if (storeAction.value === 'create_new') {
			store.name = newStoreName.value.trim()
		}
	}

	return {
		store,
		categories: categoryRows.value.map((row) => ({
			id: row.row.id,
			action: row.action,
			mappedCategoryId: row.action === 'map_existing' ? (row.mapped?.id ?? null) : null,
		})),
		items: itemRows.value.map((row) => ({
			id: row.row.id,
			action: row.action,
			mappedProductId: row.action === 'map_existing' ? (row.product?.id ?? null) : null,
			mappedCategoryId: row.category?.id ?? null,
			newProductName: row.action === 'create_new' ? row.newProductName.trim() : null,
		})),
	}
}

async function runSuggest(ai: boolean) {
	busy.value = true
	error.value = null
	info.value = null
	try {
		const next = ai
			? await suggestImportAi(current.value.id)
			: await suggestImport(current.value.id)
		initFromImport(next)
		info.value = t('Suggestions updated.')
	} catch (caught) {
		if (ai && statusOf(caught) === 409) {
			const message = messageOf(caught)
			if (message !== null && message.toLowerCase().includes('llm profile')) {
				error.value = t('Add and activate an LLM profile in Settings to use AI suggestions.')
			} else {
				emit('approved')
			}
		} else {
			error.value = t('Could not update suggestions.')
		}
	} finally {
		busy.value = false
	}
}

async function saveMapping(showConfirmation = true) {
	const next = await saveImportMapping(current.value.id, buildPayload())
	initFromImport(next)
	if (showConfirmation) {
		info.value = t('Mapping saved.')
	}
}

async function onSaveMapping() {
	busy.value = true
	error.value = null
	info.value = null
	try {
		await saveMapping()
	} catch {
		error.value = t('Could not save the mapping.')
	} finally {
		busy.value = false
	}
}

async function onApprove() {
	busy.value = true
	error.value = null
	info.value = null
	try {
		await saveMapping(false)
		await approveImport(current.value.id)
		emit('approved')
	} catch (caught) {
		if (statusOf(caught) === 409) {
			error.value = t('This import was already approved.')
			emit('approved')
		} else {
			error.value = t('Could not approve the import.')
		}
	} finally {
		busy.value = false
	}
}

function statusOf(caught: unknown): number | null {
	if (typeof caught !== 'object' || caught === null) {
		return null
	}
	const response = (caught as { response?: { status?: number } }).response
	return response?.status ?? null
}

function messageOf(caught: unknown): string | null {
	if (typeof caught !== 'object' || caught === null) {
		return null
	}
	const response = (caught as { response?: { data?: { message?: unknown } } }).response
	const message = response?.data?.message
	return typeof message === 'string' ? message : null
}
</script>

<template>
	<div :class="$style.wrapper">
		<div :class="$style.header">
			<NcButton type="button" variant="tertiary" @click="emit('close')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiArrowLeft" :size="20" />
				</template>
				{{ t('Back to inbox') }}
			</NcButton>
			<div :class="$style['header-text']">
				<h2>{{ current.name }}</h2>
				<p :class="$style.meta">
					<span v-if="current.storeName">{{ current.storeName }}</span>
					<span v-if="current.purchaseDate">{{ formatDate(current.purchaseDate) }}</span>
					<span v-if="current.finalTotal !== null">
						{{ t('Final total') }}: {{ formatTotal(current.finalTotal) }} {{ current.currency ?? '' }}
					</span>
				</p>
			</div>
			<div :class="$style['header-actions']">
				<NcButton
					type="button"
					variant="secondary"
					:disabled="busy || loadingOptions || isApproved"
					@click="runSuggest(false)">
					{{ t('Suggest') }}
				</NcButton>
				<NcButton
					type="button"
					variant="secondary"
					:disabled="busy || loadingOptions || isApproved"
					@click="runSuggest(true)">
					{{ t('AI suggest') }}
				</NcButton>
			</div>
		</div>

		<div v-if="loadingOptions" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<template v-else>
			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
			<p v-else-if="info" :class="$style.info">
				{{ info }}
			</p>

			<section :class="$style.section">
				<h3>{{ t('Store mapping') }}</h3>
				<div :class="$style.row">
					<NcSelect
						v-model="storeAction"
						:options="storeActionOptions"
						:reduce="(option) => option.value"
						label="label"
						:clearable="false"
						:inputLabel="t('Store mapping')" />
					<NcSelect
						v-if="storeAction === 'map_existing'"
						v-model="storeValue"
						:options="stores"
						label="name"
						:clearable="false"
						:filterable="true"
						:inputLabel="t('Store')"
						:placeholder="t('Select a store')" />
					<NcTextField
						v-else-if="storeAction === 'create_new'"
						v-model="newStoreName"
						:label="t('Store name')" />
				</div>
				<p v-if="current.storeMapping.source" :class="$style.meta">
					{{ t('Suggested') }}: {{ current.storeMapping.source }}
					<span v-if="current.storeMapping.confidence !== null">
						({{ Math.round((current.storeMapping.confidence ?? 0) * 100) }}%)
					</span>
				</p>
			</section>

			<section :class="$style.section">
				<h3>{{ t('Categories') }}</h3>
				<div v-for="entry in categoryRows" :key="entry.row.id" :class="$style.row">
					<strong :class="$style['row-name']">{{ entry.row.rawName }}</strong>
					<NcSelect
						v-model="entry.action"
						:options="categoryActionOptions"
						:reduce="(option) => option.value"
						label="label"
						:clearable="false" />
					<NcSelect
						v-if="entry.action === 'map_existing'"
						v-model="entry.mapped"
						:options="categories"
						label="name"
						:clearable="false"
						:filterable="true"
						:placeholder="t('Select a category')" />
				</div>
			</section>

			<section :class="$style.section">
				<h3>{{ t('Items') }}</h3>
				<div v-for="entry in itemRows" :key="entry.row.id" :class="$style['item-row']">
					<div :class="$style['item-main']">
						<strong>{{ entry.row.rawName }}</strong>
						<span :class="$style.meta">
							{{ entry.row.quantity ?? 1 }} × {{ formatTotal(entry.row.unitPrice) }}
							<span v-if="entry.row.discount !== null">− {{ formatTotal(entry.row.discount) }}</span>
						</span>
						<span v-if="entry.row.isCoupon" :class="$style.badge">{{ t('Coupon') }}</span>
					</div>

					<NcSelect
						v-model="entry.action"
						:options="itemActionOptions"
						:reduce="(option) => option.value"
						label="label"
						:clearable="false" />

					<NcSelect
						v-if="entry.action === 'map_existing'"
						v-model="entry.product"
						:options="products"
						label="name"
						:clearable="false"
						:filterable="true"
						:placeholder="t('Select a product')" />

					<NcTextField
						v-else-if="entry.action === 'create_new'"
						v-model="entry.newProductName"
						:label="t('New product name')" />

					<NcSelect
						v-if="entry.action !== 'skip'"
						v-model="entry.category"
						:options="categories"
						label="name"
						:filterable="true"
						:placeholder="t('Category')" />

					<span v-if="entry.row.suggestion.source" :class="$style.meta">
						{{ t('Suggested') }}: {{ entry.row.suggestion.source }}
					</span>
				</div>
			</section>

			<div :class="$style.footer">
				<NcButton
					type="button"
					variant="secondary"
					:disabled="busy || isApproved || !canSave"
					@click="onSaveMapping">
					{{ t('Save mapping') }}
				</NcButton>
				<NcButton
					type="button"
					variant="primary"
					:disabled="busy || isApproved || !canSave"
					@click="onApprove">
					{{ t('Approve') }}
				</NcButton>
			</div>
		</template>
	</div>
</template>

<style module>
.wrapper {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
}

.header {
	display: flex;
	align-items: flex-start;
	gap: 16px;
	flex-wrap: wrap;
}

.header-text {
	flex: 1 1 240px;
}

.header-text h2 {
	margin: 0 0 4px;
}

.header-actions {
	display: flex;
	gap: 8px;
}

.meta {
	opacity: 0.7;
	font-size: 0.9em;
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	margin: 0;
}

.section {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 12px 16px;
}

.section h3 {
	margin: 0 0 12px;
}

.row {
	display: grid;
	grid-template-columns: minmax(140px, 1fr) minmax(180px, 1fr) minmax(180px, 1fr);
	gap: 12px;
	align-items: center;
	margin-bottom: 12px;
}

.row-name {
	overflow-wrap: anywhere;
}

.item-row {
	display: grid;
	grid-template-columns: minmax(180px, 2fr) minmax(160px, 1fr) minmax(160px, 1fr) minmax(160px, 1fr);
	gap: 12px;
	align-items: center;
	margin-bottom: 12px;
}

.item-main {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.badge {
	align-self: flex-start;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	border-radius: var(--border-radius-pill);
	padding: 0 8px;
	font-size: 0.8em;
}

.footer {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.error {
	color: var(--color-error-text);
}

.info {
	color: var(--color-success-text);
}

@media (max-width: 900px) {
	.row,
	.item-row {
		grid-template-columns: 1fr;
	}
}
</style>
