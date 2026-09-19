<script setup lang="ts">
import type { AnalyticsOverview, Category, Store } from '../types.ts'
import type { BarRow } from '../utils/analyticsCharts.ts'
import type { MonthCursor } from '../utils/analyticsRange.ts'

import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AccountStateCard from '../components/analytics/AccountStateCard.vue'
import CategoryDonut from '../components/analytics/CategoryDonut.vue'
import ChartLegendChips from '../components/analytics/ChartLegendChips.vue'
import MonthPicker from '../components/analytics/MonthPicker.vue'
import TopBarChart from '../components/analytics/TopBarChart.vue'
import { fetchAnalyticsOverview } from '../services/analyticsApi.ts'
import { fetchCategories, fetchStores } from '../services/listsApi.ts'
import { buildCategoryIndex, buildChildSlices, buildRootSlices } from '../utils/analyticsCharts.ts'
import { currentMonth, formatMonthLabel, monthRangeFor, shiftMonth } from '../utils/analyticsRange.ts'
import { t } from '../utils/l10n.ts'

const cursor = ref<MonthCursor>(currentMonth())
const categories = ref<Category[]>([])
const stores = ref<Store[]>([])
const overview = ref<AnalyticsOverview | null>(null)
const previous = ref<AnalyticsOverview | null>(null)
const loading = ref(true)
const failed = ref(false)
const drilledCategoryId = ref<string | null>(null)

const monthLabel = computed(() => formatMonthLabel(cursor.value))
const uncategorizedLabel = computed(() => t('Uncategorized'))
const noStoreLabel = computed(() => t('No store'))
const categoryIndex = computed(() => buildCategoryIndex(categories.value))
const storeIndex = computed(() => new Map(stores.value.map((store) => [store.id, store.name])))

const rootSlices = computed(() => overview.value === null
	? []
	: buildRootSlices(overview.value.byCategory, categories.value, uncategorizedLabel.value))

const childSlices = computed(() => overview.value === null || drilledCategoryId.value === null
	? []
	: buildChildSlices(overview.value.byCategory, categories.value, drilledCategoryId.value, uncategorizedLabel.value))

const drilledName = computed(() => drilledCategoryId.value === null
	? null
	: categoryIndex.value.get(drilledCategoryId.value)?.name ?? '')

const canDrill = computed(() => drilledCategoryId.value === null
	&& rootSlices.value.some((slice) => slice.id !== null && categories.value.some((category) => category.parentId === slice.id)))

const slices = computed(() => (drilledCategoryId.value === null ? rootSlices.value : childSlices.value))

const storeRows = computed<BarRow[]>(() => {
	if (overview.value === null) {
		return []
	}
	return [...overview.value.byStore]
		.sort((a, b) => b.total - a.total)
		.slice(0, 5)
		.map((entry) => ({
			label: entry.storeId === null ? noStoreLabel.value : storeIndex.value.get(entry.storeId) ?? noStoreLabel.value,
			value: entry.total,
		}))
})

const listRows = computed<BarRow[]>(() => {
	if (overview.value === null) {
		return []
	}
	return [...overview.value.byList]
		.sort((a, b) => b.total - a.total)
		.slice(0, 5)
		.map((entry) => ({ label: entry.name, value: entry.total }))
})

async function loadLookups(): Promise<void> {
	try {
		const [categoryList, storeList] = await Promise.all([fetchCategories(), fetchStores()])
		categories.value = categoryList
		stores.value = storeList
	} catch {
		categories.value = []
		stores.value = []
	}
}

async function load(): Promise<void> {
	loading.value = true
	failed.value = false
	drilledCategoryId.value = null
	try {
		const range = monthRangeFor(cursor.value)
		const previousRange = monthRangeFor(shiftMonth(cursor.value, -1))
		const [current, prev] = await Promise.all([
			fetchAnalyticsOverview(range.from, range.to),
			fetchAnalyticsOverview(previousRange.from, previousRange.to),
		])
		overview.value = current
		previous.value = prev
	} catch {
		overview.value = null
		previous.value = null
		failed.value = true
	} finally {
		loading.value = false
	}
}

function onPreviousMonth(): void {
	cursor.value = shiftMonth(cursor.value, -1)
	void load()
}

function onNextMonth(): void {
	cursor.value = shiftMonth(cursor.value, 1)
	void load()
}

onMounted(() => {
	void loadLookups()
	void load()
})
</script>

<template>
	<div :class="$style.wrapper">
		<h2 :class="$style.title">
			{{ t('Analytics') }}
		</h2>

		<MonthPicker
			:label="monthLabel"
			@previous="onPreviousMonth"
			@next="onNextMonth" />

		<template v-if="loading">
			<div :class="$style.state">
				<NcLoadingIcon :size="32" />
			</div>
		</template>

		<template v-else-if="failed || overview === null">
			<div :class="$style.state">
				<p :class="$style.error">
					{{ t('Failed to load analytics.') }}
				</p>
				<NcButton type="button" variant="tertiary" @click="load">
					{{ t('Try again') }}
				</NcButton>
			</div>
		</template>

		<template v-else>
			<AccountStateCard
				:spent="overview.totalSpent"
				:income="overview.totalIncome"
				:previousSpent="previous?.totalSpent ?? 0"
				:previousIncome="previous?.totalIncome ?? 0" />

			<section :class="$style.section">
				<h3 :class="$style.sectionTitle">
					{{ t('Expenses by category') }}
				</h3>
				<p v-if="slices.length === 0" :class="$style.empty">
					{{ t('No expenses this month') }}
				</p>
				<template v-else>
					<CategoryDonut
						:key="drilledCategoryId ?? 'root'"
						:slices="slices"
						:drilledName="drilledName"
						:canDrill="canDrill"
						@drill="drilledCategoryId = $event"
						@back="drilledCategoryId = null" />
					<ChartLegendChips :slices="slices" />
				</template>
			</section>

			<section :class="$style.section">
				<h3 :class="$style.sectionTitle">
					{{ t('Top 5 stores') }}
				</h3>
				<p v-if="storeRows.length === 0" :class="$style.empty">
					{{ t('No expenses this month') }}
				</p>
				<TopBarChart v-else :rows="storeRows" />
			</section>

			<section :class="$style.section">
				<h3 :class="$style.sectionTitle">
					{{ t('Top 5 shopping lists') }}
				</h3>
				<p v-if="listRows.length === 0" :class="$style.empty">
					{{ t('No expenses this month') }}
				</p>
				<TopBarChart v-else :rows="listRows" />
			</section>
		</template>
	</div>
</template>

<style module>
.wrapper {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	padding: 16px;
	width: 100%;
	max-width: 720px;
	margin: 0 auto;
}

.title {
	margin: 0;
}

.state {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 32px 0;
}

.error {
	margin: 0;
	color: var(--color-error-text, var(--color-error));
}

.section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.sectionTitle {
	margin: 0;
}

.empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
