<script setup lang="ts">
import type { ListItem, ShoppingList } from '../types.ts'

import { mdiArrowLeft, mdiCart, mdiDelete, mdiPlus } from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import EditToBuyItemDialog from '../components/shoppinglists/EditToBuyItemDialog.vue'
import { addToBuyItem, createToBuyList, deleteListItem, fetchActiveToBuyList, fetchListItems, updateListItem } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'
import { itemName } from '../utils/listDisplay.ts'

const emit = defineEmits<{ close: [] }>()

const list = ref<ShoppingList | null>(null)
const items = ref<ListItem[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const newItem = ref('')
const adding = ref(false)
const editingItem = ref<ListItem | null>(null)
const undoItem = ref<ListItem | null>(null)
const newItemField = ref<InstanceType<typeof NcTextField> | null>(null)
let undoTimer: ReturnType<typeof setTimeout> | null = null

const pendingCount = computed(() => items.value.filter((item) => !item.isChecked).length)

onMounted(load)
onBeforeUnmount(flushUndo)

async function load() {
	loading.value = true
	loadError.value = null
	actionError.value = null
	try {
		list.value = await fetchActiveToBuyList()
		items.value = list.value === null ? [] : await fetchListItems(list.value.id)
	} catch {
		loadError.value = t('Failed to load the To Buy list.')
	} finally {
		loading.value = false
	}
}

async function createList() {
	actionError.value = null
	try {
		list.value = await createToBuyList(t('To Buy'))
		items.value = []
		await nextTick()
		newItemField.value?.focus()
	} catch {
		actionError.value = t('Failed to create the To Buy list.')
	}
}

async function addItem() {
	const listId = list.value?.id
	const name = newItem.value.trim()
	if (listId === undefined || name === '' || adding.value) {
		return
	}
	adding.value = true
	actionError.value = null
	try {
		const item = await addToBuyItem(listId, name)
		items.value = [...items.value, item]
		newItem.value = ''
		await nextTick()
		newItemField.value?.focus()
	} catch {
		actionError.value = t('Failed to add the item.')
	} finally {
		adding.value = false
	}
}

async function toggle(item: ListItem, checked: boolean) {
	const listId = list.value?.id
	if (listId === undefined) {
		return
	}
	const previous = items.value
	actionError.value = null
	items.value = items.value.map((candidate) => (candidate.id === item.id ? { ...candidate, isChecked: checked } : candidate))
	try {
		replaceItem(await updateListItem(listId, item.id, { isChecked: checked }))
	} catch {
		items.value = previous
		actionError.value = t('Failed to update the item.')
	}
}

function openRename(item: ListItem) {
	editingItem.value = item
}

function onRenamed(updated: ListItem) {
	replaceItem(updated)
	editingItem.value = null
}

function replaceItem(updated: ListItem) {
	items.value = items.value.map((candidate) => (candidate.id === updated.id ? updated : candidate))
}

function removeItem(item: ListItem) {
	flushUndo()
	items.value = items.value.filter((candidate) => candidate.id !== item.id)
	undoItem.value = item
	undoTimer = setTimeout(() => {
		undoTimer = null
		const pending = undoItem.value
		undoItem.value = null
		if (pending !== null) {
			void deletePending(pending)
		}
	}, 4000)
}

function undoDelete() {
	if (undoTimer !== null) {
		clearTimeout(undoTimer)
		undoTimer = null
	}
	const item = undoItem.value
	if (item !== null) {
		items.value = [...items.value, item].sort((a, b) => a.position - b.position)
	}
	undoItem.value = null
}

function flushUndo() {
	if (undoTimer !== null) {
		clearTimeout(undoTimer)
		undoTimer = null
	}
	const item = undoItem.value
	undoItem.value = null
	if (item !== null) {
		void deletePending(item)
	}
}

async function deletePending(item: ListItem) {
	const listId = list.value?.id
	if (listId === undefined) {
		return
	}
	try {
		await deleteListItem(listId, item.id)
	} catch {
		await load()
	}
}

function onNewItemKeydown(event: KeyboardEvent) {
	if (event.key === 'Enter') {
		event.preventDefault()
		void addItem()
	}
}
</script>

<template>
	<div :class="$style.wrapper">
		<header :class="$style.header">
			<NcButton
				type="button"
				variant="tertiary"
				:aria-label="t('Back')"
				@click="emit('close')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiArrowLeft" :size="20" />
				</template>
			</NcButton>
			<h2 :class="$style.title">
				{{ list?.name ?? t('To Buy') }}
			</h2>
			<span v-if="list !== null" :class="$style.progress">
				{{ t('{checked} of {total} done', { checked: items.length - pendingCount, total: items.length }) }}
			</span>
		</header>

		<div v-if="loading" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<NcEmptyContent
			v-else-if="loadError !== null"
			:name="t('Could not load the To Buy list')"
			:description="loadError">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCart" :size="64" />
			</template>
			<template #action>
				<NcButton type="button" @click="load">
					{{ t('Try again') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else :class="$style.paper">
			<p v-if="actionError !== null" :class="$style.error">
				{{ actionError }}
			</p>
			<NcEmptyContent
				v-if="list === null"
				:name="t('No active To Buy list')"
				:description="t('Create a To Buy list to start planning what you need to buy.')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCart" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" variant="primary" @click="createList">
						{{ t('Create To Buy list') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<template v-else>
				<ul v-if="items.length > 0" :class="$style.rows">
					<li v-for="item in items" :key="item.id" :class="$style.row">
						<NcCheckboxRadioSwitch
							:modelValue="item.isChecked"
							@update:modelValue="toggle(item, $event)" />
						<button
							type="button"
							:class="[$style.name, { [$style.checked]: item.isChecked }]"
							@click="openRename(item)">
							{{ itemName(item) }}
						</button>
						<NcButton
							type="button"
							variant="tertiary"
							:aria-label="t('Delete {name}', { name: itemName(item) })"
							@click="removeItem(item)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</li>
				</ul>
				<p v-else :class="$style.hint">
					{{ t('Add your first item.') }}
				</p>

				<div :class="$style.quickAdd">
					<NcTextField
						ref="newItemField"
						v-model="newItem"
						:label="t('New item')"
						:placeholder="t('e.g. Milk')"
						:disabled="adding"
						@keydown="onNewItemKeydown" />
					<NcButton
						type="button"
						variant="primary"
						:disabled="newItem.trim() === '' || adding"
						@click="addItem">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPlus" :size="20" />
						</template>
						{{ t('Add item') }}
					</NcButton>
				</div>
			</template>
		</div>

		<div v-if="undoItem !== null" :class="$style.undo">
			<span>{{ t('Item deleted') }}</span>
			<NcButton type="button" variant="tertiary" @click="undoDelete">
				{{ t('Undo') }}
			</NcButton>
		</div>

		<EditToBuyItemDialog
			v-if="editingItem !== null"
			:open="true"
			:listId="editingItem.listId"
			:item="editingItem"
			@update:open="editingItem = null"
			@updated="onRenamed" />
	</div>
</template>

<style module>
.wrapper {
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	width: 100%;
	max-width: 760px;
	margin: 0 auto;
	padding: 16px;
	gap: 12px;
}

.header {
	display: flex;
	align-items: center;
	gap: 8px;
}

.title {
	margin: 0;
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.progress {
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.center {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.paper {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 12px rgb(0 0 0 / 12%);
	padding: 24px;
	min-height: 320px;
}

.rows {
	list-style: none;
	margin: 0;
	padding: 0;
}

.row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
	border-bottom: 1px solid var(--color-border);
}

.name {
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	background: none;
	border: none;
	padding: 6px 0;
	text-align: start;
	color: var(--color-main-text);
	cursor: pointer;
}

.checked {
	text-decoration: line-through;
	color: var(--color-text-maxcontrast);
}

.hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
	padding: 24px 0;
	text-align: center;
}

.quickAdd {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	margin-top: 16px;
	position: sticky;
	bottom: 0;
	background: var(--color-main-background);
	padding-top: 12px;
}

.quickAdd > :first-child {
	flex: 1 1 auto;
}

.error {
	color: var(--color-error);
	margin: 0 0 12px;
}

.undo {
	position: fixed;
	inset-inline: 0;
	bottom: 24px;
	margin: 0 auto;
	width: fit-content;
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 16px;
	border-radius: var(--border-radius-pill);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border);
	box-shadow: 0 2px 12px rgb(0 0 0 / 25%);
}
</style>
