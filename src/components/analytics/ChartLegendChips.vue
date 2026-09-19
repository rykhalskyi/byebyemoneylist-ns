<script setup lang="ts">
import type { DonutSlice } from '../../utils/analyticsCharts.ts'

import { computed } from 'vue'

const props = defineProps<{ slices: DonutSlice[] }>()

const total = computed(() => props.slices.reduce((sum, slice) => sum + slice.value, 0))

function percent(slice: DonutSlice): number {
	return total.value > 0 ? Math.round((slice.value / total.value) * 100) : 0
}
</script>

<template>
	<ul :class="$style.chips">
		<li v-for="slice in props.slices" :key="slice.id ?? 'uncategorized'" :class="$style.chip">
			<span :class="$style.dot" :style="{ backgroundColor: slice.color }" />
			<span v-if="slice.emoji !== null && slice.emoji !== ''" :class="$style.emoji">{{ slice.emoji }}</span>
			<span :class="$style.label">{{ slice.label }}</span>
			<span :class="$style.percent">{{ percent(slice) }}%</span>
		</li>
	</ul>
</template>

<style module>
.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 8px 0 0;
	padding: 0;
	list-style: none;
	justify-content: center;
}

.chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 10px;
	border-radius: 12px;
	background-color: var(--color-background-dark);
	font-size: 0.8rem;
}

.dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	display: inline-block;
}

.emoji {
	line-height: 1;
}

.percent {
	color: var(--color-text-maxcontrast);
}
</style>
