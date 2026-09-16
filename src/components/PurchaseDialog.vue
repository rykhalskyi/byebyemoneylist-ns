<script setup lang="ts">
import type { Category, ScannedReceipt, ScannedReceiptItem, ShoppingList, Store } from '../types.ts'

import { mdiImagePlus, mdiReceiptText } from '@mdi/js'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createList, createStore, fetchListItems, updateList } from '../services/listsApi.ts'
import { fetchLlmProfiles } from '../services/llmApi.ts'
import { commitReceipt, scanReceipt } from '../services/receiptApi.ts'
import { formatTotal } from '../utils/format.ts'
import { normalizeReceiptImage } from '../utils/image.ts'
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
const receiptBlob = ref<Blob | null>(null)
const receiptError = ref<string | null>(null)
const scanning = ref(false)
const scanResult = ref<ScannedReceipt | null>(null)
const scanError = ref<string | null>(null)
const saveReceipt = ref(true)
const hasActiveProfile = ref<boolean | null>(null)

const newLists = computed(() => props.lists.filter((list) => list.status === 'new'))
const expenseCategories = computed(() => props.categories.filter((category) => !category.income))
const selectedList = computed(() => (listValue.value !== null && typeof listValue.value === 'object' ? listValue.value : null))
const listName = computed(() => (typeof listValue.value === 'string' ? listValue.value : listValue.value?.name ?? ''))
const storeName = computed(() => (typeof storeValue.value === 'string' ? storeValue.value : storeValue.value?.name ?? ''))

const scanItemsSum = computed(() => {
	if (scanResult.value === null) {
		return null
	}
	return scanResult.value.items.reduce((sum, item) => sum + item.price * item.quantity - (item.discount ?? 0), 0)
})
const scanTotal = computed(() => scanResult.value?.totalSum ?? scanItemsSum.value)
const scanDisabled = computed(() => hasActiveProfile.value === false)

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
		receiptBlob.value = null
		receiptError.value = null
		scanning.value = false
		scanResult.value = null
		scanError.value = null
		saveReceipt.value = true
		hasActiveProfile.value = null
		void loadActiveProfile()
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
	resetScan()
}

function resetScan() {
	receiptBlob.value = null
	scanResult.value = null
	scanError.value = null
}

function removeReceipt() {
	clearReceiptPreview()
	receiptFile.value = null
	receiptError.value = null
	resetScan()
}

function isHttpStatus(caught: unknown, status: number): boolean {
	if (typeof caught !== 'object' || caught === null) {
		return false
	}
	const response = (caught as { response?: { status?: number } }).response
	return response?.status === status
}

async function loadActiveProfile() {
	try {
		const profiles = await fetchLlmProfiles()
		hasActiveProfile.value = profiles.some((profile) => profile.isActive)
	} catch {
		// If the list cannot be loaded, keep scanning available; the scan endpoint
		// reports a missing profile itself.
		hasActiveProfile.value = null
	}
	if (hasActiveProfile.value === false && mode.value === 'scan') {
		mode.value = 'manual'
	}
}

