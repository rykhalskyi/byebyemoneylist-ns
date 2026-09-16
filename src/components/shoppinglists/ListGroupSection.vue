<script setup lang="ts">
import type { Category, ListItem, Product, ShoppingList, Store } from '../../types.ts'
import type { YearGroup } from '../../utils/listGroups.ts'

import { mdiChevronDown } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import ShoppingListRow from './ShoppingListRow.vue'
import { formatMonth, formatTotal } from '../../utils/format.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{
	year: YearGroup
	expanded: boolean
	expandedMonths: Record<string, boolean>
	expandedId: string | null
	stores: Store[]
	categories: Category[]
	products: Product[]
	itemsByList: Record<string, ListItem[]>
	itemsLoading: Record<string, boolean>
	itemsError: Record<string, string>
}>()

const emit = defineEmits<{
	toggleYear: []
	toggleMonth: [key: string]
	toggle: [list: ShoppingList]
	delete: [list: ShoppingList]
	receipt: [list: ShoppingList]
	addItem: [list: ShoppingList]
	deleteItem: [list: ShoppingList, item: ListItem]
}>()

function yearLabel(year: number | null): string {
	return year === null ? t('No date') : String(year)
}

function monthLabel(month: number | null): string {
	return month === null ? t('No date') : formatMonth(month)
}
</script>

<template>
	<section :class="$style.year">
		<button
			type="button"
			:class="$style['group-header']"
			:aria-expanded="props.expanded"
			@click="emit('toggleYear')">
			<span :class="$style['group-label']">{{ yearLabel(props.year.year) }}</span>
			<span v-if="props.year.total !== null" :class="$style['group-total']">{{ formatTotal(props.year.total) }}</span>
			<NcIconSvgWrapper
				inline
				:path="mdiChevronDown"
				:size="20"
				:class="[$style.chevron, { [$style['chevron-open']]: props.expanded }]" />
		</button>

		<div v-if="props.expanded">
			<div v-for="month in props.year.months" :key="month.key">
				<button
					type="button"
					:class="[$style['group-header'], $style['month-header']]"
					:aria-expanded="props.expandedMonths[month.key] ?? false"
					@click="emit('toggleMonth', month.key)">
					<span :class="$style['group-label']">{{ monthLabel(month.month) }}</span>
					<span v-if="month.total !== null" :class="$style['group-total']">{{ formatTotal(month.total) }}</span>
					<NcIconSvgWrapper
						inline
						:path="mdiChevronDown"
						:size="20"
						:class="[$style.chevron, { [$style['chevron-open']]: props.expandedMonths[month.key] }]" />
				</button>

				<div v-if="props.expandedMonths[month.key]" :class="$style['month-lists']">
					<ShoppingListRow
						v-for="list in month.lists"
						:key="list.id"
						:list="list"
						:stores="props.stores"
						:categories="props.categories"
						:products="props.products"
						:items="props.itemsByList[list.id]"
						:itemsLoading="props.itemsLoading[list.id] ?? false"
						:itemsError="props.itemsError[list.id] ?? ''"
						:expanded="props.expandedId === list.id"
						@toggle="emit('toggle', list)"
						@delete="emit('delete', list)"
						@receipt="emit('receipt', list)"
						@addItem="emit('addItem', list)"
						@deleteItem="emit('deleteItem', list, $event)" />
				</div>
			</div>
		</div>
	</section>
</template>

<style module>
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

.chevron {
	flex: 0 0 auto;
	transform-origin: center;
	transition: transform 0.2s ease;
}

.chevron-open {
	transform: rotate(180deg);
}
</style>
