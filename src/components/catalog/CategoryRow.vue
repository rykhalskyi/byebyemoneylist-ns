<script setup lang="ts">
import type { Category } from '../../types.ts'

import { mdiCheck, mdiDelete, mdiPencil } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import CategoryBubble from './CategoryBubble.vue'

const props = withDefaults(defineProps<{
	category: Category
	parentName?: string
	depth?: number
	search?: string
}>(), {
	parentName: '',
	depth: 0,
	search: '',
})

const emit = defineEmits<{
	edit: [category: Category]
	delete: [category: Category]
	confirm: [category: Category]
}>()
</script>

<template>
	<div
		:class="$style['tree-item']"
		:style="{ paddingLeft: `${props.depth * 24}px` }">
		<NcListItem oneLine>
			<template #name>
				<NcHighlight :text="props.category.name" :search="props.search" />
			</template>
			<template #icon>
				<CategoryBubble :color="props.category.color" :emoji="props.category.emoji" />
			</template>
			<template #subname>
				<div :class="$style.subname">
					<NcHighlight v-if="props.parentName" :text="props.parentName" :search="props.search" />
					<NcChip
						v-if="props.category.status === 'pending_review'"
						text="Pending Review"
						variant="warning"
						noClose />
					<NcChip
						v-if="props.category.income"
						text="Income"
						variant="success"
						noClose />
				</div>
			</template>
			<template #extra-actions>
				<NcButton
					v-if="props.category.status === 'pending_review'"
					type="button"
					:aria-label="`Approve ${props.category.name}`"
					@click="emit('confirm', props.category)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCheck" :size="20" />
					</template>
				</NcButton>
				<NcButton
					type="button"
					:aria-label="`Edit ${props.category.name}`"
					@click="emit('edit', props.category)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPencil" :size="20" />
					</template>
				</NcButton>
				<NcButton
					type="button"
					:aria-label="`Delete ${props.category.name}`"
					@click="emit('delete', props.category)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDelete" :size="20" />
					</template>
				</NcButton>
			</template>
		</NcListItem>
	</div>
</template>

<style module>
.tree-item {
	width: 100%;
}

.subname {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
	width: 100%;
}
</style>
