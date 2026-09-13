<script setup lang="ts">
import type { Category, ListItem, ListStatus, Product, ShoppingList, Store } from '../types.ts'

import { mdiAlertCircle, mdiAutorenew, mdiCart, mdiCartOff, mdiCartPlus, mdiCashPlus, mdiChevronDown, mdiDelete, mdiDotsVertical, mdiPlus } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AddProductDialog from '../components/AddProductDialog.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import NewListDialog from '../components/NewListDialog.vue'
import PurchaseDialog from '../components/PurchaseDialog.vue'
import { deleteList, deleteListItem, fetchCategories, fetchListItems, fetchLists, fetchProducts, fetchStores } from '../services/listsApi.ts'
import { formatDate, formatMonth, formatTotal } from '../utils/format.ts'
import { getCanonicalLocale, t } from '../utils/l10n.ts'
import { groupListsByMonth } from '../utils/listGroups.ts'

const lists = ref<ShoppingList[]>([])
const stores = ref<Store[]>([])
const categories = ref<Category[]>([])
const products = ref<Product[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const showDialog = ref(false)
const newListIsIncome = ref(false)
const showPurchaseDialog = ref(false)
const expandedId = ref<string | null>(null)
const itemsByList = ref<Record<string, ListItem[]>>({})
const itemsLoading = ref<Record<string, boolean>>({})
const itemsError = ref<Record<string, string>>({})
const addProductListId = ref<string | null>(null)
const expandedYears = ref<Record<string, boolean>>({})
const expandedMonths = ref<Record<string, boolean>>({})
const pendingDelete = ref<ShoppingList | null>(null)
const deleting = ref(false)

const groups = computed(() => groupListsByMonth(lists.value))

const addProductType = computed<'subscriptions' | 'income' | undefined>(() => {
	const list = lists.value.find((candidate) => candidate.id === addProductListId.value)
	if (list === undefined) {
		return undefined
	}
	if (list.isIncome) {
		return 'income'
	}
	if (list.isSubscription) {
		return 'subscriptions'
	}
	return undefined
})

const deleteMessage = computed(() => {
	const list = pendingDelete.value
	if (list === null) {
		return ''
	}
	return t('Delete "{name}"? This cannot be undone.', { name: list.name })
})

onMounted(loadData)

async function loadData() {
	loading.value = true
	error.value = null
	try {
		const [listData, storeData, categoryData, productData] = await Promise.all([
			fetchLists(),
			fetchStores(),
			fetchCategories(),
			fetchProducts(),
		])
		lists.value = listData
		stores.value = storeData
		categories.value = categoryData
		products.value = productData
		expandCurrentPeriod()
	} catch {
		error.value = t('Failed to load your shopping lists.')
	} finally {
		loading.value = false
	}
}

function onCreated(list: ShoppingList) {
	lists.value = [list, ...lists.value]
	expandListGroup(list.createdAt)
}

function onPurchaseSaved(list: ShoppingList) {
	if (lists.value.some((candidate) => candidate.id === list.id)) {
		lists.value = lists.value.map((candidate) => (candidate.id === list.id ? list : candidate))
	} else {
		lists.value = [list, ...lists.value]
	}
	if (itemsByList.value[list.id] !== undefined) {
		const items = { ...itemsByList.value }
		delete items[list.id]
		itemsByList.value = items
	}
	expandListGroup(list.createdAt)
}

function expandListGroup(iso: string | null) {
	if (iso === null) {
		return
	}
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) {
		return
	}
	const yearKey = String(date.getFullYear())
	const monthKey = `${yearKey}-${date.getMonth() + 1}`
	expandedYears.value = { ...expandedYears.value, [yearKey]: true }
	expandedMonths.value = { ...expandedMonths.value, [monthKey]: true }
}

function expandCurrentPeriod() {
	expandListGroup(new Date().toISOString())
}

function toggleYear(key: string) {
	expandedYears.value = { ...expandedYears.value, [key]: !expandedYears.value[key] }
}

function toggleMonth(key: string) {
	expandedMonths.value = { ...expandedMonths.value, [key]: !expandedMonths.value[key] }
}

function yearLabel(year: number | null): string {
	return year === null ? t('No date') : String(year)
}

function monthLabel(month: number | null): string {
	return month === null ? t('No date') : formatMonth(month)
}

function storeName(storeId: string | null): string {
	return stores.value.find((store) => store.id === storeId)?.name ?? ''
}

function categoryName(categoryId: string | null): string {
	return categories.value.find((category) => category.id === categoryId)?.name ?? ''
}

