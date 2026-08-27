<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { mdiAlertCircle, mdiCart, mdiCartOff, mdiPlus } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NewListDialog from '../components/NewListDialog.vue'
import { fetchCategories, fetchLists, fetchStores } from '../services/listsApi'
import type { Category, ListStatus, ShoppingList, Store } from '../types'

const lists = ref<ShoppingList[]>([])
const stores = ref<Store[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const showDialog = ref(false)

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
			<NcListItem v-for="list in lists"
				:key="list.id"
				:class="$style.item"
				:name="list.name"
				:details="formatTotal(list.finalTotal)"
				one-line>
				<template #icon>
					<NcIconSvgWrapper :path="mdiCart" :size="20" />
				</template>
				<template #subname>
					<div :class="$style.subname">
						<span>{{ subname(list) }}</span>
						<NcChip :text="statusLabel(list.status)" :variant="statusVariant(list.status)" no-close />
					</div>
				</template>
			</NcListItem>
		</ul>

		<NewListDialog :open="showDialog" @update:open="showDialog = $event" @created="onCreated" />
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
	margin-left: auto;
	width: 100%;
}

.item {
	width: 100%;
}

.add-button {
	margin-top: 6px;
}

</style>
