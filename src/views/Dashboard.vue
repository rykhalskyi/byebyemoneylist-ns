<script setup lang="ts">
import type { Category } from '../types.ts'

import { mdiPlus } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import AddWidgetDialog from '../components/dashboard/AddWidgetDialog.vue'
import WidgetGrid from '../components/dashboard/WidgetGrid.vue'
import AddPurchaseWidget from '../components/dashboard/widgets/AddPurchaseWidget.vue'
import CategorySpendingWidget from '../components/dashboard/widgets/CategorySpendingWidget.vue'
import InfoWidget from '../components/dashboard/widgets/InfoWidget.vue'
import ScanPurchaseWidget from '../components/dashboard/widgets/ScanPurchaseWidget.vue'
import SpentThisMonthWidget from '../components/dashboard/widgets/SpentThisMonthWidget.vue'
import SpentTodayWidget from '../components/dashboard/widgets/SpentTodayWidget.vue'
import { useDashboardWidgets } from '../composables/useDashboardWidgets.ts'
import { fetchCategories } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'

const emit = defineEmits<{ purchase: [mode: 'manual' | 'scan'] }>()

const { widgets, addWidget, removeWidget, reorderWidgets } = useDashboardWidgets()

const categories = ref<Category[]>([])
const showAddWidget = ref(false)

const expenseCategories = computed(() => categories.value.filter((category) => !category.income))

onMounted(async () => {
	try {
		categories.value = await fetchCategories()
	} catch {
		categories.value = []
	}
})

function categoryName(categoryId: string | null | undefined): string {
	if (categoryId === null || categoryId === undefined) {
		return ''
	}
	return categories.value.find((category) => category.id === categoryId)?.name ?? ''
}
</script>

<template>
	<div :class="$style.wrapper">
		<div :class="$style.header">
			<h2>{{ t('Dashboard') }}</h2>
			<NcButton type="button" variant="primary" @click="showAddWidget = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" :size="20" />
				</template>
				{{ t('Add widget') }}
			</NcButton>
		</div>

		<WidgetGrid
			:widgets="widgets"
			@add="showAddWidget = true"
			@remove="removeWidget"
			@reorder="reorderWidgets">
			<template #widget="{ widget }">
				<SpentTodayWidget v-if="widget.type === 'spentToday'" />
				<SpentThisMonthWidget v-else-if="widget.type === 'thisMonth'" />
				<CategorySpendingWidget
					v-else-if="widget.type === 'categorySpending'"
					:categoryId="widget.categoryId ?? null"
					:categoryName="categoryName(widget.categoryId)" />
				<AddPurchaseWidget
					v-else-if="widget.type === 'addPurchase'"
					@purchase="emit('purchase', $event)" />
				<ScanPurchaseWidget
					v-else-if="widget.type === 'scanPurchase'"
					@purchase="emit('purchase', $event)" />
				<InfoWidget v-else-if="widget.type === 'info'" />
			</template>
		</WidgetGrid>

		<AddWidgetDialog
			:open="showAddWidget"
			:categories="expenseCategories"
			@update:open="showAddWidget = $event"
			@add="addWidget($event.type, $event.categoryId)" />
	</div>
</template>

<style module>
.wrapper {
	box-sizing: border-box;
	padding: 16px;
	width: 100%;
}

.header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
}
</style>
