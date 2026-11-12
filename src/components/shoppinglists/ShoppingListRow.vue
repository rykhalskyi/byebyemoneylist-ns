<script setup lang="ts">
import type { Category, ListItem, Product, ShoppingList, Store } from '../../types.ts'

import { mdiChevronDown, mdiContentCopy, mdiDelete, mdiDotsVertical, mdiOpenInNew, mdiReceiptText, mdiShareVariant } from '@mdi/js'
import { computed } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ShoppingListItems from './ShoppingListItems.vue'
import { t } from '../../utils/l10n.ts'
import { addItemLabel, categoryColor, hasActiveShares, isReadOnlyList, isRevokedList, isSharedList, isToBuy, isToBuyActive, listIcon, listMarkStyle, listSubname, priceText, sharedByLabel, statusLabel, statusVariant } from '../../utils/listDisplay.ts'

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
	copy: []
	share: []
	manageShares: []
	addItem: []
	addToBuyItem: []
	open: []
	toggleItem: [item: ListItem, checked: boolean]
	renameItem: [item: ListItem]
	deleteItem: [item: ListItem]
}>()

const toBuy = computed(() => isToBuy(props.list))
const toBuyActive = computed(() => isToBuyActive(props.list))

function onToggle() {
	if (!isRevokedList(props.list)) {
		emit('toggle')
	}
}

function onAdd() {
	if (toBuy.value) {
		emit('addToBuyItem')
	} else {
		emit('addItem')
	}
}

function onToggleItem(item: ListItem, checked: boolean) {
	emit('toggleItem', item, checked)
}

function onRenameItem(item: ListItem) {
	emit('renameItem', item)
}

function storeName(storeId: string | null): string {
	return props.stores.find((store) => store.id === storeId)?.name ?? ''
}

function categoryName(categoryId: string | null): string {
	return props.categories.find((category) => category.id === categoryId)?.name ?? ''
}
</script>

<template>
	<div
		:class="[$style.item, { [$style.revoked]: isRevokedList(props.list) }]"
		:style="listMarkStyle(categoryColor(props.list.categoryId, props.categories))">
		<NcListItem
			:name="props.list.name"
			oneLine
			@click="onToggle">
			<template #icon>
				<NcIconSvgWrapper :path="listIcon(props.list)" :size="20" />
			</template>
			<template #subname>
				<div :class="$style.subname">
					<span>{{ toBuy ? listSubname(props.list, '', '') : listSubname(props.list, storeName(props.list.storeId), categoryName(props.list.categoryId)) }}</span>
					<NcChip
						v-if="toBuyActive"
						:text="t('Active')"
						variant="success"
						noClose />
					<NcChip
						v-if="hasActiveShares(props.list)"
						:text="t('Shared')"
						:iconPath="mdiShareVariant"
						variant="tertiary"
						noClose
						:class="$style['share-mark']"
						@click.stop="emit('manageShares')" />
					<NcChip
						v-if="isSharedList(props.list)"
						:text="sharedByLabel(props.list)"
						variant="tertiary"
						noClose />
					<NcChip
						v-if="!toBuy && priceText(props.list, props.items) !== null"
						:text="priceText(props.list, props.items) ?? ''"
						noClose />
					<NcChip
						v-if="!toBuy"
						:text="statusLabel(props.list.status)"
						:variant="statusVariant(props.list.status)"
						noClose />
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
					<template v-if="toBuy">
						<NcActionButton v-if="isSharedList(props.list) && !isRevokedList(props.list)" @click.stop="emit('copy')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiContentCopy" :size="20" />
							</template>
							{{ t('Copy to my catalog') }}
						</NcActionButton>
						<NcActionButton @click.stop="emit('open')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiOpenInNew" :size="20" />
							</template>
							{{ t('Open list') }}
						</NcActionButton>
						<NcActionButton v-if="!isSharedList(props.list)" @click.stop="emit('delete')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
							{{ t('Delete') }}
						</NcActionButton>
					</template>
					<template v-else>
						<NcActionButton v-if="isSharedList(props.list) && !isRevokedList(props.list)" @click.stop="emit('copy')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiContentCopy" :size="20" />
							</template>
							{{ t('Copy to my catalog') }}
						</NcActionButton>
						<NcActionButton v-if="!isSharedList(props.list)" @click.stop="emit('share')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiShareVariant" :size="20" />
							</template>
							{{ t('Share') }}
						</NcActionButton>
						<NcActionButton v-if="!isSharedList(props.list) && props.list.hasReceipt" @click.stop="emit('receipt')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiReceiptText" :size="20" />
							</template>
							{{ t('View receipt') }}
						</NcActionButton>
						<NcActionButton v-if="!isSharedList(props.list)" @click.stop="emit('delete')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
							{{ t('Delete') }}
						</NcActionButton>
					</template>
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
			:readonly="isReadOnlyList(props.list)"
			:toBuy="toBuy"
			@add="onAdd"
			@toggle="onToggleItem"
			@rename="onRenameItem"
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

.revoked {
	opacity: 0.5;
	filter: grayscale(1);
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
	min-width: 0;
}

.share-mark {
	cursor: pointer;
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
