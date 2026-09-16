<script setup lang="ts">
import { mdiAlertCircle, mdiCartOff, mdiCartPlus, mdiCashPlus, mdiPlus } from '@mdi/js'
import { onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AddProductDialog from '../components/AddProductDialog.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import NewListDialog from '../components/NewListDialog.vue'
import PurchaseDialog from '../components/PurchaseDialog.vue'
import ReceiptViewDialog from '../components/ReceiptViewDialog.vue'
import ListGroupSection from '../components/shoppinglists/ListGroupSection.vue'
import { useShoppingLists } from '../composables/useShoppingLists.ts'
import { t } from '../utils/l10n.ts'

const props = withDefaults(defineProps<{ purchaseMode?: 'manual' | 'scan' | null }>(), {
	purchaseMode: null,
})

const emit = defineEmits<{ purchaseOpened: [] }>()

const {
	lists,
	stores,
	categories,
	products,
	loading,
	error,
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
	askDelete,
	closeConfirmDialog,
	onConfirmDelete,
	openReceipt,
	onReceiptDeleted,
	closeReceipt,
} = useShoppingLists()

const showDialog = ref(false)
const newListIsIncome = ref(false)
const showPurchaseDialog = ref(false)
const purchaseDialogMode = ref<'manual' | 'scan'>('manual')

onMounted(loadData)

function openListDialog(isIncome: boolean) {
	newListIsIncome.value = isIncome
	showDialog.value = true
}

function openPurchaseDialog(mode: 'manual' | 'scan') {
	purchaseDialogMode.value = mode
	showPurchaseDialog.value = true
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
	<div :class="$style.wrapper">
		<div :class="$style.header">
			<h2>{{ t('Shopping Lists') }}</h2>

			<div :class="$style.actions">
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
				@addItem="openAddProduct"
				@deleteItem="onDeleteItem" />
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
			@update:open="closeAddProduct"
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

.add-button {
	margin-top: 6px;
}
</style>
