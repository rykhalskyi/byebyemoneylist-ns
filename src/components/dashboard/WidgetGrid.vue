<script setup lang="ts">
import type { DashboardWidgetConfig } from '../../constants/dashboardWidgets.ts'

import { mdiViewDashboardOutline } from '@mdi/js'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import WidgetCard from './WidgetCard.vue'
import { dashboardWidgetDefinitions } from '../../constants/dashboardWidgets.ts'
import { t } from '../../utils/l10n.ts'

defineProps<{ widgets: DashboardWidgetConfig[] }>()

const emit = defineEmits<{
	remove: [id: string]
	reorder: [from: number, to: number]
	add: []
}>()

const definitions = dashboardWidgetDefinitions()
const dragIndex = ref<number | null>(null)

function definitionFor(type: DashboardWidgetConfig['type']) {
	return definitions.find((definition) => definition.type === type)
}

function onDragStart(index: number, event: DragEvent): void {
	dragIndex.value = index
	if (event.dataTransfer !== null) {
		event.dataTransfer.effectAllowed = 'move'
	}
}

function onDrop(index: number): void {
	if (dragIndex.value !== null && dragIndex.value !== index) {
		emit('reorder', dragIndex.value, index)
	}
	dragIndex.value = null
}
</script>

<template>
	<NcEmptyContent
		v-if="widgets.length === 0"
		:name="t('No widgets yet')"
		:description="t('Add widgets to see your spending at a glance.')">
		<template #icon>
			<NcIconSvgWrapper :path="mdiViewDashboardOutline" :size="64" />
		</template>
		<template #action>
			<NcButton type="button" variant="primary" @click="emit('add')">
				{{ t('Add widget') }}
			</NcButton>
		</template>
	</NcEmptyContent>

	<div v-else :class="$style.grid">
		<WidgetCard
			v-for="(widget, index) in widgets"
			:key="widget.id"
			:title="definitionFor(widget.type)?.label ?? widget.type"
			:icon="definitionFor(widget.type)?.icon ?? mdiViewDashboardOutline"
			:dragging="dragIndex === index"
			@remove="emit('remove', widget.id)"
			@dragstart="onDragStart(index, $event)"
			@dragover.prevent
			@drop="onDrop(index)"
			@dragend="dragIndex = null">
			<slot name="widget" :widget="widget" />
		</WidgetCard>
	</div>
</template>

<style module>
.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
	gap: 12px;
}
</style>
