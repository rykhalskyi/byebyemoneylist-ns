<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { mdiAlertCircle, mdiCart, mdiCartOff, mdiChevronDown, mdiPlus } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AddProductDialog from '../components/AddProductDialog.vue'
import NewListDialog from '../components/NewListDialog.vue'
import { fetchCategories, fetchListItems, fetchLists, fetchStores } from '../services/listsApi'
import type { Category, ListItem, ListStatus, ShoppingList, Store } from '../types'

const lists = ref<ShoppingList[]>([])
const stores = ref<Store[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const showDialog = ref(false)
const expandedId = ref<string | null>(null)
const itemsByList = ref<Record<string, ListItem[]>>({})
const itemsLoading = ref<Record<string, boolean>>({})
const itemsError = ref<Record<string, string>>({})
const addProductListId = ref<string | null>(null)

onMounted(loadData)

async function loadData() {
	loading.value = true
	error.value = null
	try {
		const [listData, storeData, categoryData] = await Promise.all([
			fetchLists(),
			fetchStores(),
			fetchCategories(),
		])
		lists.value = listData
		stores.value = storeData
		categories.value = categoryData
	} catch {
		error.value = 'Failed to load your shopping lists.'
	} finally {
		loading.value = false
	}
}

function onCreated(list: ShoppingList) {
	lists.value = [list, ...lists.value]
}

function storeName(storeId: string | null): string {
	return stores.value.find((store) => store.id === storeId)?.name ?? ''
}

function categoryName(categoryId: string | null): string {
	return categories.value.find((category) => category.id === categoryId)?.name ?? ''
}

function subname(list: ShoppingList): string {
	const parts = [storeName(list.storeId), categoryName(list.categoryId)].filter(Boolean)
	const date = formatDate(list.createdAt)
	return [parts.join(' · '), date].filter(Boolean).join(' · ')
}

function formatDate(iso: string | null): string {
	if (iso === null) {
		return ''
	}
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) {
		return ''
	}
	return date.toLocaleDateString(undefined, { dateStyle: 'medium' })
}

function formatTotal(total: number | null): string {
	if (total === null) {
		return ''
	}
	return new Intl.NumberFormat(undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	}).format(total)
}

function statusLabel(status: ListStatus): string {
	return status.charAt(0).toUpperCase() + status.slice(1)
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
		itemsError.value = { ...itemsError.value, [listId]: 'Failed to load the items.' }
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

function formatQuantity(quantity: number): string {
	if (Number.isInteger(quantity)) {
		return String(quantity)
	}
	return new Intl.NumberFormat(undefined, {
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
			<h2>Shopping Lists</h2>

			<NcButton
				:class="$style['add-button']"
				type="button"
				variant="primary"
				@click="showDialog = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" :size="20" />
				</template>
				Add list
			</NcButton>
		</div>

		<div v-if="loading" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<NcEmptyContent
			v-else-if="error"
			name="Could not load lists"
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

		<NcEmptyContent
			v-else-if="lists.length === 0"
			name="No shopping lists yet"
			description="Create your first list to start tracking your spending.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCartOff" :size="64" />
			</template>
			<template #action>
				<NcButton type="button" variant="primary" @click="showDialog = true">
					Add list
				</NcButton>
			</template>
		</NcEmptyContent>

		<ul v-else :class="$style.list">
			<li
				v-for="list in lists"
				:key="list.id"
				:class="$style.item">
				<NcListItem
					:class="$style['full-width-item']"
					:name="list.name"
					:details="formatTotal(list.finalTotal)"
					one-line
					@click="toggleExpand(list)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCart" :size="20" />
					</template>
					<template #subname>
						<div :class="$style.subname">
							<span>{{ subname(list) }}</span>
							<NcChip :text="statusLabel(list.status)" :variant="statusVariant(list.status)" no-close />
							<NcIconSvgWrapper
								:path="mdiChevronDown"
								:size="20"
								:class="[$style.chevron, { [$style['chevron-open']]: expandedId === list.id }]" />
						</div>
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
								one-line>
								<template #subname>
									<span v-if="itemSubname(item)">{{ itemSubname(item) }}</span>
								</template>
							</NcListItem>
						</ul>
						<p v-else :class="$style['no-items']">
							No items yet.
						</p>

						<NcButton
							:class="$style['add-item-button']"
							type="button"
							variant="primary"
							@click="addProductListId = list.id">
							<template #icon>
								<NcIconSvgWrapper :path="mdiPlus" :size="20" />
							</template>
							Add product
						</NcButton>
					</template>
				</div>
			</li>
		</ul>

		<NewListDialog :open="showDialog" @update:open="showDialog = $event" @created="onCreated" />
		<AddProductDialog
			:open="addProductListId !== null"
			:list-id="addProductListId ?? ''"
			@update:open="addProductListId = null"
			@added="onItemAdded" />
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

.center {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.list {
	list-style: none;
	margin: 16px 0 0;
	padding: 0;
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
	min-width: 0;
}

.full-width-item :deep(.list-item__anchor),
.full-width-item :deep(.list-item-content) {
	width: 100%;
}

.full-width-item :deep(.list-item-content__main) {
	flex: 1 1 auto;
}

.item {
	width: 100%;
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

.add-item-button {
	margin-top: 8px;
}

.chevron {
	transition: transform 0.2s ease;
}

.chevron-open {
	transform: rotate(180deg);
}

.add-button {
	margin-top: 6px;
}

</style>