function categoryColor(categoryId: string | null): string | null {
	return categories.value.find((category) => category.id === categoryId)?.color ?? null
}

function productCategoryColor(item: ListItem): string | null {
	const product = products.value.find((candidate) => candidate.id === item.productId)
	return product?.categoryId ? categoryColor(product.categoryId) : null
}

function subname(list: ShoppingList): string {
	const parts = [storeName(list.storeId), categoryName(list.categoryId)].filter(Boolean)
	const date = formatDate(list.createdAt)
	return [parts.join(' · '), date].filter(Boolean).join(' · ')
}

function listIcon(list: ShoppingList): string {
	if (list.isIncome) {
		return mdiCashPlus
	}
	if (list.isSubscription) {
		return mdiAutorenew
	}
	return mdiCart
}

function addItemLabel(list: ShoppingList): string {
	if (list.isIncome) {
		return t('Add income source')
	}
	if (list.isSubscription) {
		return t('Add subscription')
	}
	return t('Add product')
}

function openListDialog(isIncome: boolean) {
	newListIsIncome.value = isIncome
	showDialog.value = true
}

function statusLabel(status: ListStatus): string {
	switch (status) {
		case 'finished':
			return t('Finished')
		case 'archived':
			return t('Archived')
		default:
			return t('New')
	}
}

function statusVariant(status: ListStatus): 'secondary' | 'success' | 'tertiary' {
	if (status === 'finished') {
		return 'success'
	}
	if (status === 'archived') {
		return 'tertiary'
	}
	return 'secondary'
}

function toggleExpand(list: ShoppingList) {
	if (expandedId.value === list.id) {
		expandedId.value = null
		return
	}
	expandedId.value = list.id
	if (itemsByList.value[list.id] === undefined) {
		loadItems(list.id)
	}
}

async function loadItems(listId: string) {
	itemsLoading.value = { ...itemsLoading.value, [listId]: true }
	itemsError.value = { ...itemsError.value, [listId]: '' }
	try {
		itemsByList.value = { ...itemsByList.value, [listId]: await fetchListItems(listId) }
	} catch {
		itemsError.value = { ...itemsError.value, [listId]: t('Failed to load the items.') }
	} finally {
		itemsLoading.value = { ...itemsLoading.value, [listId]: false }
	}
}

function onItemAdded(item: ListItem) {
	const items = itemsByList.value[item.listId] ?? []
	itemsByList.value = { ...itemsByList.value, [item.listId]: [...items, item] }
	addProductListId.value = null
}

function listItems(listId: string): ListItem[] {
	return itemsByList.value[listId] ?? []
}

function checkedSum(list: ShoppingList): number {
	const items = itemsByList.value[list.id]
	if (items === undefined) {
		return 0
	}
	return items
		.filter((item) => item.price !== null)
		.reduce((sum, item) => sum + (item.price ?? 0) * item.quantity, 0)
}

function listTotal(list: ShoppingList): number | null {
	if (list.finalTotal !== null) {
		return list.finalTotal
	}
	if (itemsByList.value[list.id] !== undefined) {
		return checkedSum(list)
	}
	return list.totalPrice
}

function priceText(list: ShoppingList): string | null {
	const total = listTotal(list)
	return total === null ? null : formatTotal(total)
}

function listMarkStyle(list: ShoppingList): Record<string, string> {
	const color = categoryColor(list.categoryId)
	return color === null ? {} : { 'border-inline-start': `3px solid ${color}` }
}

async function onDeleteItem(list: ShoppingList, item: ListItem) {
	const items = itemsByList.value[list.id] ?? []
	itemsByList.value = { ...itemsByList.value, [list.id]: items.filter((candidate) => candidate.id !== item.id) }
	try {
		await deleteListItem(list.id, item.id)
	} catch {
		await loadItems(list.id)
	}
}

function askDelete(list: ShoppingList) {
	pendingDelete.value = list
}

function closeConfirmDialog(open: boolean) {
	if (!open && !deleting.value) {
		pendingDelete.value = null
	}
}

async function onConfirmDelete() {
	const list = pendingDelete.value
	if (list === null) {
		return
	}
	deleting.value = true
	try {
		await deleteList(list.id)
		lists.value = lists.value.filter((candidate) => candidate.id !== list.id)
		if (expandedId.value === list.id) {
			expandedId.value = null
		}
		if (itemsByList.value[list.id] !== undefined) {
			const items = { ...itemsByList.value }
			delete items[list.id]
			itemsByList.value = items
		}
	} catch {
		await loadData()
	} finally {
		deleting.value = false
		pendingDelete.value = null
	}
}

