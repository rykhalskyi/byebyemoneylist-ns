<script setup lang="ts">
import type { Category, Product } from '../../types.ts'

import { mdiCallMerge, mdiDelete, mdiDotsVertical, mdiPackageVariant, mdiPencil, mdiStar } from '@mdi/js'
import { computed } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import CategoryBubble from './CategoryBubble.vue'
import { formatTotal } from '../../utils/format.ts'
import { t } from '../../utils/l10n.ts'

const props = withDefaults(defineProps<{
	product: Product
	category?: Category | null
	search?: string
}>(), {
	category: null,
	search: '',
})

const emit = defineEmits<{
	open: [product: Product]
	edit: [product: Product]
	merge: [product: Product]
	delete: [product: Product]
}>()

const lastPriceText = computed(() => (props.product.lastPrice === null ? null : formatTotal(props.product.lastPrice)))
</script>

<template>
	<div :class="$style['product-row']" @click="emit('open', props.product)">
		<NcListItem oneLine>
			<template #name>
				<NcHighlight :text="props.product.name" :search="props.search" />
			</template>
			<template #icon>
				<CategoryBubble
					v-if="props.category"
					:color="props.category.color"
					:emoji="props.category.emoji" />
				<NcIconSvgWrapper
					v-else
					:path="mdiPackageVariant"
					:size="20" />
			</template>
			<template #subname>
				<div :class="$style.subname">
					<NcChip
						v-if="props.product.isSubscription"
						:text="t('Subscription')"
						variant="primary"
						noClose />
					<NcChip
						v-if="props.product.isIncome"
						:text="t('Income')"
						variant="success"
						noClose />
					<span v-if="props.category">{{ props.category.name }}</span>
					<NcChip
						v-if="lastPriceText !== null"
						:text="lastPriceText"
						noClose />
					<NcChip
						v-if="props.product.barcode"
						:text="props.product.barcode"
						noClose />
					<NcIconSvgWrapper
						v-if="props.product.isFavorite"
						:path="mdiStar"
						:size="20"
						:class="$style.favorite" />
				</div>
			</template>
			<template #extra-actions>
				<NcActions
					:forceMenu="true"
					:ariaLabel="t('Actions for {name}', { name: props.product.name })"
					@click.stop>
					<template #icon>
						<NcIconSvgWrapper :path="mdiDotsVertical" :size="20" />
					</template>
					<NcActionButton @click.stop="emit('edit', props.product)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" :size="20" />
						</template>
						{{ t('Edit') }}
					</NcActionButton>
					<NcActionButton @click.stop="emit('merge', props.product)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiCallMerge" :size="20" />
						</template>
						{{ t('Merge') }}
					</NcActionButton>
					<NcActionButton
						class="bbml-product-action-delete"
						@click.stop="emit('delete', props.product)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
						</template>
						{{ t('Delete') }}
					</NcActionButton>
				</NcActions>
			</template>
		</NcListItem>
	</div>
</template>

<style module>
.product-row {
	border-inline-start: 3px solid transparent;
	padding-inline-start: 8px;
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
	width: 100%;
}

.favorite {
	color: var(--color-warning);
}
</style>

<style>
.bbml-product-action-delete .action-button,
.bbml-product-action-delete .action-button * {
	color: var(--color-error);
}
</style>
