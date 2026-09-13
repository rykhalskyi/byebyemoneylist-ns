<script setup lang="ts">
import type { Category, ShoppingList, Store } from '../types.ts'

import { mdiReceiptText } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createList, createStore, fetchListItems, updateList } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'
import { defaultListName, findNewListByName, itemsTotal, parsePrice } from '../utils/purchase.ts'

const props = defineProps<{
	open: boolean
	lists: ShoppingList[]
	stores: Store[]
	categories: Category[]
	initialMode?: 'manual' | 'scan'
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	saved: [list: ShoppingList]
}>()

const mode = ref<string>('manual')
const listValue = ref<ShoppingList | string | null>(null)
const storeValue = ref<Store | string | null>(null)
const category = ref<Category | null>(null)
const price = ref('')
const itemsLoading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const storeError = ref(false)
const priceError = ref(false)
const categoryError = ref(false)

const newLists = computed(() => props.lists.filter((list) => list.status === 'new'))
const expenseCategories = computed(() => props.categories.filter((category) => !category.income))
const selectedList = computed(() => (listValue.value !== null && typeof listValue.value === 'object' ? listValue.value : null))
const listName = computed(() => (typeof listValue.value === 'string' ? listValue.value : listValue.value?.name ?? ''))
const storeName = computed(() => (typeof storeValue.value === 'string' ? storeValue.value : storeValue.value?.name ?? ''))

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		mode.value = props.initialMode ?? 'manual'
		listValue.value = null
		storeValue.value = null
		category.value = null
		price.value = ''
		itemsLoading.value = false
		submitting.value = false
		error.value = null
		storeError.value = false
		priceError.value = false
		categoryError.value = false
	},
)

watch(listValue, async (value) => {
	if (value === null || typeof value === 'string') {
		itemsLoading.value = false
		return
	}
	const store = props.stores.find((candidate) => candidate.id === value.storeId)
	storeValue.value = store ?? null
	category.value = props.categories.find((candidate) => candidate.id === value.categoryId) ?? null
	await prefillTotal(value.id)
})

async function prefillTotal(listId: string) {
	itemsLoading.value = true
	try {
		const items = await fetchListItems(listId)
		if (selectedList.value?.id !== listId) {
			return
		}
		const total = itemsTotal(items)
		price.value = total > 0 ? total.toFixed(2) : ''
	} catch {
		if (selectedList.value?.id === listId) {
			price.value = ''
		}
	} finally {
		if (selectedList.value?.id === listId) {
			itemsLoading.value = false
		}
	}
}

async function resolveStoreId(name: string): Promise<string> {
	const existing = props.stores.find((store) => store.name.trim().toLowerCase() === name.toLowerCase())
	if (existing !== undefined) {
		return existing.id
	}
	const store = await createStore({ name })
	return store.id
}

function onCancel() {
	emit('update:open', false)
}

async function onSubmit() {
	if (submitting.value) {
		return
	}
	const name = listName.value.trim()
	const trimmedStore = storeName.value.trim()
	const parsedPrice = parsePrice(price.value)
	const selectedCategory = category.value

	storeError.value = trimmedStore === ''
	priceError.value = parsedPrice === null
	categoryError.value = selectedCategory === null

	if (storeError.value || priceError.value || categoryError.value || parsedPrice === null || selectedCategory === null) {
		return
	}

	submitting.value = true
	error.value = null
	try {
		const storeId = await resolveStoreId(trimmedStore)
		const resolvedName = name === '' ? defaultListName(trimmedStore) : name
		const payload = {
			name: resolvedName,
			storeId,
			categoryIds: [selectedCategory.id],
			finalTotal: parsedPrice,
			purchaseDate: new Date().toISOString(),
			isFinished: true,
		}
		const existing = findNewListByName(props.lists, resolvedName)
		const list = existing === null ? await createList(payload) : await updateList(existing.id, payload)
		emit('saved', list)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to create the purchase. Please try again.')
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('Add purchase')"
		:open="props.open"
		size="normal"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<div :class="$style.field">
				<NcSelect
					v-model="listValue"
					label="name"
					:inputLabel="t('List name')"
					:placeholder="t('Select or type a list name')"
					:options="newLists"
					:disabled="submitting"
					:taggable="true"
					:clearable="false"
					:filterable="true" />
				<p :class="$style.hint">
					{{ t('Leave empty to name it after the store and date.') }}
				</p>
			</div>

			<NcRadioGroup v-model="mode" :disabled="submitting">
				<NcRadioGroupButton :label="t('Manual input')" value="manual" />
				<NcRadioGroupButton :label="t('Scan receipt')" value="scan" />
			</NcRadioGroup>

			<template v-if="mode === 'manual'">
				<div :class="$style.field">
					<NcSelect
						v-model="storeValue"
						label="name"
						:inputLabel="t('Store')"
						:placeholder="t('Select a store')"
						:options="props.stores"
						:disabled="submitting"
						:taggable="true"
						:clearable="false"
						:filterable="true" />
					<p v-if="storeError" :class="$style['field-error']">
						{{ t('The store name is required.') }}
					</p>
				</div>

				<div :class="$style.field">
					<NcTextField
						v-model="price"
						type="text"
						inputmode="decimal"
						:label="t('Total price')"
						:placeholder="t('e.g. 1.99')"
						:disabled="submitting || itemsLoading"
						:helperText="itemsLoading ? t('Loading…') : ''"
						:error="priceError" />
					<p v-if="priceError" :class="$style['field-error']">
						{{ price.trim() === '' ? t('The total price is required.') : t('Please enter a valid total price.') }}
					</p>
				</div>

				<div :class="$style.field">
					<NcSelect
						v-model="category"
						label="name"
						:inputLabel="t('Category')"
						:placeholder="t('Select a category')"
						:options="expenseCategories"
						:disabled="submitting"
						clearable />
					<p v-if="categoryError" :class="$style['field-error']">
						{{ t('The category is required.') }}
					</p>
				</div>
			</template>

			<div v-else :class="$style.placeholder">
				<NcIconSvgWrapper :path="mdiReceiptText" :size="48" />
				<p :class="$style['placeholder-text']">
					{{ t('Receipt scanning is coming soon.') }}
				</p>
			</div>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="submitting">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ t('Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.field-error {
	color: var(--color-error);
	margin: 0;
}

.hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.placeholder {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 24px 0;
	color: var(--color-text-maxcontrast);
}

.placeholder-text {
	margin: 0;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
