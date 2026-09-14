<script setup lang="ts">
import type { Category, ShoppingList, Store } from '../types.ts'

import { mdiImagePlus, mdiReceiptText } from '@mdi/js'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createList, createStore, deleteList, fetchListItems, updateList, uploadListReceipt } from '../services/listsApi.ts'
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

const RECEIPT_MAX_BYTES = 4 * 1024 * 1024
const RECEIPT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']

const receiptInput = ref<HTMLInputElement | null>(null)
const receiptFile = ref<File | null>(null)
const receiptPreview = ref<string | null>(null)
const receiptError = ref<string | null>(null)

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
		clearReceiptPreview()
		receiptFile.value = null
		receiptError.value = null
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

function chooseReceipt() {
	receiptInput.value?.click()
}

function clearReceiptPreview() {
	if (receiptPreview.value !== null) {
		URL.revokeObjectURL(receiptPreview.value)
		receiptPreview.value = null
	}
}

function onReceiptSelected(event: Event) {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (file === undefined) {
		return
	}
	if (!RECEIPT_MIME_TYPES.includes(file.type)) {
		receiptError.value = t('Unsupported image type. Use JPEG, PNG, WebP or GIF.')
		return
	}
	if (file.size > RECEIPT_MAX_BYTES) {
		receiptError.value = t('The receipt exceeds the 4 MB limit.')
		return
	}
	clearReceiptPreview()
	receiptFile.value = file
	receiptPreview.value = URL.createObjectURL(file)
	receiptError.value = null
}

function removeReceipt() {
	clearReceiptPreview()
	receiptFile.value = null
	receiptError.value = null
}

onBeforeUnmount(clearReceiptPreview)

async function onSubmit() {
	if (submitting.value) {
		return
	}
	if (mode.value === 'scan') {
		await submitScan()
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

async function submitScan() {
	const name = listName.value.trim()
	if (selectedList.value === null && name === '') {
		error.value = t('The list name is required.')
		return
	}

	submitting.value = true
	error.value = null
	try {
		const existing = findNewListByName(props.lists, name)
		const isNew = selectedList.value === null && existing === null
		const target = selectedList.value ?? existing ?? await createList({ name })
		let savedList = target
		if (receiptFile.value !== null) {
			try {
				await uploadListReceipt(target.id, receiptFile.value)
				savedList = { ...target, hasReceipt: true }
			} catch {
				if (isNew) {
					await deleteList(target.id).catch(() => undefined)
				}
				error.value = t('Failed to upload the receipt. Please try again.')
				return
			}
		}
		emit('saved', savedList)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to create the list. Please try again.')
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
					{{ mode === 'manual'
						? t('Leave empty to name it after the store and date.')
						: t('Select a list or type a name to attach the receipt to.') }}
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

			<div v-else :class="$style.scan">
				<div :class="$style.placeholder">
					<NcIconSvgWrapper :path="mdiReceiptText" :size="48" />
					<p :class="$style['placeholder-text']">
						{{ t('Receipt scanning is coming soon. Attach a receipt to save it with the list.') }}
					</p>
				</div>

				<div :class="$style.field">
					<span :class="$style['field-label']">{{ t('Receipt (optional)') }}</span>
					<input
						ref="receiptInput"
						type="file"
						accept="image/jpeg,image/png,image/webp,image/gif"
						:class="$style['receipt-input']"
						:disabled="submitting"
						@change="onReceiptSelected">
					<div v-if="receiptPreview !== null" :class="$style['receipt-preview']">
						<img :src="receiptPreview" alt="" :class="$style['receipt-image']">
						<div :class="$style['receipt-actions']">
							<NcButton
								type="button"
								variant="secondary"
								:disabled="submitting"
								@click="chooseReceipt">
								{{ t('Replace') }}
							</NcButton>
							<NcButton
								type="button"
								variant="secondary"
								:disabled="submitting"
								@click="removeReceipt">
								{{ t('Remove') }}
							</NcButton>
						</div>
					</div>
					<NcButton
						v-else
						type="button"
						variant="secondary"
						:disabled="submitting"
						@click="chooseReceipt">
						<template #icon>
							<NcIconSvgWrapper :path="mdiImagePlus" :size="20" />
						</template>
						{{ t('Attach receipt') }}
					</NcButton>
					<p :class="$style.hint">
						{{ t('JPEG, PNG, WebP or GIF, up to 4 MB.') }}
					</p>
					<p v-if="receiptError" :class="$style['field-error']">
						{{ receiptError }}
					</p>
				</div>
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

.field-label {
	color: var(--color-text-maxcontrast);
}

.receipt-input {
	display: none;
}

.receipt-preview {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.receipt-image {
	max-width: 100%;
	max-height: 240px;
	border-radius: var(--border-radius);
	object-fit: contain;
	align-self: flex-start;
}

.receipt-actions {
	display: flex;
	gap: 8px;
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

.scan {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.placeholder-text {
	margin: 0;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
