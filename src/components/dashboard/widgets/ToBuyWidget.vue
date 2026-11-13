<script setup lang="ts">
import type { ListItem, ShoppingList } from '../../../types.ts'

import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { fetchActiveToBuyList, fetchListItems } from '../../../services/listsApi.ts'
import { t } from '../../../utils/l10n.ts'
import { itemName } from '../../../utils/listDisplay.ts'

const emit = defineEmits<{ open: [] }>()

const list = ref<ShoppingList | null>(null)
const items = ref<ListItem[]>([])
const loading = ref(true)

const total = computed(() => items.value.length)
const checked = computed(() => items.value.filter((item) => item.isChecked).length)
const preview = computed(() => items.value
	.filter((item) => !item.isChecked)
	.slice(0, 3)
	.map((item) => ({ id: item.id, name: itemName(item) })))

onMounted(() => {
	void load()
	window.addEventListener('focus', load)
})

onBeforeUnmount(() => {
	window.removeEventListener('focus', load)
})

async function load() {
	try {
		list.value = await fetchActiveToBuyList()
		items.value = list.value === null ? [] : await fetchListItems(list.value.id)
	} catch {
		list.value = null
		items.value = []
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div :class="$style.widget">
		<div v-if="loading" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<div v-else-if="list === null" :class="$style.empty">
			<p :class="$style.hint">
				{{ t('No active To Buy list') }}
			</p>
			<NcButton type="button" variant="primary" @click="emit('open')">
				{{ t('Create To Buy list') }}
			</NcButton>
		</div>

		<button
			v-else
			type="button"
			:class="$style.content"
			:aria-label="t('Open To Buy list')"
			@click="emit('open')">
			<span :class="$style.name">{{ list.name }}</span>
			<span :class="$style.progress">
				{{ t('{checked} of {total} done', { checked, total }) }}
			</span>
			<ul v-if="preview.length > 0" :class="$style.preview">
				<li v-for="previewItem in preview" :key="previewItem.id" :class="$style.previewItem">
					{{ previewItem.name }}
				</li>
			</ul>
			<span v-else :class="$style.done">
				{{ t('All done') }}
			</span>
		</button>
	</div>
</template>

<style module>
.widget {
	height: 100%;
}

.center {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.empty {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
}

.hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.content {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 4px;
	width: 100%;
	background: none;
	border: none;
	padding: 0;
	text-align: start;
	color: var(--color-main-text);
	cursor: pointer;
}

.name {
	font-weight: 600;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 100%;
}

.progress {
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.preview {
	list-style: none;
	margin: 4px 0 0;
	padding: 0;
	width: 100%;
}

.previewItem {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.previewItem::before {
	content: '• ';
	color: var(--color-text-maxcontrast);
}

.done {
	color: var(--color-success);
}
</style>
