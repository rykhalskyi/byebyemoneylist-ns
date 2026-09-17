<script setup lang="ts">
import type { Category, ListItem, Product } from '../../types.ts'

import { mdiDelete, mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ProductThumb from '../catalog/ProductThumb.vue'
import { t } from '../../utils/l10n.ts'
import { itemDetails, itemSubname, productCategory } from '../../utils/listDisplay.ts'

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

function productOf(item: ListItem): Product | null {
	return props.products.find((candidate) => candidate.id === item.productId) ?? null
}

const rows = computed(() => props.items.map((item) => ({
	item,
	product: productOf(item),
	category: productCategory(item, props.products, props.categories),
})))
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
					v-for="row in rows"
					:key="row.item.id"
					:name="row.item.productName"
					:details="itemDetails(row.item)"
					compact
					oneLine>
					<template #icon>
						<ProductThumb
							:product="row.product"
							:category="row.category" />
					</template>
					<template #subname>
						<span v-if="itemSubname(row.item)">{{ itemSubname(row.item) }}</span>
					</template>
					<template #extra-actions>
						<NcButton
							type="button"
							:aria-label="t('Delete {name}', { name: row.item.productName })"
							@click="emit('delete', row.item)">
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
