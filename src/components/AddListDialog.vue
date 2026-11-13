<script setup lang="ts">
import type { Category, ListItem, ShoppingList } from '../types.ts'

import { mdiAutorenew, mdiCart, mdiCashPlus } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createList, createToBuyList, fetchActiveToBuyList, fetchListItems } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'
import { itemName } from '../utils/listDisplay.ts'

type DialogType = 'toBuy' | 'subscription' | 'income'
type Period = 'WEEK' | 'MONTH' | 'YEAR'

interface PeriodOption {
	id: Period
	label: string
}

const props = defineProps<{
	open: boolean
	categories: Category[]
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [list: ShoppingList]
}>()

const type = ref<DialogType>('toBuy')
const name = ref('')
const selectedCategories = ref<Category[]>([])
const period = ref<PeriodOption>({ id: 'MONTH', label: t('Monthly') })
const recurring = ref(false)
const startEmpty = ref(true)
const carryOverItems = ref<ListItem[]>([])
const carryOver = ref<Record<string, boolean>>({})
const loadingItems = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)

const periodOptions = computed<PeriodOption[]>(() => [
	{ id: 'WEEK', label: t('Weekly') },
	{ id: 'MONTH', label: t('Monthly') },
	{ id: 'YEAR', label: t('Yearly') },
])

const availableCategories = computed(() => {
	if (type.value === 'income') {
		return props.categories.filter((category) => category.income)
	}
	return props.categories
})

const canSubmit = computed(() => {
	if (submitting.value) {
		return false
	}
	return type.value === 'toBuy' || name.value.trim() !== ''
})

watch(
	() => props.open,
	async (open) => {
		if (!open) {
			return
		}
		type.value = 'toBuy'
		name.value = ''
		selectedCategories.value = []
		period.value = periodOptions.value[1]
		recurring.value = false
		startEmpty.value = true
		carryOver.value = {}
		error.value = null
		submitting.value = false
		await loadCarryOverItems()
	},
	{ immediate: true },
)

async function loadCarryOverItems() {
	loadingItems.value = true
	try {
		const active = await fetchActiveToBuyList()
		carryOverItems.value = active === null
			? []
			: (await fetchListItems(active.id)).filter((item) => !item.isChecked)
	} catch {
		carryOverItems.value = []
	} finally {
		loadingItems.value = false
	}
}

function selectType(next: DialogType) {
	type.value = next
	selectedCategories.value = []
	error.value = null
}

async function onSubmit() {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		let list: ShoppingList
		if (type.value === 'toBuy') {
			const names = carryOverItems.value
				.filter((item) => carryOver.value[item.id] === true)
				.map((item) => itemName(item))
			list = await createToBuyList(t('To Buy'), names)
		} else {
			list = await createList({
				name: name.value.trim(),
				categoryIds: selectedCategories.value.map((category) => category.id),
				isIncome: type.value === 'income',
				isSubscription: type.value === 'subscription',
				isRecurring: type.value === 'subscription' ? true : recurring.value,
				recurringPeriod: period.value.id,
				isForwardEmpty: type.value === 'subscription' ? false : startEmpty.value,
			})
		}
		emit('created', list)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to create the list. Please try again.')
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('Add list')"
		:open="props.open"
		size="normal"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<div :class="$style.types">
				<NcButton
					type="button"
					:variant="type === 'toBuy' ? 'primary' : 'secondary'"
					:disabled="submitting"
					@click="selectType('toBuy')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCart" :size="20" />
					</template>
					{{ t('To Buy') }}
				</NcButton>
				<NcButton
					type="button"
					:variant="type === 'subscription' ? 'primary' : 'secondary'"
					:disabled="submitting"
					@click="selectType('subscription')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAutorenew" :size="20" />
					</template>
					{{ t('Subscription') }}
				</NcButton>
				<NcButton
					type="button"
					:variant="type === 'income' ? 'primary' : 'secondary'"
					:disabled="submitting"
					@click="selectType('income')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCashPlus" :size="20" />
					</template>
					{{ t('Income') }}
				</NcButton>
			</div>

			<template v-if="type === 'toBuy'">
				<p :class="$style.hint">
					{{ t('A To Buy list is a plain checklist. Items have no price, store or category.') }}
				</p>
				<div v-if="loadingItems" :class="$style.center">
					<NcLoadingIcon />
				</div>
				<div v-else-if="carryOverItems.length > 0" :class="$style.carry">
					<p :class="$style.carryTitle">
						{{ t('Carry over unchecked items from the active list') }}
					</p>
					<NcCheckboxRadioSwitch
						v-for="item in carryOverItems"
						:key="item.id"
						:modelValue="carryOver[item.id] === true"
						:disabled="submitting"
						@update:modelValue="carryOver[item.id] = $event">
						{{ itemName(item) }}
					</NcCheckboxRadioSwitch>
				</div>
			</template>

			<template v-else>
				<NcTextField
					v-model="name"
					:label="t('Name')"
					:placeholder="type === 'income' ? t('e.g. Salary') : t('e.g. Netflix')"
					:disabled="submitting"
					:error="name.trim() === '' && name.length > 0"
					:helperText="t('The list name is required.')" />
				<NcSelect
					v-model="selectedCategories"
					label="name"
					:inputLabel="t('Categories')"
					:placeholder="t('No categories')"
					:options="availableCategories"
					:disabled="submitting"
					:multiple="true"
					:keepOpen="true"
					clearable />
				<NcSelect
					v-model="period"
					label="label"
					:inputLabel="t('Interval')"
					:options="periodOptions"
					:disabled="submitting"
					:clearable="false" />
				<div v-if="type === 'income'" :class="$style.toggles">
					<NcCheckboxRadioSwitch v-model="recurring" type="switch" :disabled="submitting">
						{{ t('Recurring') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-model="startEmpty" type="switch" :disabled="submitting">
						{{ t('Start empty') }}
					</NcCheckboxRadioSwitch>
				</div>
			</template>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="emit('update:open', false)">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ t('Create') }}
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

.types {
	display: flex;
	width: 100%;
	gap: 8px;
}

.types > * {
	flex: 1 1 0;
	min-width: 0;
	justify-content: center;
}

.hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.carry {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.carryTitle {
	font-weight: 600;
	margin: 0 0 4px;
}

.toggles {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
