<script setup lang="ts">
import type { BarRow } from '../../utils/analyticsCharts.ts'

import { computed } from 'vue'
import VChart from 'vue-echarts'
import { buildBarOption } from '../../utils/analyticsCharts.ts'

import '../../utils/echarts.ts'

const props = defineProps<{ rows: BarRow[] }>()

const option = computed(() => buildBarOption(props.rows))
const height = computed(() => `${Math.max(props.rows.length * 46 + 24, 120)}px`)
</script>

<template>
	<div :class="$style.chart" :style="{ height }">
		<VChart :option="option" :class="$style.plot" autoresize />
	</div>
</template>

<style module>
.chart {
	width: 100%;
}

.plot {
	width: 100%;
	height: 100%;
}
</style>