async function scanReceiptImage() {
	const file = receiptFile.value
	if (file === null || scanning.value) {
		return
	}
	scanning.value = true
	scanError.value = null
	try {
		const image = await normalizeReceiptImage(file)
		receiptBlob.value = image
		scanResult.value = await scanReceipt(image)
	} catch (caught) {
		receiptBlob.value = null
		scanResult.value = null
		scanError.value = isHttpStatus(caught, 409)
			? t('No active LLM profile. Configure one in Settings to scan receipts.')
			: t('Failed to scan the receipt. Please try again.')
	} finally {
		scanning.value = false
	}
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

function scanItemMeta(item: ScannedReceiptItem): string {
	const parts = [`${item.quantity} × ${formatTotal(item.price)}`]
	if (item.discount !== null && item.discount > 0) {
		parts.push(`− ${formatTotal(item.discount)}`)
	}
	return parts.join(' · ')
}

async function submitScan() {
	const scan = scanResult.value
	if (scan === null) {
		scanError.value = t('Scan the receipt first.')
		return
	}

	const name = listName.value.trim()
	const resolvedName = name === '' ? defaultListName(scan.storeName ?? t('Receipt')) : name

	submitting.value = true
	error.value = null
	try {
		const itemCategoryIds = scan.items
			.map((item) => item.categoryId)
			.filter((categoryId): categoryId is string => categoryId !== null)
		const categoryIds = [...new Set(itemCategoryIds)]
		const list = await commitReceipt({
			name: resolvedName,
			storeName: scan.storeName,
			storeAddress: scan.storeAddress,
			categoryIds,
			finalTotal: scan.totalSum ?? scanItemsSum.value,
			purchaseDate: new Date().toISOString(),
			saveReceipt: saveReceipt.value,
			items: scan.items.map((item) => ({
				productId: item.productId,
				name: item.name,
				quantity: item.quantity,
				price: item.price,
				discount: item.discount,
				isCoupon: item.isCoupon,
				categoryId: item.categoryId,
				categoryName: item.categoryName,
			})),
		}, saveReceipt.value ? receiptBlob.value : null)
		emit('saved', list)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to save the scanned receipt. Please try again.')
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
						: t('Leave empty to name it after the scanned store and date.') }}
				</p>
			</div>

			<NcRadioGroup v-model="mode" :disabled="submitting">
				<NcRadioGroupButton :label="t('Manual input')" value="manual" />
				<NcRadioGroupButton :label="t('Scan receipt')" value="scan" :disabled="scanDisabled" />
			</NcRadioGroup>
			<p v-if="scanDisabled" :class="$style.hint">
				{{ t('Receipt scanning needs an LLM profile. Configure one in Settings.') }}
			</p>

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
				<div :class="$style.field">
					<span :class="$style['field-label']">{{ t('Receipt') }}</span>
					<input
						ref="receiptInput"
						type="file"
						accept="image/jpeg,image/png,image/webp,image/gif"
						:class="$style['receipt-input']"
						:disabled="submitting || scanning"
						@change="onReceiptSelected">
					<div v-if="receiptPreview !== null" :class="$style['receipt-preview']">
						<img :src="receiptPreview" alt="" :class="$style['receipt-image']">
						<div :class="$style['receipt-actions']">
							<NcButton
								type="button"
								variant="secondary"
								:disabled="submitting || scanning"
								@click="chooseReceipt">
								{{ t('Replace') }}
							</NcButton>
							<NcButton
								type="button"
								variant="secondary"
								:disabled="submitting || scanning"
								@click="removeReceipt">
								{{ t('Remove') }}
							</NcButton>
						</div>
					</div>
					<NcButton
						v-else
						type="button"
						variant="secondary"
						:disabled="submitting || scanning"
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

				<NcButton
					v-if="receiptFile !== null && scanResult === null"
					type="button"
					variant="primary"
					:disabled="submitting || scanning"
					@click="scanReceiptImage">
					<template #icon>
						<NcLoadingIcon v-if="scanning" />
						<NcIconSvgWrapper v-else :path="mdiReceiptText" :size="20" />
					</template>
					{{ scanning ? t('Scanning…') : t('Scan receipt') }}
				</NcButton>

				<div v-if="scanResult !== null" :class="$style['scan-result']">
					<h4 :class="$style['scan-title']">
						{{ t('Scanned receipt') }}
					</h4>
					<dl :class="$style['scan-summary']">
						<div :class="$style['scan-row']">
							<dt :class="$style['scan-label']">
								{{ t('Store') }}
							</dt>
							<dd :class="$style['scan-value']">
								{{ scanResult.storeName ?? t('Unknown store') }}
							</dd>
						</div>
						<div v-if="scanTotal !== null" :class="$style['scan-row']">
							<dt :class="$style['scan-label']">
								{{ t('Total') }}
							</dt>
							<dd :class="$style['scan-value']">
								{{ formatTotal(scanTotal) }}
							</dd>
						</div>
					</dl>
					<ul v-if="scanResult.items.length > 0" :class="$style['scan-items']">
						<li v-for="(item, index) in scanResult.items" :key="index" :class="$style['scan-item']">
							<span :class="$style['scan-item-name']">{{ item.name }}</span>
							<span :class="$style['scan-item-meta']">{{ scanItemMeta(item) }}</span>
						</li>
					</ul>
					<p :class="$style.hint">
						{{ t('You can edit the list and its items after saving.') }}
					</p>
					<NcButton
						type="button"
						variant="secondary"
						:disabled="submitting || scanning"
						@click="scanReceiptImage">
						<template #icon>
							<NcLoadingIcon v-if="scanning" />
						</template>
						{{ t('Re-scan') }}
					</NcButton>
				</div>

				<NcCheckboxRadioSwitch
					v-if="receiptFile !== null"
					v-model="saveReceipt"
					type="checkbox"
					:disabled="submitting || scanning">
					{{ t('Save receipt') }}
				</NcCheckboxRadioSwitch>

				<p v-if="scanError" :class="$style['field-error']">
					{{ scanError }}
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

.scan {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.scan-result {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-background-hover);
}

.scan-title {
	margin: 0;
}

.scan-summary {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 0;
}

.scan-row {
	display: flex;
	gap: 8px;
}

.scan-label {
	color: var(--color-text-maxcontrast);
	flex: 0 0 64px;
	margin: 0;
}

.scan-value {
	margin: 0;
	min-width: 0;
	overflow-wrap: anywhere;
}

.scan-items {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
	max-height: 220px;
	overflow-y: auto;
}

.scan-item {
	display: flex;
	justify-content: space-between;
	gap: 12px;
}

.scan-item-name {
	min-width: 0;
	overflow-wrap: anywhere;
}

.scan-item-meta {
	color: var(--color-text-maxcontrast);
	flex: 0 0 auto;
	font-variant-numeric: tabular-nums;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