function formatQuantity(quantity: number): string {
	if (Number.isInteger(quantity)) {
		return String(quantity)
	}
	return new Intl.NumberFormat(getCanonicalLocale(), {
		maximumFractionDigits: 2,
	}).format(quantity)
}

function itemDetails(item: ListItem): string {
	if (item.price === null) {
		return ''
	}
	return formatTotal(item.price * item.quantity)
}

function itemSubname(item: ListItem): string {
	const quantity = formatQuantity(item.quantity)
	if (item.price === null) {
		return quantity
	}
	return `${quantity} × ${formatTotal(item.price)}`
}
</script>

<template>
	<div :class="$style.wrapper">
		<div :class="$style.header">
			<h2>{{ t('Shopping Lists') }}</h2>

			<div :class="$style.actions">
				<NcButton
					:class="$style['add-button']"
					type="button"
					variant="secondary"
					@click="showPurchaseDialog = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCartPlus" :size="20" />
					</template>
					{{ t('Add purchase') }}
				</NcButton>

				<NcButton
					:class="$style['add-button']"
					type="button"
					variant="secondary"
					@click="openListDialog(true)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCashPlus" :size="20" />
					</template>
					{{ t('Add income') }}
				</NcButton>

				<NcButton
					:class="$style['add-button']"
					type="button"
					variant="primary"
					@click="openListDialog(false)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" :size="20" />
					</template>
					{{ t('Add list') }}
				</NcButton>
			</div>
		</div>

		<div v-if="loading" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<NcEmptyContent
			v-else-if="error"
			:name="t('Could not load lists')"
			:description="error">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAlertCircle" :size="64" />
			</template>
			<template #action>
				<NcButton type="button" @click="loadData">
					{{ t('Try again') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="lists.length === 0"
			:name="t('No shopping lists yet')"
			:description="t('Create your first list to start tracking your spending.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCartOff" :size="64" />
			</template>
			<template #action>
				<NcButton type="button" variant="primary" @click="openListDialog(false)">
					{{ t('Add list') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else :class="$style.list">
			<section v-for="year in groups" :key="year.key" :class="$style.year">
				<button
					type="button"
					:class="$style['group-header']"
					:aria-expanded="expandedYears[year.key] ?? false"
					@click="toggleYear(year.key)">
					<span :class="$style['group-label']">{{ yearLabel(year.year) }}</span>
					<span v-if="year.total !== null" :class="$style['group-total']">{{ formatTotal(year.total) }}</span>
					<NcIconSvgWrapper
						inline
						:path="mdiChevronDown"
						:size="20"
						:class="[$style.chevron, { [$style['chevron-open']]: expandedYears[year.key] }]" />
				</button>

				<div v-if="expandedYears[year.key]">
					<div v-for="month in year.months" :key="month.key">
						<button
							type="button"
							:class="[$style['group-header'], $style['month-header']]"
							:aria-expanded="expandedMonths[month.key] ?? false"
							@click="toggleMonth(month.key)">
							<span :class="$style['group-label']">{{ monthLabel(month.month) }}</span>
							<span v-if="month.total !== null" :class="$style['group-total']">{{ formatTotal(month.total) }}</span>
							<NcIconSvgWrapper
								inline
								:path="mdiChevronDown"
								:size="20"
								:class="[$style.chevron, { [$style['chevron-open']]: expandedMonths[month.key] }]" />
						</button>

						<div v-if="expandedMonths[month.key]" :class="$style['month-lists']">
							<div
								v-for="list in month.lists"
								:key="list.id"
								:class="$style.item"
								:style="listMarkStyle(list)">
								<NcListItem
									:name="list.name"
									oneLine
									@click="toggleExpand(list)">
									<template #icon>
										<NcIconSvgWrapper :path="listIcon(list)" :size="20" />
									</template>
									<template #subname>
										<div :class="$style.subname">
											<span>{{ subname(list) }}</span>
											<NcChip
												v-if="priceText(list) !== null"
												:text="priceText(list) ?? ''"
												noClose />
											<NcChip :text="statusLabel(list.status)" :variant="statusVariant(list.status)" noClose />
											<NcIconSvgWrapper
												inline
												:path="mdiChevronDown"
												:size="20"
												:class="[$style.chevron, { [$style['chevron-open']]: expandedId === list.id }]" />
										</div>
									</template>
									<template #extra-actions>
										<NcActions
											:forceMenu="true"
											:ariaLabel="t('Actions for {name}', { name: list.name })">
											<template #icon>
												<NcIconSvgWrapper :path="mdiDotsVertical" :size="20" />
											</template>
											<NcActionButton @click.stop="askDelete(list)">
												<template #icon>
													<NcIconSvgWrapper :path="mdiDelete" :size="20" />
												</template>
												{{ t('Delete') }}
											</NcActionButton>
										</NcActions>
									</template>
								</NcListItem>

								<div v-if="expandedId === list.id" :class="$style.items">
									<div v-if="itemsLoading[list.id]" :class="$style.center">
										<NcLoadingIcon />
									</div>

									<p v-else-if="itemsError[list.id]" :class="$style['items-error']">
										{{ itemsError[list.id] }}
									</p>

									<template v-else>
										<ul v-if="listItems(list.id).length > 0" :class="$style['item-list']">
											<NcListItem
												v-for="item in listItems(list.id)"
												:key="item.id"
												:name="item.productName"
												:details="itemDetails(item)"
												compact
												oneLine
												:style="productCategoryColor(item) ? { borderInlineStart: `2px solid ${productCategoryColor(item)}` } : {}">
												<template #subname>
													<span v-if="itemSubname(item)">{{ itemSubname(item) }}</span>
												</template>
												<template #extra-actions>
													<NcButton
														type="button"
														:aria-label="t('Delete {name}', { name: item.productName })"
														@click="onDeleteItem(list, item)">
														<template #icon>
															<NcIconSvgWrapper :path="mdiDelete" :size="20" />
														</template>
													</NcButton>
												</template>
											</NcListItem>
										</ul>
										<p v-else :class="$style['no-items']">
											{{ t('No items yet.') }}
										</p>

										<div :class="$style['list-actions']">
											<NcButton
												type="button"
												variant="primary"
												@click="addProductListId = list.id">
												<template #icon>
													<NcIconSvgWrapper :path="mdiPlus" :size="20" />
												</template>
												{{ addItemLabel(list) }}
											</NcButton>
										</div>
									</template>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		</div>

		<NewListDialog
			:open="showDialog"
			:isIncome="newListIsIncome"
			@update:open="showDialog = $event"
			@created="onCreated" />
		<PurchaseDialog
			:open="showPurchaseDialog"
			:lists="lists"
			:stores="stores"
			:categories="categories"
			@update:open="showPurchaseDialog = $event"
			@saved="onPurchaseSaved" />
		<AddProductDialog
			:open="addProductListId !== null"
			:listId="addProductListId ?? ''"
			:type="addProductType"
			@update:open="addProductListId = null"
			@added="onItemAdded" />
		<ConfirmDialog
			:open="pendingDelete !== null"
			:title="t('Delete list')"
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

.actions {
	display: flex;
	align-items: center;
	gap: 8px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.list {
	margin: 16px 0 0;
	padding: 0;
}

.year {
	margin-top: 12px;
}

.group-header {
	display: flex;
	align-items: center;
	gap: 8px;
	box-sizing: border-box;
	width: 100% !important;
	margin: 0 !important;
	padding: 8px !important;
	border: none;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-main-text);
	font-size: 1.05em;
	font-weight: bold;
	text-align: start;
	cursor: pointer;
}

.group-header:hover {
	background: var(--color-background-hover);
}

.group-header:focus {
	outline: none;
}

.group-header:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.group-label {
	flex: 1;
	min-width: 0;
}

.group-total {
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.month-header {
	color: var(--color-text-maxcontrast);
	font-size: 0.95em;
	font-weight: 600;
}

.month-lists {
	border-inline-start: 2px solid var(--color-border);
	margin-inline-start: 16px;
	padding-inline-start: 8px;
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
	min-width: 0;
}

.item {
	width: 100%;
	border-inline-start: 3px solid transparent;
	padding-inline-start: 8px;
}

.items {
	border-inline-start: 3px solid var(--color-border);
	margin: 0 0 8px 16px;
	padding: 8px 0 8px 16px;
}

.item-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.no-items {
	color: var(--color-text-maxcontrast);
	margin: 0;
	padding: 8px 0;
}

.items-error {
	color: var(--color-error);
	margin: 0;
	padding: 8px 0;
}

.list-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 8px;
}

.chevron {
	flex: 0 0 auto;
	transform-origin: center;
	transition: transform 0.2s ease;
}

.chevron-open {
	transform: rotate(180deg);
}

.add-button {
	margin-top: 6px;
}

</style>
