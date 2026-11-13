<script setup lang="ts">
import type { KindFilter } from '../composables/useShoppingLists.ts'
import type { ListItem, ShoppingList } from '../types.ts'

import { mdiAlertCircle, mdiCartOff, mdiCartPlus, mdiInbox, mdiPlus } from '@mdi/js'
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AddListDialog from '../components/AddListDialog.vue'
import AddProductDialog from '../components/AddProductDialog.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import PurchaseDialog from '../components/PurchaseDialog.vue'
import ReceiptViewDialog from '../components/ReceiptViewDialog.vue'
import AddToBuyItemDialog from '../components/shoppinglists/AddToBuyItemDialog.vue'
import EditToBuyItemDialog from '../components/shoppinglists/EditToBuyItemDialog.vue'
import ListGroupSection from '../components/shoppinglists/ListGroupSection.vue'
import ShareListDialog from '../components/shoppinglists/ShareListDialog.vue'
import Imports from './Imports.vue'
import { useShoppingLists } from '../composables/useShoppingLists.ts'
import { fetchPurchaseImports } from '../services/purchaseImportsApi.ts'
import { t } from '../utils/l10n.ts'

const props = withDefaults(defineProps<{ purchaseMode?: 'manual' | 'scan' | null }>(), {
	purchaseMode: null,
})

const emit = defineEmits<{ purchaseOpened: [], openToBuy: [] }>()

const {
	lists,
	stores,
	categories,
	products,
	loading,
	error,
	kindFilter,
	filteredLists,
	groups,
	itemsByList,
	itemsLoading,
	itemsError,
	expandedId,
	expandedYears,
	expandedMonths,
	pendingDelete,
	deleting,
	receiptList,
	addProductListId,
	addToBuyListId,
	editingToBuyItem,
	addProductType,
	deleteMessage,
	loadData,
	onCreated,
	onPurchaseSaved,
	toggleYear,
	toggleMonth,
	toggleExpand,
	onItemAdded,
	onDeleteItem,
	openAddProduct,
	closeAddProduct,
	openAddToBuyItem,
	closeAddToBuyItem,
	onToBuyItemAdded,
	openEditToBuyItem,
	closeEditToBuyItem,
	onToBuyItemUpdated,
	onToggleToBuyItem,
	askDelete,
	closeConfirmDialog,
	onConfirmDelete,
	openReceipt,
	onReceiptDeleted,
	closeReceipt,
	onCopyList,
} = useShoppingLists()

const showAddListDialog = ref(false)
const showPurchaseDialog = ref(false)
const purchaseDialogMode = ref<'manual' | 'scan'>('manual')
const shareList = ref<ShoppingList | null>(null)
const shareAddOnly = ref(false)
const inboxOpen = ref(false)
const inboxCount = ref(0)

const filters: { id: KindFilter, label: string }[] = [
	{ id: 'all', label: t('All') },
	{ id: 'toBuy', label: t('To Buy') },
	{ id: 'purchases', label: t('Purchases') },
	{ id: 'income', label: t('Income') },
	{ id: 'subscriptions', label: t('Subscriptions') },
]

const addProductSharedOwner = computed<string | null>(() => {
	const list = lists.value.find((candidate) => candidate.id === addProductListId.value)
	return list?.sharedBy ?? null
})

onMounted(() => {
	void loadData()
	void loadInboxCount()
})

async function loadInboxCount() {
	try {
		const imports = await fetchPurchaseImports()
		inboxCount.value = imports.filter((entry) => entry.status === 'pending' || entry.status === 'in_review').length
	} catch {
		inboxCount.value = 0
	}
}

function openInbox() {
	inboxOpen.value = true
}

async function closeInbox() {
	inboxOpen.value = false
	await loadInboxCount()
}

function openPurchaseDialog(mode: 'manual' | 'scan') {
	purchaseDialogMode.value = mode
	showPurchaseDialog.value = true
}

function onOpenToBuyList() {
	emit('openToBuy')
}

function onRenameItem(_list: ShoppingList, item: ListItem) {
	openEditToBuyItem(item)
}

function openShare(list: ShoppingList) {
	shareAddOnly.value = true
	shareList.value = list
}

function openManageShares(list: ShoppingList) {
	shareAddOnly.value = false
	shareList.value = list
}

function closeShare(open: boolean) {
	if (!open) {
		shareList.value = null
	}
}

function onSharesChanged(hasShares: boolean) {
	const target = shareList.value
	if (target === null) {
		return
	}
	lists.value = lists.value.map((candidate) => (candidate.id === target.id ? { ...candidate, hasShares } : candidate))
}

watch(
	() => props.purchaseMode,
	(mode) => {
		if (mode !== null) {
			openPurchaseDialog(mode)
			emit('purchaseOpened')
		}
	},
	{ immediate: true },
)
</script>

