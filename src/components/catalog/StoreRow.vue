<script setup lang="ts">
import type { Store } from '../../types.ts'

import { mdiDelete, mdiPencil, mdiStore } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { t } from '../../utils/l10n.ts'

const props = withDefaults(defineProps<{
	store: Store
	accentColor?: string | null
	search?: string
}>(), {
	accentColor: null,
	search: '',
})

const emit = defineEmits<{
	edit: [store: Store]
	delete: [store: Store]
}>()
</script>

<template>
	<div
		:class="$style['store-row']"
		:style="props.accentColor ? { borderInlineStartColor: props.accentColor } : {}">
		<NcListItem>
			<template #name>
				<NcHighlight :text="props.store.name" :search="props.search" />
			</template>
			<template #icon>
				<NcIconSvgWrapper :path="mdiStore" :size="20" />
			</template>
			<template #subname>
				<div :class="$style.subname">
					<span v-if="props.store.address" :class="$style['store-address']">
						<NcHighlight :text="props.store.address" :search="props.search" />
					</span>
					<NcChip
						v-if="props.store.shared"
						:text="t('Shared')"
						variant="tertiary"
						noClose />
				</div>
			</template>
			<template #extra-actions>
				<NcButton
					v-if="!props.store.shared"
					type="button"
					:aria-label="t('Edit {name}', { name: props.store.name })"
					@click="emit('edit', props.store)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPencil" :size="20" />
					</template>
				</NcButton>
				<NcButton
					v-if="!props.store.shared"
					type="button"
					:aria-label="t('Delete {name}', { name: props.store.name })"
					@click="emit('delete', props.store)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDelete" :size="20" />
					</template>
				</NcButton>
			</template>
		</NcListItem>
	</div>
</template>

<style module>
.store-row {
	border-inline-start: 3px solid transparent;
	padding-inline-start: 8px;
}

.store-address {
	display: block;
	min-width: 0;
	max-width: 100%;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
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
