<script setup lang="ts">
import type { DashboardWidgetType } from '../../constants/dashboardWidgets.ts'
import type { Category } from '../../types.ts'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { dashboardWidgetDefinitions } from '../../constants/dashboardWidgets.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{ open: boolean, categories: Category[] }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	add: [payload: { type: DashboardWidgetType, categoryId: string | null }]
}>()

const definitions = dashboardWidgetDefinitions()
const selectedType = ref(dashboardWidgetDefinitions()[0] ?? null)
const selectedCategory = ref<Category | null>(null)

const requiresCategory = computed(() => selectedType.value?.requiresCategory ?? false)
const canSubmit = computed(() => selectedType.value !== null && (!requiresCategory.value || selectedCategory.value !== null))

watch(
	() => props.open,
	(open) => {
		if (open) {
			selectedType.value = definitions[0] ?? null
			selectedCategory.value = null
		}
	},
)

function onCancel(): void {
	emit('update:open', false)
}

function onSubmit(): void {
	if (selectedType.value === null || !canSubmit.value) {
		return
	}
	emit('add', {
		type: selectedType.value.type,
		categoryId: requiresCategory.value ? selectedCategory.value?.id ?? null : null,
	})
	emit('update:open', false)
}
</script>

<template>
	<NcDialog
		:name="t('Add widget')"
		:open="props.open"
		size="normal"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcSelect
				v-model="selectedType"
				label="label"
				:inputLabel="t('Widget type')"
				:placeholder="t('Select a widget')"
				:options="definitions"
				:clearable="false"
				:filterable="false" />
			<NcSelect
				v-if="requiresCategory"
				v-model="selectedCategory"
				label="name"
				:inputLabel="t('Category')"
				:placeholder="t('Select a category')"
				:options="props.categories"
				:clearable="false" />
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				@click="onCancel">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				{{ t('Add widget') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}
</style>
