<script setup lang="ts">
import type { Category, ListItem, Product } from '../../types.ts'

import { mdiDelete, mdiPlus } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { t } from '../../utils/l10n.ts'
import { itemDetails, itemSubname, productCategoryColor } from '../../utils/listDisplay.ts'

const props = defineProps<{
	items: ListItem[]
	loading: boolean
	error: string
	addLabel: string
	products: Product[]
	categories: Category[]
}>()

const emit = defineEmits<{
	add: []
	delete: [item: ListItem]
}>()

function itemStyle(item: ListItem): Record<string, string> {
	const color = productCategoryColor(item, props.products, props.categories)
	return color === null ? {} : { borderInlineStart: `2px solid ${color}` }
}
</script>

<template>
	<div :class="$style.items">
		<div v-if="props.loading" :class="$style.center">
			<NcLoadingIcon />
		</div>

		<p v-else-if="props.error" :class="$style['items-error']">
			{{ props.error }}
		</p>

		<template v-else>
			<ul v-if="props.items.length > 0" :class="$style['item-list']">
				<NcListItem
					v-for="item in props.items"
					:key="item.id"
					:name="item.productName"
					:details="itemDetails(item)"
					compact
					oneLine
					:style="itemStyle(item)">
					<template #subname>
						<span v-if="itemSubname(item)">{{ itemSubname(item) }}</span>
					</template>
					<template #extra-actions>
						<NcButton
							type="button"
							:aria-label="t('Delete {name}', { name: item.productName })"
							@click="emit('delete', item)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
			</ul>
			<p v-else :class="$style['no-items']">
				{{ t('No items yet.') }}
			</p>

			<div :class="$style['list-actions']">
				<NcButton
					type="button"
					variant="primary"
					@click="emit('add')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" :size="20" />
					</template>
					{{ props.addLabel }}
				</NcButton>
			</div>
		</template>
	</div>
</template>

<style module>
.items {
	border-inline-start: 3px solid var(--color-border);
	margin: 0 0 8px 16px;
	padding: 8px 0 8px 16px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.item-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.no-items {
	color: var(--color-text-maxcontrast);
	margin: 0;
	padding: 8px 0;
}

.items-error {
	color: var(--color-error);
	margin: 0;
	padding: 8px 0;
}

.list-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 8px;
}
</style>
