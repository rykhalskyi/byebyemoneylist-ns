<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { mdiAlertCircle, mdiPlus, mdiStore, mdiStoreOff, mdiTagMultiple, mdiTagOff } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NewCategoryDialog from '../components/NewCategoryDialog.vue'
import NewStoreDialog from '../components/NewStoreDialog.vue'
import { fetchCategories, fetchStores } from '../services/listsApi'
import type { Category, Store } from '../types'

type TabId = 'categories' | 'stores'

interface FlatCategory {
	category: Category
	depth: number
}

const tabs: { id: TabId; label: string }[] = [
	{ id: 'categories', label: 'Categories' },
	{ id: 'stores', label: 'Stores' },
]

const activeTab = ref<TabId>('categories')
const categories = ref<Category[]>([])
const stores = ref<Store[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const showCategoryDialog = ref(false)
const showStoreDialog = ref(false)

const addButtonLabel = computed(() => (activeTab.value === 'categories' ? 'Add category' : 'Add store'))

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
		const [categoryData, storeData] = await Promise.all([fetchCategories(), fetchStores()])
		categories.value = categoryData
		stores.value = storeData
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

function onAdd() {
	if (activeTab.value === 'categories') {
		showCategoryDialog.value = true
	} else {
		showStoreDialog.value = true
	}
}

async function onCategoryCreated(category: Category) {
	categories.value = [...categories.value, category].sort(byName)
}

async function onStoreCreated(store: Store) {
	stores.value = [...stores.value, store].sort((a, b) => a.name.localeCompare(b.name))
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
							<span :class="$style['category-icon']">
								<span
									v-if="node.category.color"
									:class="$style['color-dot']"
									:style="{ backgroundColor: node.category.color }" />
								<span v-if="node.category.emoji">{{ node.category.emoji }}</span>
								<NcIconSvgWrapper
									v-if="!node.category.emoji && !node.category.color"
									:path="mdiTagMultiple"
									:size="20" />
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
					</NcListItem>
				</div>
			</div>
		</template>

		<template v-else>
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
				</NcListItem>
			</div>
		</template>

		<NewCategoryDialog
			:open="showCategoryDialog"
			@update:open="showCategoryDialog = $event"
			@created="onCategoryCreated" />
		<NewStoreDialog
			:open="showStoreDialog"
			@update:open="showStoreDialog = $event"
			@created="onStoreCreated" />
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

.category-icon {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	min-width: 20px;
}

.color-dot {
	display: inline-block;
	width: 12px;
	height: 12px;
	border-radius: 50%;
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-left: auto;
	width: 100%;
}

.add-button {
	margin-top: 6px;
}
</style>
