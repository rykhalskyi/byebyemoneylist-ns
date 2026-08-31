<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createProduct, fetchCategories, updateProduct } from '../services/listsApi'
import type { Category, Product } from '../types'

const props = defineProps<{ open: boolean; entity?: Product; preset?: 'subscription' | 'income' }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [product: Product]
	updated: [product: Product]
}>()

const name = ref('')
const category = ref<Category | null>(null)
const barcode = ref('')
const aliases = ref('')
const favorite = ref(false)
const subscription = ref(false)
const income = ref(false)
const categories = ref<Category[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

const isEditing = computed(() => props.entity !== undefined)

const availableCategories = computed(() => categories.value.filter((item) => item.income || !income.value))

const canSubmit = computed(() => name.value.trim() !== '' && !submitting.value)

watch(income, (isIncome) => {
	if (!isIncome && category.value?.income) {
		category.value = null
	}
})

watch(
	() => props.open,
	async (open) => {
		if (!open) {
			return
		}
		error.value = null
		submitting.value = false
		const entity = props.entity
		name.value = entity?.name ?? ''
		category.value = null
		barcode.value = entity?.barcode ?? ''
		aliases.value = entity?.aliases.join(', ') ?? ''
		favorite.value = entity?.isFavorite ?? false
		subscription.value = entity?.isSubscription ?? props.preset === 'subscription'
		income.value = entity?.isIncome ?? props.preset === 'income'
		requestAnimationFrame(() => nameField.value?.focus())

		loading.value = true
		try {
			categories.value = await fetchCategories()
			if (entity?.categoryId) {
				category.value = categories.value.find((candidate) => candidate.id === entity.categoryId) ?? null
			}
		} catch {
			error.value = 'Failed to load categories.'
		} finally {
			loading.value = false
		}
	},
)

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
		const payload = {
			name: name.value.trim(),
			categoryId: category.value?.id ?? null,
			barcode: barcode.value.trim() || null,
			aliases: aliases.value
				.split(',')
				.map((alias) => alias.trim())
				.filter((alias) => alias !== ''),
			isFavorite: favorite.value,
			isSubscription: subscription.value,
			isIncome: income.value,
		}
		const product = props.entity === undefined
			? await createProduct(payload)
			: await updateProduct(props.entity.id, payload)
		emit(props.entity === undefined ? 'created' : 'updated', product)
		emit('update:open', false)
	} catch {
		error.value = isEditing.value ? 'Failed to update the product. Please try again.' : 'Failed to create the product. Please try again.'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? 'Edit product' : 'New product'"
		:open="props.open"
		size="normal"
		is-form
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcTextField
				ref="nameField"
				v-model="name"
				label="Name"
				placeholder="e.g. Milk"
				:disabled="submitting"
				:error="name.trim() === '' && name.length > 0"
				helper-text="The product name is required." />

			<NcSelect
				v-model="category"
				label="name"
				input-label="Category"
				placeholder="No category"
				:options="availableCategories"
				:loading="loading"
				:disabled="submitting"
				clearable />

			<NcTextField
				v-model="barcode"
				label="Barcode"
				placeholder="e.g. 4001686310542"
				:disabled="submitting" />

			<NcTextField
				v-model="aliases"
				label="Aliases (comma-separated)"
				placeholder="e.g. M, Milch"
				:disabled="submitting"
				helper-text="Alternative names used on receipts." />

			<NcCheckboxRadioSwitch v-model="favorite" type="switch" :disabled="submitting">
				Favorite
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch v-model="subscription" type="switch" :disabled="submitting">
				Subscription
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch v-model="income" type="switch" :disabled="submitting">
				Income
			</NcCheckboxRadioSwitch>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
				Cancel
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ isEditing ? 'Save' : 'Create' }}
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

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