<template>
	<Imports v-if="inboxOpen" @close="closeInbox" />
	<div v-else :class="$style.wrapper">
		<div :class="$style.header">
			<h2>{{ t('Shopping Lists') }}</h2>

			<div :class="$style.actions">
				<NcButton
					:class="$style['add-button']"
					type="button"
					variant="secondary"
					:disabled="inboxCount === 0"
					@click="openInbox">
					<template #icon>
						<NcIconSvgWrapper :path="mdiInbox" :size="20" />
					</template>
					{{ t('Inbox') }} ({{ inboxCount }})
				</NcButton>

				<NcButton
					:class="$style['add-button']"
					type="button"
					variant="secondary"
					@click="openPurchaseDialog('manual')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCartPlus" :size="20" />
					</template>
					{{ t('Add purchase') }}
				</NcButton>

				<NcButton
					:class="$style['add-button']"
					type="button"
					variant="primary"
					@click="showAddListDialog = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" :size="20" />
					</template>
					{{ t('Add list') }}
				</NcButton>
			</div>
		</div>

		<div v-if="!loading && lists.length > 0" :class="$style.filters">
			<NcButton
				v-for="filter in filters"
				:key="filter.id"
				type="button"
				:variant="kindFilter === filter.id ? 'primary' : 'secondary'"
				@click="kindFilter = filter.id">
				{{ filter.label }}
			</NcButton>
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
				<NcButton type="button" variant="primary" @click="showAddListDialog = true">
					{{ t('Add list') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="filteredLists.length === 0"
			:name="t('No lists match this filter')"
			:description="t('Try another list type or show all lists.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCartOff" :size="64" />
			</template>
			<template #action>
				<NcButton type="button" @click="kindFilter = 'all'">
					{{ t('Show all') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else :class="$style.list">
			<ListGroupSection
				v-for="year in groups"
				:key="year.key"
				:year="year"
				:expanded="expandedYears[year.key] ?? false"
				:expandedMonths="expandedMonths"
				:expandedId="expandedId"
				:stores="stores"
				:categories="categories"
				:products="products"
				:itemsByList="itemsByList"
				:itemsLoading="itemsLoading"
				:itemsError="itemsError"
				@toggleYear="toggleYear(year.key)"
				@toggleMonth="toggleMonth"
				@toggle="toggleExpand"
				@delete="askDelete"
				@receipt="openReceipt"
				@copy="onCopyList"
				@share="openShare"
				@manageShares="openManageShares"
				@addItem="openAddProduct"
				@addToBuyItem="openAddToBuyItem"
				@open="onOpenToBuyList"
				@toggleItem="onToggleToBuyItem"
				@renameItem="onRenameItem"
				@deleteItem="onDeleteItem" />
		</div>

		<AddListDialog
			:open="showAddListDialog"
			:categories="categories"
			@update:open="showAddListDialog = $event"
			@created="onCreated" />
		<PurchaseDialog
			:open="showPurchaseDialog"
			:stores="stores"
			:categories="categories"
			:initialMode="purchaseDialogMode"
			@update:open="showPurchaseDialog = $event"
			@saved="onPurchaseSaved" />
		<ReceiptViewDialog
			:open="receiptList !== null"
			:listId="receiptList?.id ?? ''"
			:listName="receiptList?.name"
			@update:open="closeReceipt"
			@deleted="onReceiptDeleted" />
		<AddProductDialog
			:open="addProductListId !== null"
			:listId="addProductListId ?? ''"
			:type="addProductType"
			:sharedOwner="addProductSharedOwner"
			@update:open="closeAddProduct"
			@added="onItemAdded" />
		<AddToBuyItemDialog
			:open="addToBuyListId !== null"
			:listId="addToBuyListId ?? ''"
			@update:open="closeAddToBuyItem"
			@added="onToBuyItemAdded" />
		<EditToBuyItemDialog
			v-if="editingToBuyItem !== null"
			:open="true"
			:listId="editingToBuyItem.listId"
			:item="editingToBuyItem"
			@update:open="closeEditToBuyItem"
			@updated="onToBuyItemUpdated" />
		<ConfirmDialog
			:open="pendingDelete !== null"
			:title="t('Delete list')"
			:message="deleteMessage"
			:busy="deleting"
			@update:open="closeConfirmDialog"
			@confirm="onConfirmDelete" />
		<ShareListDialog
			v-if="shareList !== null"
			:open="true"
			:list="shareList"
			:addOnly="shareAddOnly"
			@update:open="closeShare"
			@sharesChanged="onSharesChanged" />
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

.filters {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 16px;
}

.add-button {
	margin-top: 6px;
}
</style>
