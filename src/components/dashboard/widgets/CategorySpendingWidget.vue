<script setup lang="ts">
import SpendingValue from './SpendingValue.vue'
import { monthRange } from '../../../utils/dashboardRange.ts'
import { t } from '../../../utils/l10n.ts'

const props = withDefaults(defineProps<{
	categoryId?: string | null
	categoryName?: string
}>(), {
	categoryId: null,
	categoryName: '',
})

const range = monthRange()
</script>

<template>
	<div :class="$style.wrapper">
		<SpendingValue :from="range.from" :to="range.to" :categoryId="props.categoryId" />
		<p :class="$style.category">
			{{ props.categoryName === '' ? t('No category') : props.categoryName }}
		</p>
	</div>
</template>

<style module>
.wrapper {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.category {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
