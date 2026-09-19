<script setup lang="ts">
import type { DonutSlice } from '../../utils/analyticsCharts.ts'

import { mdiArrowLeft } from '@mdi/js'
import { computed, ref } from 'vue'
import VChart from 'vue-echarts'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { buildDonutOption } from '../../utils/analyticsCharts.ts'
import { formatTotal } from '../../utils/format.ts'
import { t } from '../../utils/l10n.ts'

import '../../utils/echarts.ts'

const props = defineProps<{
	slices: DonutSlice[]
	drilledName: string | null
	canDrill: boolean
}>()

const emit = defineEmits<{ drill: [id: string], back: [] }>()

const selectedId = ref<string | null>(null)

const option = computed(() => buildDonutOption(props.slices))
const total = computed(() => props.slices.reduce((sum, slice) => sum + slice.value, 0))
const selected = computed(() => props.slices.find((slice) => slice.id === selectedId.value) ?? null)
const selectedPercent = computed(() => (selected.value !== null && total.value > 0 ? (selected.value.value / total.value) * 100 : 0))

function onSelect(params: unknown): void {
	const id = (params as { data?: { id?: string | null } }).data?.id
	selectedId.value = id ?? null
}

function onDrill(params: unknown): void {
	if (!props.canDrill) {
		return
	}
	const id = (params as { data?: { id?: string | null } }).data?.id
	if (id !== undefined && id !== null) {
		emit('drill', id)
	}
}
</script>

<template>
	<div :class="$style.wrapper">
		<div v-if="drilledName !== null" :class="$style.header">
			<NcButton type="button" variant="tertiary-no-background" @click="emit('back')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiArrowLeft" :size="20" />
				</template>
				{{ t('Back') }}
			</NcButton>
			<span :class="$style.headerTitle">{{ drilledName }}</span>
		</div>

		<div :class="$style.chart">
			<VChart
				:option="option"
				:class="$style.plot"
				autoresize
				@click="onSelect"
				@dblclick="onDrill" />
			<div :class="$style.center" data-testid="donut-center">
				<template v-if="selected !== null">
					<span :class="$style.centerLabel">{{ selected.label }}</span>
					<span :class="$style.centerPercent">{{ Math.round(selectedPercent) }}%</span>
					<span :class="$style.centerAmount">{{ formatTotal(selected.value) }}</span>
				</template>
				<template v-else>
					<span :class="$style.centerLabel">{{ t('Total') }}</span>
					<span :class="$style.centerAmount">{{ formatTotal(total) }}</span>
				</template>
			</div>
		</div>

		<p v-if="canDrill" :class="$style.hint">
			{{ t('Double-click a segment to drill down') }}
		</p>
	</div>
</template>

<style module>
.wrapper {
	display: flex;
	flex-direction: column;
	width: 100%;
}

.header {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-bottom: 4px;
}

.headerTitle {
	font-weight: 600;
}

.chart {
	position: relative;
	width: 100%;
	height: 340px;
}

.plot {
	width: 100%;
	height: 100%;
}

.center {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	pointer-events: none;
	max-width: 120px;
}

.centerLabel {
	font-size: 0.9rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.centerPercent {
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
}

.centerAmount {
	font-size: 1rem;
	font-weight: bold;
	font-variant-numeric: tabular-nums;
	color: var(--color-main-text);
}

.hint {
	margin: 0;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}
</style>
