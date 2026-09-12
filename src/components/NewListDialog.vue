<script setup lang="ts">
import type { Category, ShoppingList, Store } from '../types.ts'

import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createList, fetchCategories, fetchStores } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'

const props = withDefaults(defineProps<{ open: boolean, isIncome?: boolean }>(), {
	isIncome: false,
})

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [list: ShoppingList]
}>()

const name = ref('')
const store = ref<Store | null>(null)
const category = ref<Category | null>(null)
const stores = ref<Store[]>([])
const categories = ref<Category[]>([])
const recurring = ref(false)
const subscription = ref(false)
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

const canSubmit = computed(() => name.value.trim() !== '' && !submitting.value)

const availableCategories = computed(() => {
	if (!props.isIncome) {
		return categories.value
	}
	return categories.value.filter((item) => item.income)
})

watch(
	() => props.open,
	(open) => {
		if (open) {
			error.value = null
			submitting.value = false
			name.value = ''
			store.value = null
			category.value = null
			recurring.value = false
			subscription.value = false
			requestAnimationFrame(() => nameField.value?.focus())
		}
	},
)

onMounted(async () => {
	try {
		const [storeData, categoryData] = await Promise.all([fetchStores(), fetchCategories()])
		stores.value = storeData
		categories.value = categoryData
	} catch {
		loading.value = false
	}
})

function onCancel() {
	emit('update:open', false)
}

async function onSubmit() {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const list = await createList({
			name: name.value.trim(),
			storeId: props.isIncome ? null : store.value?.id ?? null,
			categoryId: category.value?.id ?? null,
			isIncome: props.isIncome,
			isRecurring: recurring.value,
			isSubscription: props.isIncome ? false : subscription.value,
		})
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
		:name="props.isIncome ? t('New income list') : t('New list')"
		:open="props.open"
		size="normal"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcTextField
				ref="nameField"
				v-model="name"
				:label="t('Name')"
				:placeholder="props.isIncome ? t('e.g. Salary') : t('e.g. Weekly groceries')"
				:disabled="submitting"
				:error="name.trim() === '' && name.length > 0"
				:helperText="t('The list name is required.')" />
			<NcSelect
				v-if="!props.isIncome"
				v-model="store"
				label="name"
				:inputLabel="t('Store')"
				:placeholder="t('Select a store (optional)')"
				:options="stores"
				:loading="loading"
				:disabled="submitting"
				clearable />
			<NcSelect
				v-model="category"
				label="name"
				:inputLabel="t('Category')"
				:placeholder="t('Select a category (optional)')"
				:options="availableCategories"
				:loading="loading"
				:disabled="submitting"
				clearable />
			<div :class="$style.toggles">
				<NcCheckboxRadioSwitch v-model="recurring" type="switch" :disabled="submitting">
					{{ t('Recurring') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-if="!props.isIncome"
					v-model="subscription"
					type="switch"
					:disabled="submitting">
					{{ t('Subscription') }}
				</NcCheckboxRadioSwitch>
			</div>
			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
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

.toggles {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
