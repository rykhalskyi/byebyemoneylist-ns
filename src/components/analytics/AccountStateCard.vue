<script setup lang="ts">
import { computed } from 'vue'
import { formatTotal } from '../../utils/format.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{
	spent: number
	income: number
	previousSpent: number
	previousIncome: number
}>()

const balance = computed(() => props.income - props.spent)
const previousBalance = computed(() => props.previousIncome - props.previousSpent)
const delta = computed(() => balance.value - previousBalance.value)
const trendUp = computed(() => delta.value >= 0)
</script>

<template>
	<div :class="$style.card">
		<div :class="$style.balanceBlock">
			<span :class="$style.caption">{{ t('Balance') }}</span>
			<span :class="[$style.balance, balance >= 0 ? $style.positive : $style.negative]">
				{{ formatTotal(balance) }}
			</span>
		</div>

		<div :class="$style.columns">
			<div :class="$style.column">
				<span :class="$style.caption">{{ t('Income') }}</span>
				<span :class="[$style.amount, $style.positive]">{{ formatTotal(income) }}</span>
			</div>
			<div :class="[$style.column, $style.alignEnd]">
				<span :class="$style.caption">{{ t('Expenses') }}</span>
				<span :class="[$style.amount, $style.negative]">{{ formatTotal(spent) }}</span>
			</div>
		</div>

		<div :class="[$style.trend, trendUp ? $style.positive : $style.negative]" data-testid="balance-trend">
			{{ trendUp ? '↑' : '↓' }} {{ formatTotal(Math.abs(delta)) }} {{ t('vs last month') }}
		</div>
	</div>
</template>

<style module>
.card {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-background-dark);
	width: 100%;
}

.balanceBlock {
	display: flex;
	flex-direction: column;
}

.caption {
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}

.balance {
	font-size: 2rem;
	font-weight: bold;
	font-variant-numeric: tabular-nums;
}

.columns {
	display: flex;
	justify-content: space-between;
}

.column {
	display: flex;
	flex-direction: column;
}

.alignEnd {
	text-align: right;
}

.amount {
	font-size: 1.1rem;
	font-weight: 600;
	font-variant-numeric: tabular-nums;
}

.trend {
	font-size: 0.9rem;
}

.positive {
	color: var(--color-success-text, var(--color-success, #2d936c));
}

.negative {
	color: var(--color-error-text, var(--color-error, #ff6b6b));
}
</style>
