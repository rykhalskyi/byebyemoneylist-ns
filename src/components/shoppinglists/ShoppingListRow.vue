<script setup lang="ts">
import type { Category, ListItem, Product, ShoppingList, Store } from '../../types.ts'

import { mdiChevronDown, mdiDelete, mdiDotsVertical, mdiReceiptText } from '@mdi/js'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ShoppingListItems from './ShoppingListItems.vue'
import { t } from '../../utils/l10n.ts'
import { addItemLabel, categoryColor, listIcon, listMarkStyle, listSubname, priceText, statusLabel, statusVariant } from '../../utils/listDisplay.ts'

const props = defineProps<{
	list: ShoppingList
	stores: Store[]
	categories: Category[]
	products: Product[]
	items?: ListItem[]
	itemsLoading: boolean
	itemsError: string
	expanded: boolean
}>()

const emit = defineEmits<{
	toggle: []
	delete: []
	receipt: []
	addItem: []
	deleteItem: [item: ListItem]
}>()

function storeName(storeId: string | null): string {
	return props.stores.find((store) => store.id === storeId)?.name ?? ''
}

function categoryName(categoryId: string | null): string {
	return props.categories.find((category) => category.id === categoryId)?.name ?? ''
}
</script>

<template>
	<div
		:class="$style.item"
		:style="listMarkStyle(categoryColor(props.list.categoryId, props.categories))">
		<NcListItem
			:name="props.list.name"
			oneLine
			@click="emit('toggle')">
			<template #icon>
				<NcIconSvgWrapper :path="listIcon(props.list)" :size="20" />
			</template>
			<template #subname>
				<div :class="$style.subname">
					<span>{{ listSubname(props.list, storeName(props.list.storeId), categoryName(props.list.categoryId)) }}</span>
					<NcChip
						v-if="priceText(props.list, props.items) !== null"
						:text="priceText(props.list, props.items) ?? ''"
						noClose />
					<NcChip :text="statusLabel(props.list.status)" :variant="statusVariant(props.list.status)" noClose />
					<NcIconSvgWrapper
						inline
						:path="mdiChevronDown"
						:size="20"
						:class="[$style.chevron, { [$style['chevron-open']]: props.expanded }]" />
				</div>
			</template>
			<template #extra-actions>
				<NcActions
					:forceMenu="true"
					:ariaLabel="t('Actions for {name}', { name: props.list.name })">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDotsVertical" :size="20" />
					</template>
					<NcActionButton v-if="props.list.hasReceipt" @click.stop="emit('receipt')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiReceiptText" :size="20" />
						</template>
						{{ t('View receipt') }}
					</NcActionButton>
					<NcActionButton @click.stop="emit('delete')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
						</template>
						{{ t('Delete') }}
					</NcActionButton>
				</NcActions>
			</template>
		</NcListItem>

		<ShoppingListItems
			v-if="props.expanded"
			:items="props.items ?? []"
			:loading="props.itemsLoading"
			:error="props.itemsError"
			:addLabel="addItemLabel(props.list)"
			:products="props.products"
			:categories="props.categories"
			@add="emit('addItem')"
			@delete="emit('deleteItem', $event)" />
	</div>
</template>

<style module>
.item {
	width: 100%;
	margin-block: 2px;
	border-inline-start: 3px solid transparent;
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

.chevron {
	flex: 0 0 auto;
	transform-origin: center;
	transition: transform 0.2s ease;
}

.chevron-open {
	transform: rotate(180deg);
}
</style>
